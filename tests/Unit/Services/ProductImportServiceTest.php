<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessCsvImportJob;
use App\Services\ProductImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    protected ProductImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Queue::fake();
        $this->importService = new ProductImportService();
    }

    public function test_import_throws_exception_when_filename_is_invalid(): void
    {
        $file = UploadedFile::fake()->create('invalid_name.csv', 100, 'text/csv');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File name must match format: YYYY_MM_DD_timestamp_productName_number.csv');

        $this->importService->importFile($file, 'invalid_name.csv');
    }

    public function test_import_throws_exception_when_file_path_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file source or path provided for import.');

        $this->importService->importFile('non_existent_file.csv', '2026_06_30_1782860289_product_1.csv');
    }

    public function test_import_successfully_handles_uploaded_file(): void
    {
        $fileName = '2026_06_30_1782860289_product_1.csv';
        $file = UploadedFile::fake()->create($fileName, 100, 'text/csv');

        $storedPath = $this->importService->importFile($file, $fileName);

        $this->assertEquals('imports/' . $fileName, $storedPath);

        // Verify storage write and queue dispatch
        Storage::assertExists('imports/' . $fileName);
        Queue::assertPushed(ProcessCsvImportJob::class, function ($job) use ($storedPath, $fileName) {
            return $job->filePath === $storedPath && $job->fileName === $fileName && $job->connection === 'rabbitmq';
        });
    }

    public function test_import_successfully_handles_local_file_path(): void
    {
        $fileName = '2026_06_30_1782860289_product_2.csv';
        $tempFile = tempnam(sys_get_temp_dir(), 'test_cli_import_');
        file_put_contents($tempFile, 'dummy csv row content');

        $storedPath = $this->importService->importFile($tempFile, $fileName);

        $this->assertEquals('imports/' . $fileName, $storedPath);

        // Verify storage write and queue dispatch
        Storage::assertExists('imports/' . $fileName);
        $this->assertEquals('dummy csv row content', Storage::get('imports/' . $fileName));

        Queue::assertPushed(ProcessCsvImportJob::class, function ($job) use ($storedPath, $fileName) {
            return $job->filePath === $storedPath && $job->fileName === $fileName && $job->connection === 'rabbitmq';
        });

        // Clean up system temporary file
        unlink($tempFile);
    }
}
