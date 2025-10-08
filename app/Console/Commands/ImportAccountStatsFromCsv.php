<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportAccountStatsFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account-stats:import {file} {--chunk=1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import account stats from CSV file';

    private array $existingPlatformGroupUuids = [];
    private array $existingPlatformAccountUuids = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting import from: {$filePath}");
        $this->info("Chunk size: {$chunkSize}");

        // Load existing UUIDs for validation
        $this->loadExistingUuids();

        $file = fopen($filePath, 'r');
        if (!$file) {
            $this->error("Could not open file: {$filePath}");
            return 1;
        }

        $headers = fgetcsv($file, 0, ',', '"', '"');
        if (!$headers) {
            $this->error("Could not read headers from CSV file");
            fclose($file);
            return 1;
        }

        $headerMap = array_flip($headers);
        $batch = [];
        $totalRows = 0;
        $successful = 0;
        $skipped = 0;
        $errors = 0;

        $this->info("Processing CSV data...");

        while (($row = fgetcsv($file, 0, ',', '"', '"')) !== false) {
            $totalRows++;

            $data = [];
            foreach ($headers as $header) {
                $data[$header] = $row[$headerMap[$header]] ?? null;
            }

            $preparedData = $this->prepareAccountStatsData($data);

            if ($preparedData === null) {
                $skipped++;
                continue;
            }

            $batch[] = $preparedData;

            if (count($batch) >= $chunkSize) {
                $result = $this->insertBatch($batch);
                $successful += $result['successful'];
                $errors += $result['errors'];
                $batch = [];
            }

            // Progress bar
            if ($totalRows % 1000 == 0) {
                $this->showProgress($totalRows);
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            $result = $this->insertBatch($batch);
            $successful += $result['successful'];
            $errors += $result['errors'];
        }

        fclose($file);

        $this->newLine();
        $this->info("Import completed!");
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Rows', $totalRows],
                ['Successful', $successful],
                ['Skipped (missing FK)', $skipped],
                ['Errors', $errors],
            ]
        );

        return 0;
    }

    /**
     * Load existing UUIDs for foreign key validation
     */
    private function loadExistingUuids(): void
    {
        $this->info("Loading existing UUIDs for validation...");

        // Load platform group UUIDs
        $this->existingPlatformGroupUuids = DB::table('platform_groups')
            ->pluck('uuid')
            ->flip()
            ->toArray();

        // Load platform account UUIDs
        $this->existingPlatformAccountUuids = DB::table('platform_accounts')
            ->pluck('uuid')
            ->flip()
            ->toArray();

        $this->info("Loaded " . count($this->existingPlatformGroupUuids) . " platform group UUIDs");
        $this->info("Loaded " . count($this->existingPlatformAccountUuids) . " platform account UUIDs");
    }

    /**
     * Prepare account stats data from CSV row
     */
    private function prepareAccountStatsData(array $data): array|false|null
    {
        try {
            // Validate platform_group_uuid if provided
            $platformGroupUuid = null;
            if (!empty($data['platform_group_uuid']) && $this->isValidUuid($data['platform_group_uuid'])) {
                if (isset($this->existingPlatformGroupUuids[$data['platform_group_uuid']])) {
                    $platformGroupUuid = $data['platform_group_uuid'];
                }
            }

            // Validate platform_account_uuid if provided
            $platformAccountUuid = null;
            if (!empty($data['platform_account_uuid']) && $this->isValidUuid($data['platform_account_uuid'])) {
                if (isset($this->existingPlatformAccountUuids[$data['platform_account_uuid']])) {
                    $platformAccountUuid = $data['platform_account_uuid'];
                }
            }

            return [
                'uuid' => !empty($data['uuid']) && $this->isValidUuid($data['uuid']) ? $data['uuid'] : null,
                'status' => !empty($data['status']) ? $data['status'] : null,
                'current_equity' => $this->parseFloat($data['current_equity']),
                'yesterday_equity' => $this->parseFloat($data['yesterday_equity']),
                'performance_percent' => $this->parseFloat($data['performance_percent']),
                'current_overall_drawdown' => $this->parseFloat($data['current_overall_drawdown']),
                'current_daily_drawdown' => $this->parseFloat($data['current_daily_drawdown']),
                'average_win' => $this->parseFloat($data['average_win']),
                'average_loss' => $this->parseFloat($data['average_loss']),
                'hit_ratio' => $this->parseFloat($data['hit_ratio']),
                'best_trade' => $this->parseFloat($data['best_trade']),
                'worst_trade' => $this->parseFloat($data['worst_trade']),
                'max_consecutive_wins' => $this->parseInt($data['max_consecutive_wins']),
                'max_consecutive_losses' => $this->parseInt($data['max_consecutive_losses']),
                'trades_without_stoploss' => $this->parseInt($data['trades_without_stoploss']),
                'most_traded_asset' => !empty($data['most_traded_asset']) ? $data['most_traded_asset'] : null,
                'win_coefficient' => $this->parseFloat($data['win_coefficient']),
                'avg_win_loss_coefficient' => $this->parseFloat($data['avg_win_loss_coefficient']),
                'best_worst_coefficient' => $this->parseFloat($data['best_worst_coefficient']),
                'maximum_daily_drawdown' => $this->parseFloat($data['maximum_daily_drawdown']),
                'maximum_overall_drawdown' => $this->parseFloat($data['maximum_overall_drawdown']),
                'consistency_score' => $this->parseFloat($data['consistency_score']) ?? 0.0,
                'lowest_watermark' => $this->parseFloat($data['lowest_watermark']) ?? 0.0,
                'highest_watermark' => $this->parseFloat($data['highest_watermark']) ?? 0.0,
                'current_balance' => $this->parseFloat($data['current_balance']),
                'current_profit' => $this->parseFloat($data['current_profit']),
                'platform_group_uuid' => $platformGroupUuid,
                'platform_account_uuid' => $platformAccountUuid,
                'trading_days_count' => $this->parseInt($data['trading_days_count']) ?? 0,
                'first_trade_date' => $this->parseTimestamp($data['first_trade_date']),
                'total_trade_count' => $this->parseInt($data['total_trade_count']),
            ];
        } catch (\Exception $e) {
            Log::error("Error preparing account stats data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert batch of records
     */
    private function insertBatch(array $batch): array
    {
        $successful = 0;
        $errors = 0;

        try {
            DB::table('account_stats')->insert($batch);
            $successful = count($batch);
        } catch (\Exception $e) {
            Log::error("Batch insert error: " . $e->getMessage());
            $errors = count($batch);
        }

        return ['successful' => $successful, 'errors' => $errors];
    }

    /**
     * Parse float value
     */
    private function parseFloat(?string $value): ?float
    {
        if (empty($value) || $value === '') {
            return null;
        }

        $float = (float) $value;
        return is_finite($float) ? $float : null;
    }

    /**
     * Parse integer value
     */
    private function parseInt(?string $value): ?int
    {
        if (empty($value) || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Parse timestamp
     */
    private function parseTimestamp(?string $value): ?string
    {
        if (empty($value) || $value === '') {
            return null;
        }

        try {
            $timestamp = \Carbon\Carbon::parse($value);
            return $timestamp->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate UUID format
     */
    private function isValidUuid(?string $uuid): bool
    {
        if (empty($uuid)) {
            return false;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Show progress bar
     */
    private function showProgress(int $current): void
    {
        $progress = str_repeat('▓', min(intval($current / 1000), 30));
        $remaining = str_repeat('░', max(0, 30 - intval($current / 1000)));
        $this->line("\r{$current} [{$progress}{$remaining}]", null, 'v');
    }
}
