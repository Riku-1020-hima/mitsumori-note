<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 取引先 */
class Client extends Model
{
    public const HONORIFICS = ['御中', '様'];

    protected $fillable = ['name', 'honorific', 'contact_person', 'postal_code', 'address', 'email', 'phone', 'memo'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** 宛名(株式会社さくら 御中) */
    public function getAddresseeAttribute(): string
    {
        return $this->name.' '.$this->honorific;
    }
}
