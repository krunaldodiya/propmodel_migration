<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('default_challenge_settings', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->boolean('100_profit_split')->default(false);
            $table->boolean('2_percent_lower_target')->default(false);
            $table->boolean('2_percent_more_daily_drawdown')->default(false);
            $table->boolean('2_percent_more_max_drawdown')->default(false);
            $table->boolean('allow_expert_advisors')->default(false);
            $table->string('breach_type')->nullable();
            $table->boolean('close_all_positions_on_friday')->default(false);
            $table->integer('delete_account_after_failure')->nullable();
            $table->string('delete_account_after_failure_unit')->nullable();
            $table->boolean('double_leverage')->default(false);
            $table->boolean('held_over_the_weekend')->default(false);
            $table->integer('inactivity_breach_trigger')->nullable();
            $table->string('inactivity_breach_trigger_unit')->nullable();
            $table->integer('max_open_lots')->nullable();
            $table->integer('max_risk_per_symbol')->nullable();
            $table->integer('max_time_per_evaluation_phase')->nullable();
            $table->string('max_time_per_evaluation_phase_unit')->nullable();
            $table->integer('max_time_per_funded_phase')->nullable();
            $table->string('max_time_per_funded_phase_unit')->nullable();
            $table->integer('max_trading_days')->nullable();
            $table->integer('min_time_per_phase')->nullable();
            $table->string('min_time_per_phase_unit')->nullable();
            $table->boolean('no_sl_required')->default(false);
            $table->boolean('requires_stop_loss')->default(false);
            $table->boolean('requires_take_profit')->default(false);
            $table->integer('time_between_withdrawals')->nullable();
            $table->string('time_between_withdrawals_unit')->nullable();
            $table->boolean('visible_on_leaderboard')->default(false);
            $table->integer('withdraw_within')->nullable();
            $table->string('withdraw_within_unit')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->integer('account_leverage')->default(0);
            $table->integer('profit_split')->default(0);
            $table->integer('profit_target')->default(0);
            $table->integer('max_drawdown')->default(0);
            $table->integer('max_daily_drawdown')->default(0);
            $table->integer('min_trading_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_challenge_settings');
    }
};
