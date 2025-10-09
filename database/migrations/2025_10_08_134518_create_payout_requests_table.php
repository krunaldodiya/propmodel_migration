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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_uuid');
            $table->text('type')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('method');
            $table->smallInteger('status')->default(0);
            $table->jsonb('data')->nullable();
            $table->timestampsTz();
            $table->uuid('platform_account_uuid')->nullable();
            $table->string('payout_id')->nullable();
            $table->text('note')->nullable();
            $table->uuid('note_created_by')->nullable();

            // Foreign key constraints
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->foreign('platform_account_uuid')->references('uuid')->on('platform_accounts')->onDelete('set null');
            $table->foreign('note_created_by')->references('uuid')->on('users')->onDelete('set null');

            // Indexes
            $table->index('user_uuid');
            $table->index('status');
            $table->index('platform_account_uuid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
