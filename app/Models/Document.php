<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** 見積書・請求書 */
class Document extends Model
{
    public const TYPES = ['quote' => '見積書', 'invoice' => '請求書'];

    public const STATUSES = [
        'quote' => ['draft' => '下書き', 'sent' => '提出済み', 'won' => '受注', 'lost' => '失注'],
        'invoice' => ['unpaid' => '未入金', 'paid' => '入金済み', 'void' => '取消'],
    ];

    /** 番号の頭につける文字 */
    public const PREFIXES = ['quote' => 'Q', 'invoice' => 'INV'];

    /** 書類に写しておく自社の情報 */
    public const ISSUER_FIELDS = ['name', 'postal_code', 'address', 'phone', 'invoice_number', 'bank_account', 'rounding'];

    protected $fillable = ['client_id', 'type', 'number', 'subject', 'issue_date', 'due_date', 'transaction_date', 'status', 'paid_on', 'notes', 'source_id'];

    protected function casts(): array
    {
        return ['issue_date' => 'immutable_date', 'due_date' => 'immutable_date', 'paid_on' => 'immutable_date', 'transaction_date' => 'immutable_date',
            'issuer' => 'array', 'recipient' => 'array',
            'subtotal' => 'integer', 'tax' => 'integer', 'total' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->orderBy('position')->orderBy('id');
    }

    /** 元になった見積書 */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_id');
    }

    /** この見積書から作った請求書 */
    public function invoice(): HasOne
    {
        return $this->hasOne(Document::class, 'source_id')->where('type', 'invoice');
    }

    public function scopeQuotes(Builder $q): Builder
    {
        return $q->where('type', 'quote');
    }

    public function scopeInvoices(Builder $q): Builder
    {
        return $q->where('type', 'invoice');
    }

    /** 支払期限を過ぎた未入金の請求書 */
    public function scopeOverdue(Builder $q): Builder
    {
        return $q->invoices()->where('status', 'unpaid')->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
    }

    public function isQuote(): bool
    {
        return $this->type === 'quote';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->type][$this->status] ?? $this->status;
    }

    /** 期限の呼び方(見積:有効期限/請求:お支払期限) */
    public function getDueLabelAttribute(): string
    {
        return $this->isQuote() ? '有効期限' : 'お支払期限';
    }

    /** 入金済み・取消の請求書は直せない(お客さんに送った内容と食い違わないように) */
    public function isLocked(): bool
    {
        return ! $this->isQuote() && in_array($this->status, ['paid', 'void'], true);
    }

    /** 直せない理由 */
    public function getLockReasonAttribute(): string
    {
        return $this->isVoid()
            ? '取り消した請求書は直せません。必要なら「コピーして新しく作る」から作り直してください。'
            : '入金済みの請求書は直せません。直す必要があるときは、「未入金に戻す」→「この請求書を取り消す」→「コピーして新しく作る」の順で作り直してください。';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /** 書類に印字する自社の情報(作ったときに写したもの。古い書類は今の設定で補う) */
    public function issuerFor(User $company): array
    {
        $now = collect(self::ISSUER_FIELDS)->mapWithKeys(fn ($f) => [$f => $company->{$f}])->all();

        // 写しがあればそれを優先(空だった項目も「空」のまま)。足りない項目だけ今の設定で補う
        return $this->issuer ? $this->issuer + $now : $now;
    }

    /** 宛名(作ったときに写したもの。なければ今の取引先) */
    public function getRecipientInfoAttribute(): ?array
    {
        if ($this->recipient) {
            return $this->recipient;
        }

        return $this->client ? ['name' => $this->client->name, 'honorific' => $this->client->honorific, 'contact_person' => $this->client->contact_person] : null;
    }

    /** 消費税の端数処理(作ったときの設定) */
    public function roundingFor(User $company): string
    {
        $r = $this->issuer['rounding'] ?? $company->rounding;

        return array_key_exists($r, User::ROUNDINGS) ? $r : 'floor';
    }

    /** 取引年月日(空なら発行日) */
    public function getTransactionDayAttribute(): \Carbon\CarbonImmutable
    {
        return $this->transaction_date ?? $this->issue_date;
    }

    public function isOverdue(): bool
    {
        return ! $this->isQuote() && $this->status === 'unpaid' && $this->due_date && $this->due_date->lt(now()->startOfDay());
    }

    /** ルート名の頭(quotes / invoices) */
    public function getRouteBaseAttribute(): string
    {
        return $this->isQuote() ? 'quotes' : 'invoices';
    }

    /** PDFのファイル名(見積書_Q2026-001_株式会社さくら.pdf) */
    public function getPdfFilenameAttribute(): string
    {
        $client = $this->client?->name ? '_'.$this->client->name : '';

        return preg_replace('/[\\\\\/:*?"<>|]/u', '', $this->type_label.'_'.$this->number.$client).'.pdf';
    }
}
