<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 明細の1行 */
class DocumentItem extends Model
{
    public const TAX_RATES = [10 => '10%', 8 => '8%(軽減)', 0 => '非課税'];

    protected $fillable = ['position', 'name', 'quantity', 'unit', 'unit_price', 'tax_rate'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'integer', 'tax_rate' => 'integer', 'position' => 'integer'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** 金額(数量 × 単価。1円未満は四捨五入) */
    public function getAmountAttribute(): int
    {
        return (int) round($this->quantity * $this->unit_price);
    }

    /** 数量の表示(2.50 → 2.5、3.00 → 3) */
    public function getQuantityLabelAttribute(): string
    {
        return rtrim(rtrim(number_format($this->quantity, 2, '.', ','), '0'), '.');
    }
}
