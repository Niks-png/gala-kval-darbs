<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\PriceDropNotifier;
use App\Services\ProductCategorizer;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SplFileObject;
use Throwable;

class ImportProductsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:import
        {file=scrapers/top_products.csv : The CSV file containing scraped products}
        {--force : Import even if a store has far fewer products than it has on offer now}';

    /**
     * Columns every scraper writes. image_url is optional.
     */
    private const REQUIRED_COLUMNS = ['title', 'store', 'original_price', 'current_price', 'unit_price', 'unit'];

    /**
     * Rows per database statement.
     */
    private const BATCH_SIZE = 500;

    /**
     * Units a unit price may be given in.
     */
    private const UNITS = ['€/kg', '€/l'];

    /**
     * @var string
     */
    protected $description = 'Import scraped products; the file is a store\'s full offer list, so its products missing from it are marked as ended';

    public function handle(ProductCategorizer $categorizer, PriceDropNotifier $notifier): int
    {
        $fileArgument = (string) $this->argument('file');
        $filePath = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $fileArgument)
            ? $fileArgument
            : base_path($fileArgument);

        if (! is_file($filePath)) {
            $this->error("Product file not found: {$filePath}");

            return self::FAILURE;
        }

        $file = new SplFileObject($filePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $header = $file->fgetcsv();
        $missing = array_diff(self::REQUIRED_COLUMNS, is_array($header) ? $header : []);

        if ($missing !== []) {
            throw new RuntimeException('The product CSV is missing columns: '.implode(', ', $missing).'.');
        }

        // One timestamp for the whole file, so "not in this import" is "updated before it".
        $importedAt = now();
        $products = [];
        $invalid = [];
        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if ($row === [null] || $row === false) {
                continue;
            }

            // Column name => value, so the order of the CSV columns does not matter.
            $values = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $product = $this->validProduct($values);

            if (is_string($product)) {
                $invalid[] = $product;

                continue;
            }

            $products[] = [
                ...$product,
                'category' => $categorizer->categorize($product['title']),
                'offer_ended_at' => null,
                'created_at' => $importedAt,
                'updated_at' => $importedAt,
            ];
        }

        if ($invalid !== []) {
            // Scraped data is never trusted: a broken row is left out instead of saved or crashing the import.
            $this->warn(sprintf('Skipped %d rows with invalid data, e.g. %s', count($invalid), $invalid[0]));
            Log::warning('products:import skipped invalid rows', ['file' => $filePath, 'count' => count($invalid), 'examples' => array_slice($invalid, 0, 5)]);
        }

        if ($products === []) {
            throw new RuntimeException('The product file has no products; nothing was imported.');
        }

        if (! $this->option('force') && ($problem = $this->incompleteStore($products)) !== null) {
            throw new RuntimeException($problem.' Nothing was imported. If the shop really has this few offers, run again with --force.');
        }

        // Prices, ended offers and price history change together or not at all.
        $ended = DB::transaction(function () use ($products, $importedAt): int {
            // Compared with the prices before this import, so it must be worked out first.
            $priceChanges = $this->priceChanges($products, $importedAt);

            // In batches: one statement for a whole shop could pass MySQL's limit of 65,535 values
            // (Rimi alone is about 4,300 products × 11 columns). Still one transaction.
            foreach (array_chunk($products, self::BATCH_SIZE) as $batch) {
                Product::upsert(
                    $batch,
                    ['title', 'store'],
                    ['category', 'original_price', 'current_price', 'unit_price', 'unit', 'image_url', 'offer_ended_at', 'updated_at'],
                );
            }

            $ended = Product::query()
                ->whereIn('store', array_unique(array_column($products, 'store')))
                ->where('updated_at', '<', $importedAt)
                ->onOffer()
                ->update(['offer_ended_at' => $importedAt]);

            foreach (array_chunk($priceChanges, self::BATCH_SIZE) as $batch) {
                ProductPriceHistory::insert($batch);
            }

            return $ended;
        });

        if ($ended > 0) {
            $this->info(sprintf('Marked %d products no longer on offer as ended.', $ended));
        }

        $this->info(sprintf('Imported %d products.', count($products)));

        // Only after the prices are committed. A failure here must not undo the import;
        // the unsent drops stay pending and go out with the next import.
        try {
            $alerts = $notifier->sendPending();

            if ($alerts > 0) {
                $this->info(sprintf('Sent %d price drop alerts.', $alerts));
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->warn('Price drop alerts could not be sent; they will be sent with the next import.');
        }

        return self::SUCCESS;
    }

    /**
     * A scraper that silently loads only part of a shop would otherwise mark the rest as ended.
     * So a store whose file has far fewer products than it has on offer now is refused.
     *
     * @param  list<array<string, mixed>>  $products
     */
    private function incompleteStore(array $products): ?string
    {
        $ratio = (float) config('services.scraper.min_import_ratio');
        $counts = array_count_values(array_column($products, 'store'));

        foreach ($counts as $store => $count) {
            $onOffer = Product::query()->where('store', $store)->onOffer()->count();

            // Tiny stores swing too much week to week to judge.
            if ($onOffer >= 20 && $count < $onOffer * $ratio) {
                return sprintf('The file has %d %s products, but %d are on offer now (minimum %d%%).', $count, $store, $onOffer, $ratio * 100);
            }
        }

        return null;
    }

    /**
     * A price history row for each product whose price is about to change.
     *
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function priceChanges(array $products, CarbonInterface $importedAt): array
    {
        // Only the four columns needed, as plain rows, for the shops in this file
        // (not a WHERE title IN list of thousands of titles, not full models).
        $existingProducts = Product::query()
            ->whereIn('store', array_unique(array_column($products, 'store')))
            ->toBase()
            ->get(['id', 'title', 'store', 'current_price'])
            ->keyBy(fn (object $product): string => $product->title.'|'.$product->store);

        $priceChanges = [];
        foreach ($products as $product) {
            $existing = $existingProducts->get($product['title'].'|'.$product['store']);
            if ($existing?->current_price !== null
                && $product['current_price'] !== null
                && (float) $existing->current_price !== (float) $product['current_price']) {
                $priceChanges[] = [
                    'product_id' => $existing->id,
                    'previous_price' => $existing->current_price,
                    'new_price' => $product['current_price'],
                    'created_at' => $importedAt,
                    'updated_at' => $importedAt,
                ];
            }
        }

        return $priceChanges;
    }

    /**
     * Check one CSV row. A row with a broken title, store or price is rejected (returned as a
     * reason); optional fields that are broken are left empty instead.
     *
     * @param  array<string, string|null>  $values
     * @return array{title: string, store: string, original_price: ?string, current_price: ?string, unit_price: ?string, unit: ?string, image_url: ?string}|string
     */
    private function validProduct(array $values): array|string
    {
        $title = $this->nullableValue($values['title']);
        $store = $this->nullableValue($values['store']);

        if ($title === null || $store === null || mb_strlen($title) > 255 || mb_strlen($store) > 255) {
            return sprintf('missing or too long title/store ("%s")', mb_substr((string) $title, 0, 40));
        }

        $currentPrice = $this->nullablePrice($values['current_price']);

        // No price at all is allowed (e.g. a leaflet banner), but a price must be a real one.
        if ($currentPrice !== null && ! $this->isValidPrice($currentPrice)) {
            return sprintf('invalid price "%s" for "%s"', $values['current_price'], mb_substr($title, 0, 40));
        }

        $unit = $this->nullableValue($values['unit']);
        $unitPrice = $this->nullablePrice($values['unit_price']);

        if (! in_array($unit, self::UNITS, true) || $unitPrice === null || ! $this->isValidPrice($unitPrice)) {
            $unit = $unitPrice = null;
        }

        $originalPrice = $this->nullableValue($values['original_price']);
        $imageUrl = $this->nullableValue($values['image_url'] ?? null);

        return [
            'title' => $title,
            'store' => $store,
            'original_price' => $originalPrice !== null && mb_strlen($originalPrice) <= 255 ? $originalPrice : null,
            'current_price' => $currentPrice,
            'unit_price' => $unitPrice,
            'unit' => $unit,
            'image_url' => $this->isValidImageUrl($imageUrl) ? $imageUrl : null,
        ];
    }

    /**
     * A positive amount that fits the decimal(10, 2) price columns.
     */
    private function isValidPrice(string $price): bool
    {
        return preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price) === 1 && (float) $price > 0;
    }

    private function isValidImageUrl(?string $url): bool
    {
        return $url !== null
            && mb_strlen($url) <= 2048
            && preg_match('#^https?://#i', $url) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function nullableValue(?string $value): ?string
    {
        // Also strip invisible zero-width characters, which shops sometimes put in empty price fields.
        $value = (string) preg_replace('/^[\s\x{200B}-\x{200D}\x{FEFF}]+|[\s\x{200B}-\x{200D}\x{FEFF}]+$/u', '', (string) $value);

        return $value === '' || strtoupper($value) === 'N/A' ? null : $value;
    }

    private function nullablePrice(?string $value): ?string
    {
        $value = $this->nullableValue($value);

        if ($value === null) {
            return null;
        }

        return str_replace(',', '.', (string) preg_replace('/[^0-9,.-]/', '', $value));
    }
}
