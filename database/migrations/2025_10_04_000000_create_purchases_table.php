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
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_uuid');
            $table->decimal('amount_total', 15, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_method')->nullable();
            $table->smallInteger('payment_status')->default(0);
            $table->smallInteger('is_paid_aff_commission')->nullable();
            $table->jsonb('user_data')->nullable();
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->uuid('discount_uuid')->nullable();
            $table->boolean('already_paid')->default(false);
            $table->text('payment_transaction_id')->nullable();
            $table->text('payment_response')->nullable();
            $table->integer('payment_attempt_count')->default(0);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->text('webhook_response')->nullable();
            $table->string('purchase_type')->default('challenge');
            $table->uuid('competition_uuid')->nullable();
            $table->string('ip')->nullable();

            // Add indexes
            $table->index('user_uuid');
            $table->index('discount_uuid');
            $table->index('payment_status');
            $table->index('purchase_type');
            $table->index('created_at');
        });

        // Add foreign key constraints
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->foreign('discount_uuid')->references('uuid')->on('discount_codes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
