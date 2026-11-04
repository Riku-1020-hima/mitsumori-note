<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentItem;
use App\Services\Calculator;
use App\Services\DocumentService;
use App\Services\PdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * 見積書・請求書の共通の処理
 * (QuoteController と InvoiceController が TYPE を決めて、これを使う)
 */
abstract class DocumentController extends Controller
{
    /** quote / invoice */
    protected const TYPE = '';

    /** 入力欄として出す明細の行数(空の行は保存しない) */
    protected const MIN_ROWS = 6;

    public function __construct(protected DocumentService $service) {}

    protected function routeBase(): string
    {
        return static::TYPE === 'quote' ? 'quotes' : 'invoices';
    }

    protected function findDoc(Request $request, string $id): Document
    {
        return $request->user()->documents()->where('type', static::TYPE)->with('client')->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = array_key_exists($request->query('status'), Document::STATUSES[static::TYPE]) ? $request->query('status') : null;
        $overdue = static::TYPE === 'invoice' && $request->query('status') === 'overdue';
        $q = trim((string) $request->query('q'));

        $docs = $user->documents()->where('type', static::TYPE)->with('client')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($overdue, fn ($query) => $query->overdue())
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('subject', 'like', "%{$q}%")
                ->orWhere('number', 'like', "%{$q}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $counts = $user->documents()->where('type', static::TYPE)->selectRaw('status, count(*) as n, sum(total) as amount')
            ->groupBy('status')->get()->keyBy('status');

        return view('documents.index', [
            'type' => static::TYPE,
            'base' => $this->routeBase(),
            'docs' => $docs,
            'status' => $overdue ? 'overdue' : $status,
            'q' => $q,
            'counts' => $counts,
            'overdueCount' => static::TYPE === 'invoice' ? $user->documents()->overdue()->count() : 0,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $days = static::TYPE === 'quote' ? ($user->quote_valid_days ?: 30) : ($user->payment_due_days ?: 30);
        $doc = new Document([
            'type' => static::TYPE,
            'client_id' => $request->integer('client_id') ?: null,
            'issue_date' => $today,
            'due_date' => $today->addDays($days),
            'status' => static::TYPE === 'quote' ? 'draft' : 'unpaid',
            'transaction_date' => static::TYPE === 'invoice' ? $today : null,
        ]);

        return $this->form($request, $doc, collect());
    }

    public function store(Request $request): RedirectResponse
    {
        [$attrs, $items] = $this->validated($request);
        $doc = $this->service->save($request->user(), new Document(['type' => static::TYPE]), $attrs + ['type' => static::TYPE], $items);

        return redirect()->route($this->routeBase().'.show', $doc)->with('status', $doc->type_label.'「'.$doc->number.'」を作りました。');
    }

    public function show(Request $request, string $id): View
    {
        $doc = $this->findDoc($request, $id);
        $doc->load(['items', 'source', 'invoice']);

        return view('documents.show', [
            'doc' => $doc,
            'base' => $this->routeBase(),
            'company' => $request->user(),
            'totals' => Calculator::totals($doc->items, $doc->roundingFor($request->user())),
        ]);
    }

    public function edit(Request $request, string $id): View|RedirectResponse
    {
        $doc = $this->findDoc($request, $id);
        if ($doc->isLocked()) {
            return redirect()->route($this->routeBase().'.show', $doc)->with('status', $doc->lock_reason);
        }

        return $this->form($request, $doc, $doc->items()->get());
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $doc = $this->findDoc($request, $id);
        if ($doc->isLocked()) {
            return redirect()->route($this->routeBase().'.show', $doc)->with('status', $doc->lock_reason);
        }
        [$attrs, $items] = $this->validated($request, $doc);
        $this->service->save($request->user(), $doc, $attrs, $items);

        return redirect()->route($this->routeBase().'.show', $doc)->with('status', '保存しました。');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $doc = $this->findDoc($request, $id);
        // 請求書は保存しておく決まりがあるので消さない(「取り消し」にする)
        if (! $doc->isQuote()) {
            return back()->with('status', '請求書は削除できません。間違えたときは「この請求書を取り消す」を使ってください。');
        }
        $label = $doc->type_label.'「'.$doc->number.'」';
        $doc->delete();

        return redirect()->route($this->routeBase().'.index')->with('status', $label.'を削除しました。');
    }

    /** PDF(ブラウザで開く。?download=1 ならダウンロード) */
    public function pdf(Request $request, string $id, PdfService $pdf): Response|RedirectResponse
    {
        $doc = $this->findDoc($request, $id);

        try {
            $binary = $pdf->render($doc, $request->user());
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('status', 'PDFを作れませんでした:'.$e->getMessage());
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition."; filename=\"document.pdf\"; filename*=UTF-8''".rawurlencode($doc->pdf_filename),
        ]);
    }

    public function duplicate(Request $request, string $id): RedirectResponse
    {
        $copy = $this->service->duplicate($request->user(), $this->findDoc($request, $id));

        return redirect()->route($this->routeBase().'.edit', $copy)->with('status', 'コピーを作りました。必要なところを直して保存してください。');
    }

    /** 状態を変える(見積:提出済み・受注など/請求:入金済み・未入金) */
    public function status(Request $request, string $id): RedirectResponse
    {
        $doc = $this->findDoc($request, $id);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Document::STATUSES[static::TYPE]))],
            'paid_on' => ['nullable', 'date'],
        ]);

        $doc->status = $data['status'];
        if (static::TYPE === 'invoice') {
            // 取り消した請求書は入金の記録も外す
            $doc->paid_on = $data['status'] === 'paid' ? ($data['paid_on'] ?? CarbonImmutable::today()->toDateString()) : null;
        }
        $doc->save();

        return back()->with('status', $doc->number.'を「'.$doc->status_label.'」にしました。');
    }

    protected function form(Request $request, Document $doc, $items): View
    {
        $rows = old('items', $items->map(fn ($i) => [
            'name' => $i->name, 'quantity' => $i->quantity_label, 'unit' => $i->unit,
            'unit_price' => $i->unit_price, 'tax_rate' => $i->tax_rate,
        ])->all());
        $rows = array_values($rows);
        // 空の行を足して、いつも少し余白を残す
        $blank = ['name' => '', 'quantity' => '', 'unit' => '', 'unit_price' => '', 'tax_rate' => 10];
        $want = max(static::MIN_ROWS, count($rows) + 3);
        while (count($rows) < $want) {
            $rows[] = $blank;
        }

        return view('documents.form', [
            'doc' => $doc,
            'base' => $this->routeBase(),
            'rows' => $rows,
            'clients' => $request->user()->clients()->orderBy('name')->get(),
            'units' => ['式', '個', '台', '本', '枚', '㎡', 'm', '時間', '日', '人工', '件', '回'],
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    protected function validated(Request $request, ?Document $doc = null): array
    {
        $user = $request->user();

        // 「12,000」「¥3,000」のような入力も数字として受け取る
        $items = collect($request->input('items', []))->map(function ($row) {
            $row = is_array($row) ? $row : [];
            foreach (['quantity', 'unit_price'] as $k) {
                if (isset($row[$k])) {
                    $row[$k] = str_replace([',', '、', '¥', '￥', '円', ' ', '　'], '', mb_convert_kana((string) $row[$k], 'n'));
                }
            }

            return $row;
        })->all();
        $request->merge(['items' => $items]);

        $data = $request->validate([
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('user_id', $user->id)],
            'subject' => ['required', 'string', 'max:100'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'transaction_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'max:50'],
            'items.*.name' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.unit' => ['nullable', 'string', 'max:10'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'items.*.tax_rate' => ['nullable', Rule::in(array_map('strval', array_keys(DocumentItem::TAX_RATES)))],
        ], [
            'due_date.after_or_equal' => ($doc?->due_label ?? (static::TYPE === 'quote' ? '有効期限' : 'お支払期限')).'は、発行日より後の日にしてください。',
        ], [
            'client_id' => '取引先', 'subject' => '件名', 'issue_date' => '発行日', 'due_date' => '期限', 'transaction_date' => '取引日', 'notes' => '備考',
            'items.*.name' => '品目', 'items.*.quantity' => '数量', 'items.*.unit' => '単位', 'items.*.unit_price' => '単価',
        ]);

        $rows = collect($data['items'])
            ->filter(fn ($r) => trim((string) ($r['name'] ?? '')) !== '')
            ->map(fn ($r) => [
                'name' => trim($r['name']),
                'quantity' => ($r['quantity'] ?? '') === '' ? 1 : (float) $r['quantity'],
                'unit' => ($r['unit'] ?? '') === '' ? null : $r['unit'],
                'unit_price' => (int) ($r['unit_price'] ?? 0),
                'tax_rate' => (int) ($r['tax_rate'] ?? 10),
            ])->values()->all();

        if (! $rows) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => '明細を1行以上入力してください(品目の欄が空の行は保存されません)。']);
        }

        $attrs = collect($data)->only(['client_id', 'subject', 'issue_date', 'due_date', 'transaction_date', 'notes'])->all();
        if (static::TYPE === 'quote') {
            $attrs['transaction_date'] = null;
        }
        if (! $doc) {
            $attrs['status'] = static::TYPE === 'quote' ? 'draft' : 'unpaid';
        }

        return [$attrs, $rows];
    }
}
