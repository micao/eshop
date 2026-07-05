<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessCsvImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public $backoff = [10, 30];

    public string $filePath;

    public string $fileName;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $fileName)
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Resolve absolute path from Laravel storage disk
        if (! Storage::exists($this->filePath)) {
            Log::error("ProcessCsvImportJob: CSV file path '{$this->filePath}' does not exist in storage.");

            return;
        }

        $realPath = Storage::path($this->filePath);

        // Stream rows using Generator to maintain O(1) memory usage
        foreach ($this->readCsvRows($realPath) as $data) {
            DB::beginTransaction();
            try {
                $categoryId = null;
                if (! empty($data['product_category'])) {
                    $categoryName = $data['product_category'];
                    $categorySlug = Str::slug($categoryName);

                    $category = Category::firstOrCreate(
                        ['slug' => $categorySlug],
                        ['name' => $categoryName]
                    );
                    $categoryId = $category->id;
                }

                $brandId = null;
                if (! empty($data['product_brand'])) {
                    $brandName = $data['product_brand'];
                    $brandSlug = Str::slug($brandName);

                    $brand = Brand::firstOrCreate(
                        ['slug' => $brandSlug],
                        ['name' => $brandName]
                    );
                    $brandId = $brand->id;
                }

                $supplierId = null;
                if (! empty($data['product_supplier'])) {
                    $supplierName = $data['product_supplier'];
                    $supplierSlug = Str::slug($supplierName);

                    $supplier = Supplier::firstOrCreate(
                        ['slug' => $supplierSlug],
                        ['name' => $supplierName]
                    );
                    $supplierId = $supplier->id;
                }

                // 1. Find or create the parent product by slug
                $product = Product::updateOrCreate(
                    ['slug' => $data['product_slug']],
                    [
                        'category_id' => $categoryId,
                        'brand_id' => $brandId,
                        'supplier_id' => $supplierId,
                        'name' => $data['product_name'],
                        'description' => $data['product_description'] ?? '',
                        'summary' => $data['product_summary'] ?? '',
                        'status' => $data['product_status'] ?? 'active',
                        'thumbnail' => $data['product_thumbnail'] ?? null,
                        'images' => ! empty($data['product_images']) ? json_decode($data['product_images'], true) : [],
                        'options' => ! empty($data['product_options']) ? json_decode($data['product_options'], true) : [],
                    ]
                );

                // 2. Decode options and physical dimensions
                $variantOptions = ! empty($data['variant_options']) ? json_decode($data['variant_options'], true) : [];

                $width = isset($data['variant_width']) && trim($data['variant_width']) !== '' ? floatval($data['variant_width']) : null;
                $height = isset($data['variant_height']) && trim($data['variant_height']) !== '' ? floatval($data['variant_height']) : null;
                $depth = isset($data['variant_depth']) && trim($data['variant_depth']) !== '' ? floatval($data['variant_depth']) : null;
                $dimensionUnit = $data['variant_dimension_unit'] ?? 'cm';

                // 3. Find or create the product variant by SKU
                $product->variants()->updateOrCreate(
                    ['sku' => $data['variant_sku']],
                    [
                        'name' => $data['variant_name'],
                        'barcode' => $data['variant_barcode'] ?? null,
                        'price' => floatval($data['variant_price'] ?? 0),
                        'compare_at_price' => isset($data['variant_compare_at_price']) && trim($data['variant_compare_at_price']) !== '' ? floatval($data['variant_compare_at_price']) : null,
                        'cost_price' => isset($data['variant_cost_price']) && trim($data['variant_cost_price']) !== '' ? floatval($data['variant_cost_price']) : null,
                        'inventory_quantity' => intval($data['variant_inventory_quantity'] ?? 0),
                        'track_inventory' => filter_var($data['variant_track_inventory'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'continue_selling_out_of_stock' => filter_var($data['variant_continue_selling_out_of_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'weight' => isset($data['variant_weight']) && trim($data['variant_weight']) !== '' ? floatval($data['variant_weight']) : null,
                        'weight_unit' => $data['variant_weight_unit'] ?? 'g',
                        'width' => $width,
                        'height' => $height,
                        'depth' => $depth,
                        'dimension_unit' => $dimensionUnit,
                        'options' => $variantOptions,
                    ]
                );

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("ProcessCsvImportJob: Failed to import CSV row from '{$this->fileName}' (Error: {$e->getMessage()})", [
                    'exception' => $e,
                    'row' => $data,
                ]);
            }
        }

        // Clean up the imported file from storage disk
        Storage::delete($this->filePath);
    }

    /**
     * PHP Generator to parse CSV line-by-line.
     * Keeps memory consumption at a low constant O(1) complexity.
     */
    private function readCsvRows(string $filePath): \Generator
    {
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Read first row as headers
            $headers = fgetcsv($handle);
            if ($headers) {
                // Clean headers
                $headers = array_map(function ($header) {
                    return trim($header, " \t\n\r\0\x0B\xEF\xBB\xBF");
                }, $headers);

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) === count($headers)) {
                        yield array_combine($headers, $row);
                    }
                }
            }
            fclose($handle);
        }
    }
}
