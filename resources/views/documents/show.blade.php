@extends('layouts.app', ['title' => $doc->type_label.' '.$doc->number])

@use('App\Models\Document')
@use('App\Services\Calculator')

@section('content')
<a href="{{ route($base.'.index') }}" class="text-sm text-stone-500 hover:text-ink">← {{ $doc->type_label }}の一覧</a>
<div class="flex flex-wrap items-center gap-3 mt-2 mb-5">
    <h1 class="text-2xl font-bold">{{ $doc->subject }}</h1>
    <x-doc-status :doc="$doc" />
</div>

@if ($doc->isVoid())
    <div class="mb-5 rounded-lg border border-stone-300 bg-stone-100 px-4 py-3 text-sm">
        この請求書は<b>取り消し済み</b>です。合計や未入金には数えません。記録として残しています(PDFには「取消」の透かしが入ります)。
    </div>
@endif

<div class="grid lg:grid-cols-3 gap-5 items-start">
    {{-- 操作 --}}
    <aside class="lg:order-2 space-y-4">
        <section data-tour="doc-pdf" class="bg-white rounded-xl border border-stone-200 p-5 space-y-2">
            <p class="text-sm text-stone-500">{{ $doc->number }}</p>
            <p class="text-2xl font-bold">{{ Calculator::yen($totals['total']) }}<span class="text-sm font-normal text-stone-500">(税込)</span></p>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <a href="{{ route($base.'.pdf', $doc) }}" target="_blank" rel="noopener" class="text-center rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-3 py-2 text-sm">PDFを開く</a>
                <a href="{{ route($base.'.pdf', [$doc, 'download' => 1]) }}" class="text-center rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-2 text-sm">ダウンロード</a>
            </div>
            <p class="text-xs text-stone-500">PDFはメールに添付したり、印刷したりできます。</p>
        </section>

        {{-- 状態を変える --}}
        <section data-tour="doc-status" class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-bold text-sm mb-3">状態</h2>
            @if ($doc->isQuote())
                <div class="flex flex-wrap gap-2">
                    @foreach (Document::STATUSES['quote'] as $key => $name)
                        <form method="POST" action="{{ route('quotes.status', $doc) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $key }}">
                            <button @disabled($doc->status === $key) aria-pressed="{{ $doc->status === $key ? 'true' : 'false' }}"
                                    class="rounded-full border px-3 py-1.5 text-sm {{ $doc->status === $key ? 'bg-ink text-white border-ink' : 'bg-white border-stone-300 hover:bg-stone-50' }}">{{ $name }}</button>
                        </form>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-stone-100">
                    @if ($doc->invoice)
                        <p class="text-sm">請求書:<a href="{{ route('invoices.show', $doc->invoice) }}" class="text-brand-600 underline">{{ $doc->invoice->number }}</a></p>
                    @else
                        <form method="POST" action="{{ route('quotes.convert', $doc) }}">
                            @csrf
                            <button data-tour="doc-convert" class="w-full rounded-lg bg-ink hover:bg-stone-800 text-white font-bold px-3 py-2 text-sm">この見積から請求書を作る</button>
                        </form>
                        <p class="mt-2 text-xs text-stone-500">明細をそのまま写した請求書ができます。見積書は「受注」になります。</p>
                    @endif
                </div>
            @elseif ($doc->isVoid())
                <form method="POST" action="{{ route('invoices.status', $doc) }}" onsubmit="return confirm('取り消しをやめて、未入金に戻しますか?')">
                    @csrf
                    <input type="hidden" name="status" value="unpaid">
                    <button class="text-sm text-stone-600 underline">取り消しをやめる(未入金に戻す)</button>
                </form>
            @else
                @if ($doc->status === 'paid')
                    <p class="text-sm"><span class="font-bold text-brand-700">入金済み</span>({{ $doc->paid_on?->format('Y年n月j日') }})</p>
                    <form method="POST" action="{{ route('invoices.status', $doc) }}" class="mt-3" onsubmit="return confirm('未入金に戻しますか?')">
                        @csrf
                        <input type="hidden" name="status" value="unpaid">
                        <button class="text-sm text-stone-600 underline">未入金に戻す</button>
                    </form>
                @else
                    @if ($doc->isOverdue())
                        <p class="mb-3 text-sm font-bold text-alert-600">お支払期限({{ $doc->due_date->format('n/j') }})を過ぎています</p>
                    @endif
                    <form method="POST" action="{{ route('invoices.status', $doc) }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="status" value="paid">
                        <label class="block text-sm">
                            <span class="text-stone-500">入金日</span>
                            <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
                        </label>
                        <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-3 py-2 text-sm">入金済みにする</button>
                    </form>
                @endif
                @if ($doc->source)
                    <p class="mt-4 pt-4 border-t border-stone-100 text-sm">元の見積書:<a href="{{ route('quotes.show', $doc->source) }}" class="text-brand-600 underline">{{ $doc->source->number }}</a></p>
                @endif
            @endif
        </section>

        <section class="bg-white rounded-xl border border-stone-200 p-5 flex flex-wrap gap-2">
            @if ($doc->isLocked())
                <p class="w-full text-xs text-stone-500 mb-1">{{ $doc->lock_reason }}</p>
            @else
                <a href="{{ route($base.'.edit', $doc) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2 text-sm">編集</a>
            @endif
            <form method="POST" action="{{ route($base.'.duplicate', $doc) }}">
                @csrf
                <button class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2 text-sm">コピーして新しく作る</button>
            </form>
            @if ($doc->isQuote())
                <form method="POST" action="{{ route($base.'.destroy', $doc) }}" class="ml-auto" onsubmit="return confirm('{{ $doc->number }}を削除します。元に戻せません。よろしいですか?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg px-2 py-2 text-sm text-alert-600 underline">削除</button>
                </form>
            @elseif (! $doc->isVoid())
                <form method="POST" action="{{ route('invoices.status', $doc) }}" class="ml-auto" onsubmit="return confirm('{{ $doc->number }}を取り消します。請求書は消さずに「取消」として残ります。よろしいですか?')">
                    @csrf
                    <input type="hidden" name="status" value="void">
                    <button class="rounded-lg px-2 py-2 text-sm text-alert-600 underline">この請求書を取り消す</button>
                </form>
            @endif
            @unless ($doc->isQuote())
                <p class="w-full text-xs text-stone-500">請求書は保存しておく決まりがあるため、削除ではなく「取り消し」にします。</p>
            @endunless
        </section>
    </aside>

    {{-- 紙面のプレビュー(PDFと同じ中身) --}}
    <div class="lg:col-span-2 lg:order-1 overflow-x-auto">
        <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-6 sm:p-10 min-w-[600px]">
            @include('documents.paper', ['company' => $company])
        </div>
        <p class="mt-2 text-xs text-stone-500">PDFと同じ内容のプレビューです。</p>
    </div>
</div>
@endsection
