<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TruncateTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:truncate {--table-names=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate one or more database tables.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tables = $this->parseTablesOption((string) $this->option('table-names'));

        if ($tables->isEmpty()) {
            $this->error('Provide at least one table via --table-names=table_one,table_two');
            return self::FAILURE;
        }

        if ($this->getLaravel()->environment('production')
            && !$this->confirm(
                sprintf(
                    'You are about to truncate the following tables in production: %s. Continue?',
                    $tables->implode(', ')
                )
            )
        ) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $this->warn("Skipping '{$table}' because it does not exist.");
                    continue;
                }

                DB::table($table)->truncate();
                $this->info("Truncated '{$table}'.");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function parseTablesOption(string $rawTables): Collection
    {
        return collect(explode(',', $rawTables))
            ->map(fn (string $table) => trim($table))
            ->filter()
            ->unique()
            ->values();
    }
}
