<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRolesFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'roles:import {file=new_roles.csv} {--chunk=1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import roles from CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        // Check if it's an absolute path
        if (!file_exists($filePath)) {
            $filePath = base_path($filePath);
        }

        $chunkSize = (int) $this->option('chunk');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting import from: {$filePath}");
        $this->info("Chunk size: {$chunkSize}");

        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file);

        if ($headers === false) {
            $this->error('Failed to read CSV headers');
            fclose($file);
            return 1;
        }

        $totalRows = 0;
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;
        $batch = [];

        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        while (($row = fgetcsv($file)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $totalRows++;

            // Map CSV row to associative array
            $data = array_combine($headers, $row);

            // Prepare role data
            $roleData = $this->prepareRoleData($data);

            if ($roleData === null) {
                $skippedCount++;
                continue;
            }

            if ($roleData !== false) {
                $batch[] = $roleData;

                // Insert in chunks
                if (count($batch) >= $chunkSize) {
                    $this->insertBatch($batch, $successCount, $errorCount);
                    $batch = [];
                    $progressBar->advance($chunkSize);
                }
            } else {
                $errorCount++;
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            $this->insertBatch($batch, $successCount, $errorCount);
            $progressBar->advance(count($batch));
        }

        $progressBar->finish();
        $this->newLine(2);

        fclose($file);

        $this->info("Import completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Rows', $totalRows],
                ['Successful', $successCount],
                ['Skipped', $skippedCount],
                ['Errors', $errorCount],
            ]
        );

        return 0;
    }

    /**
     * Prepare role data from CSV row
     */
    private function prepareRoleData(array $data): array|false|null
    {
        try {
            // Validate required fields
            if (empty($data['name'])) {
                return null;
            }

            return [
                'uuid' => !empty($data['uuid']) ? $data['uuid'] : null,
                'name' => $data['name'],
                'description' => !empty($data['description']) ? $data['description'] : null,
                'created_at' => $this->parseTimestamp($data['created_at'] ?? null),
                'updated_at' => $this->parseTimestamp($data['updated_at'] ?? null),
            ];
        } catch (\Exception $e) {
            $this->error("Error preparing data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse timestamp from CSV
     */
    private function parseTimestamp(?string $value): ?string
    {
        if (empty($value)) {
            return now()->format('Y-m-d H:i:s');
        }

        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return now()->format('Y-m-d H:i:s');
        }
    }

    /**
     * Insert batch of roles
     */
    private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
    {
        try {
            DB::table('roles')->insert($batch);
            $successCount += count($batch);
        } catch (\Exception $e) {
            // Try inserting one by one to identify problematic rows
            foreach ($batch as $row) {
                try {
                    DB::table('roles')->insert($row);
                    $successCount++;
                } catch (\Exception $rowError) {
                    $errorCount++;
                    if ($errorCount <= 5) {
                        $this->error("Row error (name: {$row['name']}): " . $rowError->getMessage());
                    }
                }
            }
        }
    }
}
