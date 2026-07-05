<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessCsvImportJob;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fake the default storage disk during tests to avoid writing to real disk
        Storage::fake();
    }

    public function test_unauthenticated_request_to_import_returns_401(): void
    {
        $response = $this->postJson('/api/products/import');
        $response->assertStatus(401);
    }

    public function test_import_validation_fails_when_no_files_provided(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products/import', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    public function test_import_fails_when_filenames_do_not_match_pattern(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('invalid_name.csv', 100, 'text/csv');

        $response = $this->postJson('/api/products/import', [
            'files' => [$file]
        ]);

        $response->assertStatus(422)
            ->assertJsonMissingPath('queued_files')
            ->assertJsonCount(1, 'invalid_files')
            ->assertJsonPath('invalid_files.0.filename', 'invalid_name.csv');
    }

    public function test_import_successfully_queues_correct_files(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file1 = UploadedFile::fake()->create('2026_06_30_1782860289_product_1.csv', 100, 'text/csv');
        $file2 = UploadedFile::fake()->create('2026_06_30_1782860289_product_2.csv', 100, 'text/csv');

        $response = $this->postJson('/api/products/import', [
            'files' => [$file1, $file2]
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('message', 'CSV files have been successfully queued for importing.')
            ->assertJsonCount(2, 'queued_files')
            ->assertJsonCount(0, 'invalid_files');

        // Verify files were actually written to local imports directory
        Storage::assertExists('imports/2026_06_30_1782860289_product_1.csv');
        Storage::assertExists('imports/2026_06_30_1782860289_product_2.csv');

        Queue::assertPushed(ProcessCsvImportJob::class, 2);
    }

    public function test_process_csv_import_job_inserts_products_and_variants(): void
    {
        // 1. Arrange: Write mock CSV content to local fake disk
        $csvContent = 'product_category,product_name,product_slug,product_brand,product_supplier,product_description,product_summary,product_status,product_thumbnail,product_images,product_options,variant_name,variant_sku,variant_barcode,variant_price,variant_compare_at_price,variant_cost_price,variant_inventory_quantity,variant_track_inventory,variant_continue_selling_out_of_stock,variant_weight,variant_weight_unit,variant_width,variant_height,variant_depth,variant_dimension_unit,variant_options' . "\n"
            . '"Electronics","Test Pro Keyboard",test-pro-keyboard,"Logitech","Logitech Distribution","Mechanical keyboard with brown switches","Ergonomic keyboard.",active,http://example.com/thumb.jpg,"[""http://example.com/img1.jpg""]","[{""name"":""Layout"",""values"":[""ANSI"",""ISO""]}]","Test Pro Keyboard - ANSI / Brown",KBD-00001,1234567890123,99.99,119.99,49.99,10,1,0,850,g,40,15,3,cm,"{""Layout"":""ANSI""}"';

        $fileName = '2026_06_30_1782860289_product_1.csv';
        $filePath = 'imports/' . $fileName;
        Storage::put($filePath, $csvContent);

        // 2. Act: Instantiating and executing the Job manually with the file path
        $job = new ProcessCsvImportJob($filePath, $fileName);
        $job->handle();

        // 3. Assert: Verify database counts and exact field values
        $this->assertDatabaseHas('categories', [
            'name' => 'Electronics',
            'slug' => 'electronics'
        ]);

        $this->assertDatabaseHas('brands', [
            'name' => 'Logitech',
            'slug' => 'logitech'
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Logitech Distribution',
            'slug' => 'logitech-distribution'
        ]);

        $product = Product::where('slug', 'test-pro-keyboard')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->category_id);
        $this->assertNotNull($product->brand_id);
        $this->assertNotNull($product->supplier_id);
        $this->assertEquals(['http://example.com/img1.jpg'], $product->images);
        $this->assertEquals([['name' => 'Layout', 'values' => ['ANSI', 'ISO']]], $product->options);

        $this->assertDatabaseHas('variants', [
            'product_id' => $product->id,
            'sku' => 'KBD-00001',
            'price' => 99.99,
            'cost_price' => 49.99,
            'inventory_quantity' => 10,
            'options->Layout' => 'ANSI'
        ]);

        // Verify the file was cleaned up/deleted from disk upon complete
        Storage::assertMissing($filePath);
    }

    public function test_process_csv_import_job_upserts_correctly_when_slug_matches(): void
    {
        // Arrange: Create existing product in DB and write CSV to fake disk
        $existingProduct = Product::factory()->create([
            'name' => 'Old Name',
            'slug' => 'matched-slug',
            'status' => 'active'
        ]);

        $csvContent = 'product_category,product_name,product_slug,product_description,product_summary,product_status,product_thumbnail,product_images,product_options,variant_name,variant_sku,variant_barcode,variant_price,variant_compare_at_price,variant_cost_price,variant_inventory_quantity,variant_track_inventory,variant_continue_selling_out_of_stock,variant_weight,variant_weight_unit,variant_width,variant_height,variant_depth,variant_dimension_unit,variant_options' . "\n"
            . '"Electronics","New Name",matched-slug,"Updated description","Updated summary.",active,http://example.com/thumb.jpg,"[]","[]","New Name - Default",NEW-SKU-001,9876543210987,49.99,,20.00,5,1,0,300,g,10,10,10,cm,"[]"';

        $fileName = '2026_06_30_1782860289_product_2.csv';
        $filePath = 'imports/' . $fileName;
        Storage::put($filePath, $csvContent);

        // Act
        $job = new ProcessCsvImportJob($filePath, $fileName);
        $job->handle();

        // Assert: Product is updated, not duplicated
        $this->assertEquals(1, Product::count());
        $existingProduct->refresh();
        $this->assertEquals('New Name', $existingProduct->name);

        // Variant is created and associated with the existing product
        $this->assertEquals(1, Variant::count());
        $variant = Variant::first();
        $this->assertEquals($existingProduct->id, $variant->product_id);
        $this->assertEquals('NEW-SKU-001', $variant->sku);

        // Verify cleanup
        Storage::assertMissing($filePath);
    }
}
