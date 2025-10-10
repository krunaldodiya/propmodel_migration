<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDiscountCodesFromCsv extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'discount-codes:import {file=new_discount_codes.csv} {--chunk=1000}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Import discount codes from CSV file';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $filePath = base_path($this->argument('file'));
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
    $batch = [];

    $progressBar = $this->output->createProgressBar();
    $progressBar->start();

    while (($row = fgetcsv($file)) !== false) {
      $totalRows++;

      // Map CSV row to associative array
      $data = array_combine($headers, $row);

      // Prepare discount code data
      $discountCodeData = $this->prepareDiscountCodeData($data);

      if ($discountCodeData) {
        $batch[] = $discountCodeData;

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
        ['Errors', $errorCount],
      ]
    );

    return 0;
  }

  /**
   * Prepare discount code data from CSV row
   */
  private function prepareDiscountCodeData(array $data): ?array
  {
    try {
      return [
        'uuid' => $data['uuid'] ?: null,
        'name' => $data['name'] ?: null,
        'code' => $data['code'] ?: null,
        'max_usage_count' => (int) ($data['max_usage_count'] ?: 0),
        'current_usage_count' => (int) ($data['current_usage_count'] ?: 0),
        'discount' => (float) ($data['discount'] ?: 0),
        'start_date' => $this->parseTimestamp($data['start_date']),
        'end_date' => $this->parseTimestamp($data['end_date']),
        'challenge_amount' => $this->parseJsonb($data['challenge_amount']),
        'challenge_step' => $this->parseJsonb($data['challenge_step']),
        'email' => $this->parseJsonb($data['email']),
        'created_by' => $data['created_by'] ?: null,
        'created_at' => $this->parseTimestamp($data['created_at']),
        'updated_at' => $this->parseTimestamp($data['updated_at']),
        'type' => $data['type'] ?: 'admin',
        'commission_percentage' => (float) ($data['commission_percentage'] ?: 0),
        'deleted_at' => $this->parseTimestamp($data['deleted_at']),
        'status' => $data['status'] ?: 'active',
      ];
    } catch (\Exception $e) {
      $this->error("Error preparing data: " . $e->getMessage());
      return null;
    }
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
   * Parse JSONB field from CSV
   */
  private function parseJsonb(?string $value): ?string
  {
    if (empty($value)) {
      return null;
    }

    // Unescape double quotes in CSV format
    $unescaped = str_replace('""', '"', $value);

    // If it's valid JSON after unescaping, return it
    if (json_decode($unescaped) !== null) {
      return $unescaped;
    }

    return null;
  }

  /**
   * Insert batch of discount codes
   */
  private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
  {
    try {
      DB::table('discount_codes')->insert($batch);
      $successCount += count($batch);
    } catch (\Exception $e) {
      $errorCount += count($batch);
      $this->error("Batch insert error: " . $e->getMessage());
    }
  }
}
