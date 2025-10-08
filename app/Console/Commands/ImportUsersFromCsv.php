<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportUsersFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:import {file=new_users.csv} {--chunk=1000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from CSV file';

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

            // Prepare user data
            $userData = $this->prepareUserData($data);

            if ($userData) {
                $batch[] = $userData;

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
     * Prepare user data from CSV row
     */
    private function prepareUserData(array $data): ?array
    {
        try {
            return [
                'uuid' => $data['uuid'] ?: null,
                'ref_by_user_id' => $data['ref_by_user_id'] ?: null,
                'ref_link_count' => (int) ($data['ref_link_count'] ?: 0),
                'email' => $data['email'] ?: null,
                'password' => $data['password'] ?: null,
                'first_name' => $data['first_name'] ?: null,
                'last_name' => $data['last_name'] ?: null,
                'phone' => $data['phone'] ?: null,
                'phone_verified' => (int) ($data['phone_verified'] ?: 0),
                'sent_activation_mail_count' => (int) ($data['sent_activation_mail_count'] ?: 0),
                'status' => (int) ($data['status'] ?: 0),
                'reset_pass_hash' => $data['reset_pass_hash'] ?: null,
                'address' => $data['address'] ?: null,
                'country' => $data['country'] ?: null,
                'state' => $data['state'] ?: null,
                'city' => $data['city'] ?: null,
                'zip' => $data['zip'] ?: null,
                'timezone' => $data['timezone'] ?: null,
                'google_app_secret' => $data['google_app_secret'] ?: null,
                '2fa_sms_enabled' => (int) ($data['2fa_sms_enabled'] ?: 0),
                'identity_status' => $data['identity_status'] ?: null,
                'identity_verified_at' => $this->parseTimestamp($data['identity_verified_at']),
                'affiliate_terms' => (int) ($data['affiliate_terms'] ?: 0),
                'dashboard_popup' => (int) ($data['dashboard_popup'] ?: 0),
                'discord_connected' => (int) ($data['discord_connected'] ?: 0),
                'used_free_count' => (int) ($data['used_free_count'] ?: 0),
                'available_count' => (int) ($data['available_count'] ?: 0),
                'trail_verification_status' => (int) ($data['trail_verification_status'] ?: 0),
                'last_login_at' => $this->parseTimestamp($data['last_login_at']),
                'created_at' => $this->parseTimestamp($data['created_at']),
                'updated_at' => $this->parseTimestamp($data['updated_at']),
                'is_google_app_verify' => (int) ($data['is_google_app_verify'] ?: 0),
                'dob' => $this->parseDate($data['dob']),
                'role_id' => $data['role_id'] ?: null,
                'accept_affiliate_terms' => (int) ($data['accept_affiliate_terms'] ?: 0),
                'deleted_at' => $this->parseTimestamp($data['deleted_at']),
                'ref_code' => $data['ref_code'] ?: null,
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
     * Parse date from CSV
     */
    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return date('Y-m-d', strtotime($value));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Insert batch of users
     */
    private function insertBatch(array $batch, int &$successCount, int &$errorCount): void
    {
        try {
            DB::table('users')->insert($batch);
            $successCount += count($batch);
        } catch (\Exception $e) {
            $errorCount += count($batch);
            $this->error("Batch insert error: " . $e->getMessage());
        }
    }
}
