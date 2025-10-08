<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasUuids, SoftDeletes;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ref_by_user_id',
        'ref_link_count',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'phone_verified',
        'sent_activation_mail_count',
        'status',
        'reset_pass_hash',
        'address',
        'country',
        'state',
        'city',
        'zip',
        'timezone',
        'google_app_secret',
        '2fa_sms_enabled',
        'identity_status',
        'identity_verified_at',
        'affiliate_terms',
        'dashboard_popup',
        'discord_connected',
        'used_free_count',
        'available_count',
        'trail_verification_status',
        'last_login_at',
        'is_google_app_verify',
        'dob',
        'role_id',
        'accept_affiliate_terms',
        'ref_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_app_secret',
        'reset_pass_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identity_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'dob' => 'date',
            'password' => 'hashed',
            'phone_verified' => 'integer',
            'status' => 'integer',
            '2fa_sms_enabled' => 'integer',
            'affiliate_terms' => 'integer',
            'dashboard_popup' => 'integer',
            'discord_connected' => 'integer',
            'trail_verification_status' => 'integer',
            'is_google_app_verify' => 'integer',
            'accept_affiliate_terms' => 'integer',
            'ref_link_count' => 'integer',
            'sent_activation_mail_count' => 'integer',
            'used_free_count' => 'integer',
            'available_count' => 'integer',
        ];
    }

    /**
     * Get the user who referred this user.
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'ref_by_user_id', 'uuid');
    }

    /**
     * Get all users referred by this user.
     */
    public function referrals()
    {
        return $this->hasMany(User::class, 'ref_by_user_id', 'uuid');
    }
}
