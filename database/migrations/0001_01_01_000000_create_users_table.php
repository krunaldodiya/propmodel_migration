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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('ref_by_user_id')->nullable();
            $table->integer('ref_link_count')->default(0);
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->smallInteger('phone_verified')->default(0);
            $table->integer('sent_activation_mail_count')->default(0);
            $table->smallInteger('status')->default(0);
            $table->string('reset_pass_hash')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('timezone')->nullable();
            $table->string('google_app_secret')->nullable();
            $table->smallInteger('2fa_sms_enabled')->default(0);
            $table->string('identity_status')->nullable();
            $table->timestampTz('identity_verified_at')->nullable();
            $table->smallInteger('affiliate_terms')->default(0);
            $table->smallInteger('dashboard_popup')->default(0);
            $table->smallInteger('discord_connected')->default(0);
            $table->integer('used_free_count')->default(0);
            $table->integer('available_count')->default(0);
            $table->smallInteger('trail_verification_status')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('is_google_app_verify')->default(0);
            $table->date('dob')->nullable();
            $table->uuid('role_id')->nullable();
            $table->smallInteger('accept_affiliate_terms')->default(0);
            $table->timestampTz('deleted_at')->nullable();
            $table->string('ref_code')->nullable();

            // Add index for ref_by_user_id
            $table->index('ref_by_user_id');
        });

        // Add self-referencing foreign key after table creation
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('ref_by_user_id')->references('uuid')->on('users')->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            // Foreign key constraint
            $table->foreign('user_id')->references('uuid')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
