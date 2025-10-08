<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPlatformGroupsFromCsv extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'platform-groups:import {file=new_platform_groups.csv} {--chunk=1000}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Import platform groups from CSV file';

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

      // Prepare platform group data
      $platformGroupData = $this->preparePlatformGroupData($data);

      if ($platformGroupData === null) {
        $skippedCount++;
        continue;
      }

      if ($platformGroupData !== false) {
        $batch[] = $platformGroupData;

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
   * Prepare platform group data from CSV row
   */
  private function preparePlatformGroupData(array $data): array|false|null
  {
    try {
      // Validate required fields
      if (empty($data['name'])) {
        return null;
      }

      return [
        'uuid' => !empty($data['uuid']) ? $data['uuid'] : null,
        'name' => $data['name'],
        'second_group_name' => !empty($data['second_group_name']) ? $data['second_group_name'] : null,
        'third_group_name' => !empty($data['third_group_name']) ? $data['third_group_name'] : null,
        'description' => !empty($data['description']) ? $data['description'] : null,
        'platform_name' => !empty($data['platform_name']) ? $data['platform_name'] : 'mt5',
        'initial_balance' => !empty($data['initial_balance']) ? (float) $data['initial_balance'] : 0,
        'account_stage' => !empty($data['account_stage']) ? $data['account_stage'] : 'trial',
        'account_type' => !empty($data['account_type']) ? $data['account_type'] : 'standard',
        'profit_split' => !empty($data['profit_split']) ? (float) $data['profit_split'] : 0,
        'max_drawdown' => !empty($data['max_drawdown']) ? (int) $data['max_drawdown'] : 0,
        'max_daily_drawdown' => !empty($data['max_daily_drawdown']) ? (int) $data['max_daily_drawdown'] : 0,
        'max_trading_days' => !empty($data['max_trading_days']) ? (int) $data['max_trading_days'] : 0,
        'account_leverage' => !empty($data['account_leverage']) ? (int) $data['account_leverage'] : 0,
        'prices' => !empty($data['prices']) ? (float) $data['prices'] : 0,
        'status' => $this->parseBoolean($data['status'] ?? 'true'),
        'created_at' => $this->parseTimestamp($data['created_at'] ?? null),
        'updated_at' => $this->parseTimestamp($data['updated_at'] ?? null),
        'group_type' => !empty($data['group_type']) ? $data['group_type'] : 'challenge',
        'profit_target' => !empty($data['profit_target']) ? $data['profit_target'] : null,
        'funded_group_name' => !empty($data['funded_group_name']) ? $data['funded_group_name'] : null,
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
      return true;
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
      return null;
    }

    try {
      return date('Y-m-d H:i:s', strtotime($value));
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Insert batch of platform groups
   */
  private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
  {
    try {
      DB::table('platform_groups')->insert($batch);
      $successCount += count($batch);
    } catch (\Exception $e) {
      // Try inserting one by one to identify problematic rows
      foreach ($batch as $row) {
        try {
          DB::table('platform_groups')->insert($row);
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
