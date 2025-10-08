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
        Schema::create('account_stats', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('status')->nullable();
            $table->float('current_equity')->nullable();
            $table->float('yesterday_equity')->nullable();
            $table->float('performance_percent')->nullable();
            $table->float('current_overall_drawdown')->nullable();
            $table->float('current_daily_drawdown')->nullable();
            $table->float('average_win')->nullable();
            $table->float('average_loss')->nullable();
            $table->float('hit_ratio')->nullable();
            $table->float('best_trade')->nullable();
            $table->float('worst_trade')->nullable();
            $table->integer('max_consecutive_wins')->nullable();
            $table->integer('max_consecutive_losses')->nullable();
            $table->integer('trades_without_stoploss')->nullable();
            $table->string('most_traded_asset')->nullable();
            $table->float('win_coefficient')->nullable();
            $table->float('avg_win_loss_coefficient')->nullable();
            $table->float('best_worst_coefficient')->nullable();
            $table->float('maximum_daily_drawdown')->nullable();
            $table->float('maximum_overall_drawdown')->nullable();
            $table->float('consistency_score');
            $table->float('lowest_watermark');
            $table->float('highest_watermark');
            $table->float('current_balance')->nullable();
            $table->float('current_profit')->nullable();
            $table->uuid('platform_group_uuid')->nullable();
            $table->uuid('platform_account_uuid')->nullable();
            $table->integer('trading_days_count')->default(0);
            $table->timestampTz('first_trade_date')->nullable();
            $table->integer('total_trade_count')->nullable();

            // Foreign key constraints
            $table->foreign('platform_group_uuid')
                ->references('uuid')
                ->on('platform_groups')
                ->onDelete('cascade');

            $table->foreign('platform_account_uuid')
                ->references('uuid')
                ->on('platform_accounts')
                ->onDelete('cascade');

            // Indexes
            $table->index('platform_group_uuid');
            $table->index('platform_account_uuid');
            $table->index('status');
            $table->index('consistency_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_stats');
    }
};
