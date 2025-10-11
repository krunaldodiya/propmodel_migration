<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAdvancedChallengeSettingsFromCsv extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'advanced-challenge-settings:import {file=new_advanced_challenge_settings.csv} {--chunk=1000}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Import advanced challenge settings from CSV file';

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

    // Load existing UUIDs for validation
    $this->info("Loading existing UUIDs for validation...");
    $platformGroupUuids = DB::table('platform_groups')->pluck('uuid')->toArray();
    $platformAccountUuids = DB::table('platform_accounts')->pluck('uuid')->toArray();
    $this->info("Loaded " . count($platformGroupUuids) . " platform group UUIDs and " . count($platformAccountUuids) . " platform account UUIDs");

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

      // Prepare advanced challenge settings data
      $settingsData = $this->prepareAdvancedChallengeSettingsData($data, $platformGroupUuids, $platformAccountUuids);

      if ($settingsData === null) {
        $skippedCount++;
        continue;
      }

      if ($settingsData !== false) {
        $batch[] = $settingsData;

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
   * Prepare advanced challenge settings data from CSV row
   */
  private function prepareAdvancedChallengeSettingsData(array $data, array $platformGroupUuids, array $platformAccountUuids): array|false|null
  {
    try {
      return [
        'uuid' => !empty($data['uuid']) ? $data['uuid'] : null,
        '100_profit_split' => $this->parseBoolean($data['100_profit_split'] ?? 'false'),
        '2_percent_lower_target' => $this->parseBoolean($data['2_percent_lower_target'] ?? 'false'),
        '2_percent_more_daily_drawdown' => $this->parseBoolean($data['2_percent_more_daily_drawdown'] ?? 'false'),
        '2_percent_more_max_drawdown' => $this->parseBoolean($data['2_percent_more_max_drawdown'] ?? 'false'),
        'allow_expert_advisors' => $this->parseBoolean($data['allow_expert_advisors'] ?? 'false'),
        'breach_type' => !empty($data['breach_type']) ? $data['breach_type'] : null,
        'close_all_positions_on_friday' => $this->parseBoolean($data['close_all_positions_on_friday'] ?? 'false'),
        'delete_account_after_failure' => !empty($data['delete_account_after_failure']) ? (int) $data['delete_account_after_failure'] : null,
        'delete_account_after_failure_unit' => !empty($data['delete_account_after_failure_unit']) ? $data['delete_account_after_failure_unit'] : null,
        'double_leverage' => $this->parseBoolean($data['double_leverage'] ?? 'false'),
        'held_over_the_weekend' => $this->parseBoolean($data['held_over_the_weekend'] ?? 'false'),
        'inactivity_breach_trigger' => !empty($data['inactivity_breach_trigger']) ? (int) $data['inactivity_breach_trigger'] : null,
        'inactivity_breach_trigger_unit' => !empty($data['inactivity_breach_trigger_unit']) ? $data['inactivity_breach_trigger_unit'] : null,
        'max_open_lots' => !empty($data['max_open_lots']) ? (int) $data['max_open_lots'] : null,
        'max_risk_per_symbol' => !empty($data['max_risk_per_symbol']) ? (int) $data['max_risk_per_symbol'] : null,
        'max_time_per_evaluation_phase' => !empty($data['max_time_per_evaluation_phase']) ? (int) $data['max_time_per_evaluation_phase'] : null,
        'max_time_per_evaluation_phase_unit' => !empty($data['max_time_per_evaluation_phase_unit']) ? $data['max_time_per_evaluation_phase_unit'] : null,
        'max_time_per_funded_phase' => !empty($data['max_time_per_funded_phase']) ? (int) $data['max_time_per_funded_phase'] : null,
        'max_time_per_funded_phase_unit' => !empty($data['max_time_per_funded_phase_unit']) ? $data['max_time_per_funded_phase_unit'] : null,
        'max_trading_days' => !empty($data['max_trading_days']) ? (int) $data['max_trading_days'] : null,
        'min_time_per_phase' => !empty($data['min_time_per_phase']) ? (int) $data['min_time_per_phase'] : null,
        'min_time_per_phase_unit' => !empty($data['min_time_per_phase_unit']) ? $data['min_time_per_phase_unit'] : null,
        'no_sl_required' => $this->parseBoolean($data['no_sl_required'] ?? 'false'),
        'requires_stop_loss' => $this->parseBoolean($data['requires_stop_loss'] ?? 'false'),
        'requires_take_profit' => $this->parseBoolean($data['requires_take_profit'] ?? 'false'),
        'time_between_withdrawals' => !empty($data['time_between_withdrawals']) ? (int) $data['time_between_withdrawals'] : null,
        'time_between_withdrawals_unit' => !empty($data['time_between_withdrawals_unit']) ? $data['time_between_withdrawals_unit'] : null,
        'visible_on_leaderboard' => $this->parseBoolean($data['visible_on_leaderboard'] ?? 'false'),
        'withdraw_within' => !empty($data['withdraw_within']) ? (int) $data['withdraw_within'] : null,
        'withdraw_within_unit' => !empty($data['withdraw_within_unit']) ? $data['withdraw_within_unit'] : null,
        'platform_group_uuid' => $this->validateUuid($data['platform_group_uuid'] ?? null, $platformGroupUuids),
        'platform_account_uuid' => $this->validateUuid($data['platform_account_uuid'] ?? null, $platformAccountUuids),
        'created_at' => $this->parseTimestamp($data['created_at'] ?? null),
        'updated_at' => $this->parseTimestamp($data['updated_at'] ?? null),
        'account_leverage' => !empty($data['account_leverage']) ? (int) $data['account_leverage'] : 0,
        'profit_split' => !empty($data['profit_split']) ? (int) $data['profit_split'] : 0,
        'profit_target' => !empty($data['profit_target']) ? $data['profit_target'] : null,
        'max_drawdown' => !empty($data['max_drawdown']) ? (int) $data['max_drawdown'] : 0,
        'max_daily_drawdown' => !empty($data['max_daily_drawdown']) ? (int) $data['max_daily_drawdown'] : 0,
        'min_trading_days' => !empty($data['min_trading_days']) ? (int) $data['min_trading_days'] : null,
      ];
    } catch (\Exception $e) {
      $this->error("Error preparing data: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Parse boolean value from CSV
   */
  private function parseBoolean(?string $value): bool
  {
    if (empty($value)) {
      return false;
    }

    $value = strtolower(trim($value));
    return in_array($value, ['true', '1', 'yes', 'active', 't']);
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
   * Validate UUID against existing UUIDs
   */
  private function validateUuid(?string $uuid, array $existingUuids): ?string
  {
    if (empty($uuid)) {
      return null;
    }

    return in_array($uuid, $existingUuids) ? $uuid : null;
  }

  /**
   * Insert batch of advanced challenge settings
   */
  private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
  {
    try {
      DB::table('advanced_challenge_settings')->insert($batch);
      $successCount += count($batch);
    } catch (\Exception $e) {
      // Try inserting one by one to identify problematic rows
      foreach ($batch as $row) {
        try {
          DB::table('advanced_challenge_settings')->insert($row);
          $successCount++;
        } catch (\Exception $rowError) {
          $errorCount++;
          if ($errorCount <= 5) {
            $this->error("Row error (uuid: {$row['uuid']}): " . $rowError->getMessage());
          }
        }
      }
    }
  }
}
