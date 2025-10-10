<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equity_data_daily', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->date('day');
            $table->timestampTz('created_date');
            $table->float('equity');
            $table->float('balance');
            $table->float('equity_eod_mt5')->nullable();
            $table->uuid('platform_account_uuid')->nullable();

            // Foreign key constraint
            $table->foreign('platform_account_uuid')->references('uuid')->on('platform_accounts')->onDelete('set null');

            // Indexes
            $table->index('day');
            $table->index('platform_account_uuid');
            $table->index('created_date');
            $table->index(['platform_account_uuid', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equity_data_daily');
    }
};
