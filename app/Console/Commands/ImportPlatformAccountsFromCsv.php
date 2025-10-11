<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPlatformAccountsFromCsv extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'platform-accounts:import {file=new_platform_accounts.csv} {--chunk=1000}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Import platform accounts from CSV file';

  /**
   * Cache of existing UUIDs for validation
   */
  private array $existingUserUuids = [];
  private array $existingPurchaseUuids = [];
  private array $existingPlatformGroupUuids = [];

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

    // Load existing foreign key UUIDs for validation
    $this->info("Loading existing UUIDs for validation...");
    $this->loadExistingUuids();

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

      // Prepare platform account data
      $accountData = $this->preparePlatformAccountData($data);

      if ($accountData === null) {
        $skippedCount++;
        continue;
      }

      if ($accountData !== false) {
        $batch[] = $accountData;

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
        ['Skipped (missing FK)', $skippedCount],
        ['Errors', $errorCount],
      ]
    );

    return 0;
  }

  /**
   * Load existing UUIDs from related tables for validation
   */
  private function loadExistingUuids(): void
  {
    // Load user UUIDs
    $users = DB::table('users')->pluck('uuid');
    foreach ($users as $uuid) {
      $this->existingUserUuids[$uuid] = true;
    }
    $this->info("Loaded " . count($this->existingUserUuids) . " user UUIDs");

    // Load purchase UUIDs
    $purchases = DB::table('purchases')->pluck('uuid');
    foreach ($purchases as $uuid) {
      $this->existingPurchaseUuids[$uuid] = true;
    }
    $this->info("Loaded " . count($this->existingPurchaseUuids) . " purchase UUIDs");

    // Load platform group UUIDs
    $platformGroups = DB::table('platform_groups')->pluck('uuid');
    foreach ($platformGroups as $uuid) {
      $this->existingPlatformGroupUuids[$uuid] = true;
    }
    $this->info("Loaded " . count($this->existingPlatformGroupUuids) . " platform group UUIDs");
  }

  /**
   * Prepare platform account data from CSV row
   */
  private function preparePlatformAccountData(array $data): array|false|null
  {
    try {
      // Skip if platform_group_uuid is missing (required)
      if (empty($data['platform_group_uuid'])) {
        return null;
      }

      // Validate platform_group_uuid format and existence
      if (empty($data['platform_group_uuid'])) {
        return null; // Skip if platform_group_uuid is empty (required field)
      }

      if (!$this->isValidUuid($data['platform_group_uuid'])) {
        return null;
      }

      // Validate that platform group exists in the database
      if (!isset($this->existingPlatformGroupUuids[$data['platform_group_uuid']])) {
        return null; // Platform group doesn't exist
      }

      // Validate user_uuid if provided
      $userUuid = null;
      if (!empty($data['user_uuid']) && $this->isValidUuid($data['user_uuid'])) {
        if (isset($this->existingUserUuids[$data['user_uuid']])) {
          $userUuid = $data['user_uuid'];
        }
      }

      // Validate purchase_uuid if provided
      $purchaseUuid = null;
      if (!empty($data['purchase_uuid']) && $this->isValidUuid($data['purchase_uuid'])) {
        if (isset($this->existingPurchaseUuids[$data['purchase_uuid']])) {
          $purchaseUuid = $data['purchase_uuid'];
        }
      }

      return [
        'uuid' => !empty($data['uuid']) && $this->isValidUuid($data['uuid']) ? $data['uuid'] : null,
        'user_uuid' => $userUuid,
        'purchase_uuid' => $purchaseUuid,
        'platform_login_id' => $data['platform_login_id'] ?? '',
        'platform_name' => !empty($data['platform_name']) ? $data['platform_name'] : 'mt5',
        'remote_group_name' => !empty($data['remote_group_name']) ? $data['remote_group_name'] : '0',
        'platform_group_uuid' => $data['platform_group_uuid'],
        'current_phase' => isset($data['current_phase']) && $data['current_phase'] !== '' ? (int) $data['current_phase'] : 1,
        'main_password' => !empty($data['main_password']) ? $data['main_password'] : 'password',
        'investor_password' => !empty($data['investor_password']) ? $data['investor_password'] : 'password',
        'initial_balance' => !empty($data['initial_balance']) ? (float) $data['initial_balance'] : 0,
        'profit_target' => !empty($data['profit_target']) ? (int) $data['profit_target'] : 0,
        'profit_split' => !empty($data['profit_split']) ? (float) $data['profit_split'] : 0,
        'max_drawdown' => !empty($data['max_drawdown']) ? (int) $data['max_drawdown'] : 0,
        'max_daily_drawdown' => !empty($data['max_daily_drawdown']) ? (int) $data['max_daily_drawdown'] : 0,
        'account_stage' => !empty($data['account_stage']) ? $data['account_stage'] : null,
        'account_type' => !empty($data['account_type']) ? $data['account_type'] : null,
        'account_leverage' => !empty($data['account_leverage']) ? (int) $data['account_leverage'] : 0,
        'status' => !empty($data['status']) ? (int) $data['status'] : 1,
        'funded_at' => $this->parseTimestamp($data['funded_at'] ?? null),
        'is_kyc' => !empty($data['is_kyc']) ? (int) $data['is_kyc'] : 0,
        'is_trades_check' => !empty($data['is_trades_check']) ? (int) $data['is_trades_check'] : 0,
        'is_trade_agreement' => !empty($data['is_trade_agreement']) ? $data['is_trade_agreement'] : null,
        'reason' => !empty($data['reason']) ? $data['reason'] : null,
        'deleted_at' => $this->parseTimestamp($data['deleted_at'] ?? null),
        'created_at' => $this->parseTimestamp($data['created_at'] ?? null),
        'updated_at' => $this->parseTimestamp($data['updated_at'] ?? null),
        'action_type' => !empty($data['action_type']) ? $data['action_type'] : null,
        'funded_status' => !empty($data['funded_status']) ? (int) $data['funded_status'] : 0,
        'platform_user_id' => !empty($data['platform_user_id']) ? $data['platform_user_id'] : null,
      ];
    } catch (\Exception $e) {
      $this->error("Error preparing data: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Check if a string is a valid UUID
   */
  private function isValidUuid(string $uuid): bool
  {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
  }

  /**
   * Parse timestamp from CSV
   */
  private function parseTimestamp(?string $value): ?string
  {
    if (empty($value)) {
      return null;
    }

    try {
      return date('Y-m-d H:i:s', strtotime($value));
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Insert batch of platform accounts
   */
  private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
  {
    try {
      DB::table('platform_accounts')->insert($batch);
      $successCount += count($batch);
    } catch (\Exception $e) {
      // Try inserting one by one to identify problematic rows
      foreach ($batch as $row) {
        try {
          DB::table('platform_accounts')->insert($row);
          $successCount++;
        } catch (\Exception $rowError) {
          $errorCount++;
          if ($errorCount <= 5) {
            $this->error("Row error (login_id: {$row['platform_login_id']}): " . $rowError->getMessage());
          }
        }
      }
    }
  }
}
