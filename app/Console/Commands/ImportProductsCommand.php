<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\PriceDropNotifier;
use App\Services\ProductCategorizer;
use Illuminate\Console\Command;
use RuntimeException;
use SplFileObject;

class ImportProductsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:import
        {file=scrapers/top_products.csv : The CSV file containing scraped products}';

    /**
     * Columns every scraper writes. image_url is optional.
     */
    private const REQUIRED_COLUMNS = ['title', 'store', 'original_price', 'current_price', 'unit_price', 'unit'];

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
        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if ($row === [null] || $row === false) {
                continue;
            }

            // Column name => value, so the order of the CSV columns does not matter.
            $values = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
            $title = trim((string) $values['title']);
            $store = trim((string) $values['store']);

            if ($title === '' || $store === '') {
                continue;
            }

            $products[] = [
                'title' => $title,
                'store' => $store,
                'category' => $categorizer->categorize($title),
                'original_price' => $this->nullableValue($values['original_price']),
                'current_price' => $this->nullablePrice($values['current_price']),
                'unit_price' => $this->nullablePrice($values['unit_price']),
                'unit' => $this->nullableValue($values['unit']),
                'image_url' => $this->nullableValue($values['image_url'] ?? null),
                'offer_ended_at' => null,
                'created_at' => $importedAt,
                'updated_at' => $importedAt,
            ];
        }

        if ($products !== []) {
            $existingProducts = Product::query()
                ->whereIn('title', array_column($products, 'title'))
                ->get()
                ->keyBy(fn (Product $product): string => $product->title.'|'.$product->store);

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
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            Product::upsert(
                $products,
                ['title', 'store'],
                ['category', 'original_price', 'current_price', 'unit_price', 'unit', 'image_url', 'offer_ended_at', 'updated_at'],
            );

            $ended = Product::query()
                ->whereIn('store', array_unique(array_column($products, 'store')))
                ->where('updated_at', '<', $importedAt)
                ->onOffer()
                ->update(['offer_ended_at' => $importedAt]);

            if ($ended > 0) {
                $this->info(sprintf('Marked %d products no longer on offer as ended.', $ended));
            }

            if ($priceChanges !== []) {
                ProductPriceHistory::insert($priceChanges);

                $alerts = $notifier->notify($priceChanges);
                if ($alerts > 0) {
                    $this->info(sprintf('Sent %d price drop alerts.', $alerts));
                }
            }
        }

        $this->info(sprintf('Imported %d products.', count($products)));

        return self::SUCCESS;
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
