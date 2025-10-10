<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportPeriodicTradingExportFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'periodic-trading-export:import {file} {--chunk=1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import periodic trading export from CSV file';

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

            $preparedData = $this->prepareTradingExportData($rowData);

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

    private function prepareTradingExportData(array $data): array|false|null
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
            $dupeDetected = $this->parseBoolean($data['dupe_detected'] ?? null);

            // Parse timestamp
            $dealTime = $this->parseTimestamp($data['deal_time'] ?? null);

            return [
                'id' => !empty($data['id']) && $this->isValidUuid($data['id']) ? $data['id'] : null,
                'deal_id' => !empty($data['deal_id']) ? (int) $data['deal_id'] : 0,
                'position_id' => !empty($data['position_id']) ? (int) $data['position_id'] : 0,
                'deal_type' => !empty($data['deal_type']) ? $data['deal_type'] : '',
                'profit' => !empty($data['profit']) ? (float) $data['profit'] : 0.0,
                'deal_time' => $dealTime,
                'deal_entry' => !empty($data['deal_entry']) ? $data['deal_entry'] : '',
                'deal_price' => !empty($data['deal_price']) ? (float) $data['deal_price'] : 0.0,
                'deal_symbol' => !empty($data['deal_symbol']) ? $data['deal_symbol'] : '',
                'deal_stoploss' => !empty($data['deal_stoploss']) ? (float) $data['deal_stoploss'] : null,
                'deal_volume' => !empty($data['deal_volume']) ? (float) $data['deal_volume'] : null,
                'deal_commission' => !empty($data['deal_commission']) ? (float) $data['deal_commission'] : null,
                'dupe_detected' => $dupeDetected,
                'deal_swap' => !empty($data['deal_swap']) ? (float) $data['deal_swap'] : null,
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
            DB::table('periodic_trading_export')->insert($data);
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
