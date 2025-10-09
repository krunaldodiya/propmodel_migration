<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportBreachAccountActivitiesFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'breach-activities:import {file} {--chunk=1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import breach account activities from CSV file';

    private array $existingPlatformAccountUuids = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("Starting import from: {$file}");
        $this->info("Chunk size: {$chunkSize}");
        $this->info("Loading existing UUIDs for validation...");

        // Load existing UUIDs for validation
        $this->loadExistingUuids();

        $this->info("Processing CSV data...");

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error("Could not open file: {$file}");
            return 1;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            $this->error("Could not read CSV header");
            fclose($handle);
            return 1;
        }

        $data = [];
        $totalRows = 0;
        $successful = 0;
        $skipped = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            $rowData = array_combine($header, $row);

            $preparedData = $this->prepareBreachActivityData($rowData);

            if ($preparedData === null) {
                $skipped++;
                continue;
            } elseif ($preparedData === false) {
                $errors++;
                continue;
            }

            $data[] = $preparedData;
            $successful++;

            if (count($data) >= $chunkSize) {
                $this->insertChunk($data);
                $data = [];
                $this->showProgress($successful);
            }
        }

        // Insert remaining data
        if (!empty($data)) {
            $this->insertChunk($data);
        }

        fclose($handle);

        $this->newLine();
        $this->info("Import completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Rows', number_format($totalRows)],
                ['Successful', number_format($successful)],
                ['Skipped (missing FK)', number_format($skipped)],
                ['Errors', number_format($errors)],
            ]
        );

        return 0;
    }

    private function loadExistingUuids(): void
    {
        // Load existing platform account UUIDs
        $this->existingPlatformAccountUuids = DB::table('platform_accounts')
            ->pluck('uuid')
            ->flip()
            ->toArray();

        $this->info("Loaded " . count($this->existingPlatformAccountUuids) . " platform account UUIDs");
    }

    private function prepareBreachActivityData(array $data): array|false|null
    {
        try {
            // Validate platform_account_uuid if provided
            $platformAccountUuid = null;
            if (!empty($data['platform_account_uuid']) && $this->isValidUuid($data['platform_account_uuid'])) {
                if (isset($this->existingPlatformAccountUuids[$data['platform_account_uuid']])) {
                    $platformAccountUuid = $data['platform_account_uuid'];
                }
            }

            // Parse boolean values
            $isBreached = $this->parseBoolean($data['is_breached'] ?? null);

            // Parse timestamp
            $lastBreachDate = $this->parseTimestamp($data['last_breach_date'] ?? null);

            return [
                'id' => !empty($data['id']) && $this->isValidUuid($data['id']) ? $data['id'] : null,
                'breach_count' => !empty($data['breach_count']) ? (int) $data['breach_count'] : 0,
                'last_breach_date' => $lastBreachDate,
                'is_breached' => $isBreached,
                'platform_account_uuid' => $platformAccountUuid,
            ];
        } catch (\Throwable $th) {
            $this->error("Error preparing data: " . $th->getMessage());
            return false;
        }
    }

    private function insertChunk(array $data): void
    {
        try {
            DB::table('breach_account_activities')->insert($data);
        } catch (\Exception $e) {
            $this->error("Error inserting chunk: " . $e->getMessage());
        }
    }

    private function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    private function parseBoolean(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $value = strtolower(trim($value));
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    private function parseTimestamp(?string $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }

        try {
            $date = new \DateTime($timestamp);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function showProgress(int $current): void
    {
        $progress = str_repeat('▓', min(intval($current / 1000), 30));
        $remaining = str_repeat('░', max(0, 30 - intval($current / 1000)));
        $this->line("\r{$current} [{$progress}{$remaining}]", null, 'v');
    }
}
