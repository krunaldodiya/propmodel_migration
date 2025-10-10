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
        Schema::create('periodic_trading_export', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->bigInteger('deal_id');
            $table->bigInteger('position_id');
            $table->string('deal_type');
            $table->float('profit');
            $table->timestampTz('deal_time');
            $table->string('deal_entry');
            $table->float('deal_price');
            $table->string('deal_symbol');
            $table->float('deal_stoploss')->nullable();
            $table->float('deal_volume')->nullable();
            $table->float('deal_commission')->nullable();
            $table->boolean('dupe_detected');
            $table->float('deal_swap')->nullable();
            $table->uuid('platform_account_uuid')->nullable();

            // Foreign key constraint
            $table->foreign('platform_account_uuid')->references('uuid')->on('platform_accounts')->onDelete('set null');

            // Indexes
            $table->index('deal_id');
            $table->index('position_id');
            $table->index('deal_type');
            $table->index('deal_time');
            $table->index('platform_account_uuid');
            $table->index('dupe_detected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodic_trading_export');
    }
};
