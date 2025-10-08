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
        Schema::create('platform_accounts', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_uuid')->nullable();
            $table->uuid('purchase_uuid')->nullable();
            $table->string('platform_login_id')->default('');
            $table->string('platform_name')->default('mt5');
            $table->string('remote_group_name')->default('0');
            $table->uuid('platform_group_uuid');
            $table->integer('current_phase')->default(1);
            $table->string('main_password')->default('password');
            $table->string('investor_password')->default('password');
            $table->decimal('initial_balance', 15, 2);
            $table->integer('profit_target')->default(0);
            $table->float('profit_split')->default(0);
            $table->integer('max_drawdown')->default(0);
            $table->integer('max_daily_drawdown')->default(0);
            $table->text('account_stage')->nullable();
            $table->text('account_type')->nullable();
            $table->integer('account_leverage')->default(0);
            $table->smallInteger('status')->default(1);
            $table->timestampTz('funded_at')->nullable();
            $table->smallInteger('is_kyc')->default(0);
            $table->smallInteger('is_trades_check')->default(0);
            $table->string('is_trade_agreement')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestampTz('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('action_type')->nullable();
            $table->smallInteger('funded_status')->default(0);
            $table->string('platform_user_id')->nullable();

            // Foreign keys (nullable, so we use indexes instead of constraints for now)
            // $table->foreign('user_uuid')
            //     ->references('uuid')
            //     ->on('users')
            //     ->onDelete('cascade');

            // $table->foreign('purchase_uuid')
            //     ->references('uuid')
            //     ->on('purchases')
            //     ->onDelete('cascade');

            $table->foreign('platform_group_uuid')
                ->references('uuid')
                ->on('platform_groups')
                ->onDelete('cascade');

            // Indexes for better query performance
            $table->index('user_uuid');
            $table->index('purchase_uuid');
            $table->index('platform_group_uuid');
            $table->index('platform_login_id');
            $table->index('platform_name');
            $table->index('status');
            $table->index('account_stage');
            $table->index('account_type');
            $table->index('current_phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_accounts');
    }
};
