<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('platform_groups', function (Blueprint $table) {
      $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
      $table->string('name');
      $table->string('second_group_name')->nullable();
      $table->string('third_group_name')->nullable();
      $table->string('description')->nullable();
      $table->string('platform_name')->default('mt5');
      $table->decimal('initial_balance', 15, 2)->default(0);
      $table->text('account_stage')->default('trial');
      $table->text('account_type')->default('standard');
      $table->float('profit_split')->default(0);
      $table->integer('max_drawdown')->default(0);
      $table->integer('max_daily_drawdown')->default(0);
      $table->integer('max_trading_days')->default(0);
      $table->integer('account_leverage')->default(0);
      $table->decimal('prices', 15, 2)->default(0);
      $table->boolean('status')->default(true);
      $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->string('group_type')->default('challenge');
      $table->string('profit_target')->nullable();
      $table->string('funded_group_name')->nullable();

      // Indexes for better query performance
      $table->index('name');
      $table->index('platform_name');
      $table->index('account_stage');
      $table->index('account_type');
      $table->index('group_type');
      $table->index('status');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('platform_groups');
  }
};
