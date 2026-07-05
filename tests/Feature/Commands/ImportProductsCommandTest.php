<?php

namespace Tests\Feature\Commands;

use App\Jobs\ProcessCsvImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    public function test_command_fails_when_file_does_not_exist(): void
    {
        $this->artisan('products:import non_existent_file.csv')
            ->expectsOutputToContain('File not found: non_existent_file.csv')
            ->assertExitCode(0);
    }

    public function test_command_fails_when_filename_is_invalid(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import_');
        $badNamePath = dirname($tempFile).'/invalid_name.csv';
        file_put_contents($badNamePath, 'dummy data');

        $this->artisan("products:import {$badNamePath}")
            ->expectsOutputToContain('Failed importing invalid_name.csv: File name must match format: YYYY_MM_DD_timestamp_productName_number.csv')
            ->assertExitCode(0);

        unlink($badNamePath);
    }

    public function test_command_succeeds_and_queues_job_when_valid(): void
    {
        Queue::fake();

        $tempDir = sys_get_temp_dir();
        $validNamePath = $tempDir.'/2026_06_30_1782860289_product_1.csv';
        file_put_contents($validNamePath, 'product_name,product_slug');

        $this->artisan("products:import {$validNamePath}")
            ->expectsOutputToContain('Importing file: 2026_06_30_1782860289_product_1.csv...')
            ->expectsOutputToContain('Successfully queued 2026_06_30_1782860289_product_1.csv for importing.')
            ->assertExitCode(0);

        Queue::assertPushed(ProcessCsvImportJob::class, 1);

        Storage::assertExists('imports/2026_06_30_1782860289_product_1.csv');

        unlink($validNamePath);
    }
}
