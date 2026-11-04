@extends('layouts.app', ['title' => 'ホーム'])

@use('App\Services\Calculator')

@section('content')
@unless (collect($setup)->every('done'))
    <section data-tour="setup" class="mb-6 rounded-xl border border-brand-100 bg-brand-50 p-5">
        <h2 class="font-bold text-brand-700">はじめにやること</h2>
        <ol class="mt-3 space-y-2 text-sm">
            @foreach ($setup as $i => $step)
                <li class="flex items-center gap-3">
                    <span class="inline-flex w-6 h-6 shrink-0 rounded-full items-center justify-center text-xs font-bold {{ $step['done'] ? 'bg-brand-500 text-white' : 'bg-white border border-brand-100 text-brand-700' }}">{{ $step['done'] ? '✓' : $i + 1 }}</span>
                    @if ($step['done'])
                        <span class="text-stone-400 line-through">{{ $step['label'] }}</span>
                    @else
                        <a href="{{ $step['url'] }}" class="underline decoration-brand-100 underline-offset-4 hover:text-brand-700">{{ $step['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endunless

<div data-tour="numbers" class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-stone-200 p-5">
        <p class="text-sm text-stone-500">{{ $month->format('n') }}月の請求額</p>
        <p class="mt-1 text-2xl font-bold">{{ Calculator::yen($billedThisMonth) }}</p>
    </div>
    <a href="{{ route('invoices.index', ['status' => 'unpaid']) }}" class="block bg-white rounded-xl border border-stone-200 p-5 hover:border-stone-300">
        <p class="text-sm text-stone-500">未入金</p>
        <p class="mt-1 text-2xl font-bold">{{ Calculator::yen($unpaidTotal) }}<span class="text-sm font-normal text-stone-500"> / {{ $unpaidCount }}件</span></p>
        @if ($overdue->isNotEmpty())
            <p class="mt-1 text-sm font-bold text-alert-600">うち{{ $overdue->count() }}件が期限切れ</p>
        @endif
    </a>
    <div class="bg-white rounded-xl border border-stone-200 p-5">
        <p class="text-sm text-stone-500">今年の受注率</p>
        @if ($winRate === null)
            <p class="mt-1 text-2xl font-bold text-stone-400">—</p>
            <p class="mt-1 text-xs text-stone-500">見積書を「受注」「失注」にすると出ます</p>
        @else
            <p class="mt-1 text-2xl font-bold">{{ $winRate }}%</p>
            <p class="mt-1 text-xs text-stone-500">結果が出た{{ $decidedCount }}件のうち{{ $wonCount }}件</p>
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-5">
    <section data-tour="overdue" class="bg-white rounded-xl border border-stone-200 p-5">
        <h2 class="font-bold">期限を過ぎた請求書</h2>
        @if ($overdue->isEmpty())
            <p class="mt-3 text-sm text-stone-500">ありません。</p>
        @else
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($overdue as $doc)
                    <li class="py-2.5 flex items-center justify-between gap-3">
                        <a href="{{ route('invoices.show', $doc) }}" class="min-w-0 hover:underline">
                            <span class="font-bold">{{ $doc->client?->name ?? '(取引先なし)' }}</span>
                            <span class="block text-xs text-stone-500 truncate">{{ $doc->subject }}</span>
                        </a>
                        <span class="text-right shrink-0">
                            <span class="block font-bold">{{ Calculator::yen($doc->total) }}</span>
                            <span class="block text-xs text-alert-600">期限 {{ $doc->due_date->format('n/j') }}({{ (int) $doc->due_date->diffInDays(now()->startOfDay()) }}日過ぎ)</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section data-tour="waiting" class="bg-white rounded-xl border border-stone-200 p-5">
        <h2 class="font-bold">返事待ちの見積書</h2>
        @if ($waiting->isEmpty())
            <p class="mt-3 text-sm text-stone-500">ありません。見積書を「提出済み」にすると、ここに並びます。</p>
        @else
            <ul class="mt-3 divide-y divide-stone-100 text-sm">
                @foreach ($waiting as $doc)
                    <li class="py-2.5 flex items-center justify-between gap-3">
                        <a href="{{ route('quotes.show', $doc) }}" class="min-w-0 hover:underline">
                            <span class="font-bold">{{ $doc->client?->name ?? '(取引先なし)' }}</span>
                            <span class="block text-xs text-stone-500 truncate">{{ $doc->subject }}</span>
                        </a>
                        <span class="text-right shrink-0">
                            <span class="block font-bold">{{ Calculator::yen($doc->total) }}</span>
                            @if ($doc->due_date)<span class="block text-xs text-stone-500">有効期限 {{ $doc->due_date->format('n/j') }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<section class="mt-5 bg-white rounded-xl border border-stone-200 p-5">
    <div class="flex items-center justify-between gap-2">
        <h2 class="font-bold">最近さわった書類</h2>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('quotes.create') }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-3 py-1.5">+ 見積書</a>
            <a href="{{ route('invoices.create') }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5">+ 請求書</a>
        </div>
    </div>
    @if ($recent->isEmpty())
        <p class="mt-3 text-sm text-stone-500">まだ書類がありません。</p>
    @else
        <ul class="mt-3 divide-y divide-stone-100 text-sm">
            @foreach ($recent as $doc)
                <li class="py-2.5 flex items-center justify-between gap-3">
                    <a href="{{ route($doc->route_base.'.show', $doc) }}" class="min-w-0 hover:underline">
                        <span class="text-xs text-stone-500">{{ $doc->type_label }} {{ $doc->number }}</span>
                        <span class="block font-bold truncate">{{ $doc->subject }}</span>
                    </a>
                    <span class="flex items-center gap-3 shrink-0">
                        <span class="font-bold">{{ Calculator::yen($doc->total) }}</span>
                        <x-doc-status :doc="$doc" />
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
