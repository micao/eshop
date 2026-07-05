<?php

namespace App\Console\Commands;

use App\Services\ProductImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import {files* : The paths of the CSV files to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk import products from CSV files on the local disk by dispatching them to RabbitMQ.';

    protected ProductImportService $importService;

    /**
     * Create a new command instance.
     */
    public function __construct(ProductImportService $importService)
    {
        parent::__construct();
        $this->importService = $importService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $files = $this->argument('files');

        foreach ($files as $filePath) {
            // Check relative to base path
            $fullPath = base_path($filePath);
            
            if (!file_exists($fullPath)) {
                $fullPath = $filePath;
            }

            if (!file_exists($fullPath)) {
                $this->error("File not found: {$filePath}");
                continue;
            }

            $originalName = basename($fullPath);

            try {
                $this->info("Importing file: {$originalName}...");
                $this->importService->importFile($fullPath, $originalName);
                $this->info("Successfully queued {$originalName} for importing.");
            } catch (InvalidArgumentException $e) {
                $this->error("Failed importing {$originalName}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
