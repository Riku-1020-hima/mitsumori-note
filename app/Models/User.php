<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** 自社(アカウント)。見積書・請求書の発行元 */
class User extends Authenticatable
{
    use Notifiable;

    public const DEFAULT_APP_NAME = 'ミツモリノート';

    public const ROUNDINGS = ['floor' => '切り捨て', 'round' => '四捨五入', 'ceil' => '切り上げ'];

    protected $fillable = [
        'name', 'postal_code', 'address', 'phone', 'invoice_number', 'bank_account',
        'rounding', 'quote_valid_days', 'payment_due_days', 'email', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'quote_valid_days' => 'integer',
            'payment_due_days' => 'integer',
        ];
    }

    public function getDisplayAppNameAttribute(): string
    {
        return self::DEFAULT_APP_NAME;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
