<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Console\Command;
use RuntimeException;
use SplFileObject;

class ImportProductsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:import
        {file=public/top_products.csv : The CSV file containing scraped products}
        {--store= : Store name used when importing a legacy CSV without a store column}';

    /**
     * @var string
     */
    protected $description = 'Import scraped products into the database';

    public function handle(): int
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
        $hasStoreColumn = $header === ['title', 'store', 'original_price', 'current_price'];
        $hasUnitColumns = $header === ['title', 'store', 'original_price', 'current_price', 'unit_price', 'unit'];
        $legacyHeader = ['title', 'original_price', 'current_price'];

        if (! $hasStoreColumn && ! $hasUnitColumns && $header !== $legacyHeader) {
            throw new RuntimeException('The product CSV has an unsupported header.');
        }

        $storeOption = trim((string) $this->option('store'));
        if (! $hasStoreColumn && ! $hasUnitColumns && $storeOption === '') {
            throw new RuntimeException('The --store option is required when importing a legacy product CSV.');
        }

        $products = [];
        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if ($row === [null] || $row === false) {
                continue;
            }

            if ($hasUnitColumns) {
                [$title, $store, $originalPrice, $currentPrice, $unitPrice, $unit] = array_pad($row, 6, null);
            } elseif ($hasStoreColumn) {
                [$title, $store, $originalPrice, $currentPrice] = array_pad($row, 4, null);
                $unitPrice = null;
                $unit = null;
            } else {
                [$title, $originalPrice, $currentPrice] = array_pad($row, 3, null);
                $store = $storeOption;
                $unitPrice = null;
                $unit = null;
            }
            $title = trim((string) $title);
            $store = trim((string) $store);

            if ($title === '' || $store === '') {
                continue;
            }

            $products[] = [
                'title' => $title,
                'store' => $store,
                'original_price' => $this->nullableValue($originalPrice),
                'current_price' => $this->nullablePrice($currentPrice),
                'price' => $this->nullablePrice($currentPrice) ?? '0.00',
                'unit_price' => $this->nullablePrice($unitPrice),
                'unit' => $this->nullableValue($unit),
                'created_at' => now(),
                'updated_at' => now(),
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
                ['original_price', 'current_price', 'price', 'unit_price', 'unit', 'updated_at'],
            );

            if ($priceChanges !== []) {
                ProductPriceHistory::insert($priceChanges);
            }
        }

        $this->info(sprintf('Imported %d products.', count($products)));

        return self::SUCCESS;
    }

    private function nullableValue(?string $value): ?string
    {
        $value = trim((string) $value);

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
