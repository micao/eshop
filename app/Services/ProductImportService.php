<?php

namespace App\Services;

use App\Jobs\ProcessCsvImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ProductImportService
{
    /**
     * Import a single CSV product catalog file.
     *
     * @param UploadedFile|string $file Can be UploadedFile from request or string file path from CLI
     * @param string $originalName The basename of the file
     * @return string Stored file path in Laravel Storage
     * @throws InvalidArgumentException
     */
    public function importFile(mixed $file, string $originalName): string
    {
        // Enforce filename validation
        if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d+_\w+_\d+\.csv$/', $originalName)) {
            throw new InvalidArgumentException("File name must match format: YYYY_MM_DD_timestamp_productName_number.csv");
        }

        // Save file content to storage
        if ($file instanceof UploadedFile) {
            $storedPath = $file->storeAs('imports', $originalName);
        } elseif (is_string($file) && file_exists($file)) {
            $storedPath = 'imports/' . $originalName;
            // Write/copy file content to Storage
            Storage::put($storedPath, file_get_contents($file));
        } else {
            throw new InvalidArgumentException("Invalid file source or path provided for import.");
        }

        // Dispatch process job onto rabbitmq connection
        ProcessCsvImportJob::dispatch($storedPath, $originalName)->onConnection('rabbitmq');

        return $storedPath;
    }
}
