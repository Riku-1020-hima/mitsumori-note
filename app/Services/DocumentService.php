<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** 見積書・請求書の保存・番号の採番・見積から請求への変換 */
class DocumentService
{
    /** 次の番号(Q2026-001 / INV2026-001)。年ごとに1から数え直す */
    public function nextNumber(User $user, string $type, ?CarbonImmutable $date = null): string
    {
        $prefix = Document::PREFIXES[$type].($date ?? CarbonImmutable::now())->format('Y').'-';
        $last = $user->documents()->where('type', $type)->where('number', 'like', $prefix.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 書類と明細を保存する(明細はいったん全部消して入れ直す)
     *
     * @param  array<string, mixed>  $attrs
     * @param  list<array{name: string, quantity: float, unit: ?string, unit_price: int, tax_rate: int}>  $items
     */
    public function save(User $user, Document $doc, array $attrs, array $items): Document
    {
        return DB::transaction(function () use ($user, $doc, $attrs, $items) {
            $doc->fill($attrs);
            $doc->user_id = $user->id;
            // 作った(直した)時点の自社の情報と宛名を写しておく。あとで設定や取引先を変えても、出した書類は変わらない
            $doc->issuer = collect(Document::ISSUER_FIELDS)->mapWithKeys(fn ($f) => [$f => $user->{$f}])->all();
            $client = $doc->client_id ? $user->clients()->find($doc->client_id) : null;
            $doc->recipient = $client ? ['name' => $client->name, 'honorific' => $client->honorific, 'contact_person' => $client->contact_person] : null;
            if (! $doc->exists && ! $doc->number) {
                $doc->number = $this->nextNumber($user, $doc->type, CarbonImmutable::parse($doc->issue_date));
            }
            $doc->save();

            $doc->items()->delete();
            foreach (array_values($items) as $i => $item) {
                $doc->items()->create($item + ['position' => $i]);
            }

            return $this->refreshTotals($doc, $user);
        });
    }

    /** 一覧用の合計を計算し直して保存する */
    public function refreshTotals(Document $doc, User $user): Document
    {
        $t = Calculator::totals($doc->items()->get(), $doc->roundingFor($user));
        $doc->forceFill(['subtotal' => $t['subtotal'], 'tax' => $t['tax'], 'total' => $t['total']])->save();

        return $doc;
    }

    /** 見積書から請求書を作る(明細をそのまま写し、見積は「受注」にする) */
    public function convertToInvoice(User $user, Document $quote): Document
    {
        return DB::transaction(function () use ($user, $quote) {
            $today = CarbonImmutable::today();
            $invoice = $this->save($user, new Document(['type' => 'invoice']), [
                'type' => 'invoice',
                'client_id' => $quote->client_id,
                'subject' => $quote->subject,
                'issue_date' => $today->toDateString(),
                'due_date' => $today->addDays($user->payment_due_days ?: 30)->toDateString(),
                'transaction_date' => $today->toDateString(),
                'status' => 'unpaid',
                'notes' => $quote->notes,
                'source_id' => $quote->id,
            ], $this->copyItems($quote));

            if (in_array($quote->status, ['draft', 'sent'], true)) {
                $quote->update(['status' => 'won']);
            }

            return $invoice;
        });
    }

    /** 同じ内容で新しい書類を作る(番号と日付は新しくする) */
    public function duplicate(User $user, Document $doc): Document
    {
        $today = CarbonImmutable::today();
        $days = $doc->isQuote() ? ($user->quote_valid_days ?: 30) : ($user->payment_due_days ?: 30);

        return $this->save($user, new Document(['type' => $doc->type]), [
            'type' => $doc->type,
            'client_id' => $doc->client_id,
            'subject' => $doc->subject.'(コピー)',
            'issue_date' => $today->toDateString(),
            'due_date' => $today->addDays($days)->toDateString(),
            'transaction_date' => $doc->isQuote() ? null : $today->toDateString(),
            'status' => $doc->isQuote() ? 'draft' : 'unpaid',
            'notes' => $doc->notes,
        ], $this->copyItems($doc));
    }

    private function copyItems(Document $doc): array
    {
        return $doc->items()->get()->map(fn ($i) => [
            'name' => $i->name, 'quantity' => $i->quantity, 'unit' => $i->unit,
            'unit_price' => $i->unit_price, 'tax_rate' => $i->tax_rate,
        ])->all();
    }
}
