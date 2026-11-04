@extends('layouts.app', ['title' => \App\Models\Document::TYPES[$type]])

@use('App\Models\Document')
@use('App\Services\Calculator')

@section('content')
@php $label = Document::TYPES[$type]; @endphp
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold">{{ $label }}</h1>
    <a href="{{ route($base.'.create') }}" data-tour="doc-new" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ {{ $label }}を作る</a>
</div>

{{-- 状態で絞り込み(リンクだけ。JavaScript なし) --}}
<div data-tour="doc-filter" class="flex flex-wrap gap-2 mb-4 text-sm">
    @php
        $tabs = ['' => 'すべて'] + Document::STATUSES[$type] + ($type === 'invoice' ? ['overdue' => '期限切れ'] : []);
    @endphp
    @foreach ($tabs as $key => $name)
        @php
            $count = $key === '' ? $counts->sum('n') : ($key === 'overdue' ? $overdueCount : ($counts[$key]->n ?? 0));
            $active = (string) $status === (string) $key;
        @endphp
        <a href="{{ route($base.'.index', array_filter(['status' => $key, 'q' => $q])) }}"
           class="rounded-full border px-3 py-1.5 {{ $active ? 'bg-ink text-white border-ink' : 'bg-white border-stone-300 hover:bg-stone-50' }} {{ $key === 'overdue' && $count ? ($active ? '' : 'text-alert-600 border-alert-500/40') : '' }}">
            {{ $name }} <span class="{{ $active ? 'text-white/80' : 'text-stone-400' }}">{{ $count }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route($base.'.index') }}" class="mb-4 flex gap-2 max-w-md">
    @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
    <label for="q" class="sr-only">検索</label>
    <input type="search" id="q" name="q" value="{{ $q }}" placeholder="件名・番号・取引先で探す" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
    <button class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2 text-sm">検索</button>
</form>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    @if ($docs->isEmpty())
        @if ($q !== '' || $status)
            <x-empty-state message="条件に合う{{ $label }}はありません。" />
        @else
            <x-empty-state message="まだ{{ $label }}がありません。" :action="$label.'を作る'" :url="route($base.'.create')" />
        @endif
    @else
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">件名</th>
                    <th class="px-4 py-3 font-medium">取引先</th>
                    <th class="px-4 py-3 font-medium">発行日</th>
                    <th class="px-4 py-3 font-medium">{{ $type === 'quote' ? '有効期限' : 'お支払期限' }}</th>
                    <th class="px-4 py-3 font-medium text-right">金額(税込)</th>
                    <th class="px-4 py-3 font-medium">状態</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($docs as $doc)
                    <tr>
                        <td class="rt-main px-4 py-3">
                            <a href="{{ route($base.'.show', $doc) }}" class="font-bold hover:underline">{{ $doc->subject }}</a>
                            <span class="block text-xs text-stone-500">{{ $doc->number }}</span>
                        </td>
                        <td data-label="取引先" class="px-4 py-3">{{ $doc->client?->name ?? '—' }}</td>
                        <td data-label="発行日" class="px-4 py-3 whitespace-nowrap">{{ $doc->issue_date->format('Y/n/j') }}</td>
                        <td data-label="期限" class="px-4 py-3 whitespace-nowrap {{ $doc->isOverdue() ? 'text-alert-600 font-bold' : '' }}">{{ $doc->due_date?->format('Y/n/j') ?? '—' }}</td>
                        <td data-label="金額" class="px-4 py-3 text-right font-bold whitespace-nowrap">{{ Calculator::yen($doc->total) }}</td>
                        <td data-label="状態" class="px-4 py-3"><x-doc-status :doc="$doc" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $docs->links() }}</div>
@endsection
