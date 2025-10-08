<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPurchasesFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchases:import {file=purchases.csv} {--chunk=1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import purchases from CSV file';

    /**
     * Email to UUID mapping cache
     */
    private array $emailToUuid = [];

    /**
     * Discount code to UUID mapping cache
     */
    private array $discountCodeToUuid = [];

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

        // Load email to UUID mapping
        $this->info("Loading user mappings by email...");
        $this->loadUserMappings();

        // Load discount code to UUID mapping
        $this->info("Loading discount code mappings...");
        $this->loadDiscountMappings();

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
            $totalRows++;

            // Map CSV row to associative array
            $data = array_combine($headers, $row);

            // Prepare purchase data
            $purchaseData = $this->preparePurchaseData($data);

            if ($purchaseData === null) {
                $skippedCount++;
                continue;
            }

            if ($purchaseData !== false) {
                $batch[] = $purchaseData;

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
                ['Skipped (missing user)', $skippedCount],
                ['Errors', $errorCount],
            ]
        );

        return 0;
    }

    /**
     * Load email to UUID mappings
     */
    private function loadUserMappings(): void
    {
        // Map users by email since we don't have old IDs
        $users = DB::table('users')
            ->whereNotNull('email')
            ->select('uuid', 'email')
            ->get();

        foreach ($users as $user) {
            $this->emailToUuid[strtolower(trim($user->email))] = $user->uuid;
        }

        $this->info("Loaded " . count($this->emailToUuid) . " user mappings");
    }

    /**
     * Load discount code to UUID mappings
     */
    private function loadDiscountMappings(): void
    {
        // Map by discount code since we don't have old IDs
        $discounts = DB::table('discount_codes')
            ->select('uuid', 'code')
            ->get();

        foreach ($discounts as $discount) {
            $this->discountCodeToUuid[strtolower(trim($discount->code))] = $discount->uuid;
        }

        $this->info("Loaded " . count($this->discountCodeToUuid) . " discount code mappings");
    }

    /**
     * Prepare purchase data from CSV row
     */
    private function preparePurchaseData(array $data): array|false|null
    {
        try {
            // Use user_uuid directly from CSV if provided
            $userUuid = !empty($data['user_uuid']) ? $data['user_uuid'] : null;

            // Skip if user not found
            if (!$userUuid) {
                return null;
            }

            // Get user_data for JSON conversion
            $userDataRaw = $data['user_data'] ?? '';

            // Get discount UUID from discount_code
            $discountCode = $data['discount_code'] ?? '';
            $discountUuid = null;
            if (!empty($discountCode)) {
                $codeKey = strtolower(trim($discountCode));
                $discountUuid = $this->discountCodeToUuid[$codeKey] ?? null;
            }

            // Convert PHP serialized data to JSON
            $userData = $this->convertSerializedToJson($userDataRaw);

            return [
                'uuid' => !empty($data['uuid']) ? $data['uuid'] : null,
                'user_uuid' => $userUuid,
                'amount_total' => !empty($data['amount_total']) ? (float) $data['amount_total'] : 0,
                'currency' => !empty($data['currency']) ? $data['currency'] : 'USD',
                'payment_method' => !empty($data['payment_method']) ? $data['payment_method'] : null,
                'payment_status' => !empty($data['payment_status']) ? (int) $data['payment_status'] : 0,
                'is_paid_aff_commission' => !empty($data['is_paid_aff_commission']) ? (int) $data['is_paid_aff_commission'] : null,
                'user_data' => $userData,
                'original_amount' => !empty($data['original_amount']) ? (float) $data['original_amount'] : null,
                'discount_uuid' => $discountUuid,
                'already_paid' => !empty($data['already_paid']) ? (bool) $data['already_paid'] : false,
                'payment_transaction_id' => !empty($data['payment_transaction_id']) ? $data['payment_transaction_id'] : null,
                'payment_response' => !empty($data['payment_response']) ? $data['payment_response'] : null,
                'payment_attempt_count' => !empty($data['payment_attempt_count']) ? (int) $data['payment_attempt_count'] : 0,
                'created_at' => $this->parseTimestamp($data['created_at'] ?? null),
                'updated_at' => $this->parseTimestamp($data['created_at'] ?? null),
                'webhook_response' => null,
                'purchase_type' => 'challenge',
                'competition_uuid' => null,
                'ip' => null,
            ];
        } catch (\Exception $e) {
            $this->error("Error preparing data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract email from PHP serialized user_data
     */
    private function extractEmailFromUserData(?string $serialized): ?string
    {
        if (empty($serialized)) {
            return null;
        }

        try {
            // Try to unserialize PHP data
            $data = @unserialize($serialized);

            if ($data === false || !is_array($data)) {
                return null;
            }

            // Extract email from the unserialized data
            return $data['email'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert PHP serialized data to JSON
     */
    private function convertSerializedToJson(?string $serialized): ?string
    {
        if (empty($serialized)) {
            return null;
        }

        try {
            // Try to unserialize PHP data
            $data = @unserialize($serialized);

            if ($data === false && $serialized !== 'b:0;') {
                return null;
            }

            // Convert to JSON
            return json_encode($data);
        } catch (\Exception $e) {
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
     * Insert batch of purchases
     */
    private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
    {
        try {
            DB::table('purchases')->insert($batch);
            $successCount += count($batch);
        } catch (\Exception $e) {
            // Try inserting one by one to identify problematic rows
            foreach ($batch as $row) {
                try {
                    DB::table('purchases')->insert($row);
                    $successCount++;
                } catch (\Exception $rowError) {
                    $errorCount++;
                    if ($errorCount <= 5) {
                        $this->error("Row error (user_uuid: {$row['user_uuid']}): " . $rowError->getMessage());
                    }
                }
            }
        }
    }
}
