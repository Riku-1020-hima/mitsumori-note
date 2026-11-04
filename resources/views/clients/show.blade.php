@extends('layouts.app', ['title' => $client->name])

@use('App\Services\Calculator')

@section('content')
<a href="{{ route('clients.index') }}" class="text-sm text-stone-500 hover:text-ink">← 取引先の一覧</a>
<div class="flex flex-wrap items-center justify-between gap-3 mt-2 mb-5">
    <h1 class="text-2xl font-bold">{{ $client->addressee }}</h1>
    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('quotes.create', ['client_id' => $client->id]) }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2">+ 見積書</a>
        <a href="{{ route('invoices.create', ['client_id' => $client->id]) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2">+ 請求書</a>
        <a href="{{ route('clients.edit', $client) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2">編集</a>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-5 items-start">
    <section class="bg-white rounded-xl border border-stone-200 p-5 text-sm space-y-2">
        <h2 class="font-bold">連絡先</h2>
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
            <dt class="text-stone-500">ご担当者</dt><dd>{{ $client->contact_person ?: '—' }}</dd>
            <dt class="text-stone-500">住所</dt><dd>{{ $client->postal_code ? '〒'.$client->postal_code.' ' : '' }}{{ $client->address ?: '—' }}</dd>
            <dt class="text-stone-500">電話</dt><dd>{{ $client->phone ?: '—' }}</dd>
            <dt class="text-stone-500">メール</dt><dd class="break-all">{{ $client->email ?: '—' }}</dd>
        </dl>
        @if ($client->memo)<p class="pt-2 border-t border-stone-100 text-stone-600">{!! nl2br(e($client->memo)) !!}</p>@endif
    </section>

    <section class="lg:col-span-2 bg-white rounded-xl border border-stone-200 overflow-hidden">
        <h2 class="font-bold px-5 pt-5 pb-3">書類</h2>
        @if ($docs->isEmpty())
            <x-empty-state message="この取引先の書類はまだありません。" />
        @else
            <table class="rtable w-full text-sm">
                <thead class="bg-stone-50 text-stone-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">件名</th>
                        <th class="px-4 py-3 font-medium">種類</th>
                        <th class="px-4 py-3 font-medium">発行日</th>
                        <th class="px-4 py-3 font-medium text-right">金額</th>
                        <th class="px-4 py-3 font-medium">状態</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($docs as $doc)
                        <tr>
                            <td class="rt-main px-4 py-3"><a href="{{ route($doc->route_base.'.show', $doc) }}" class="font-bold hover:underline">{{ $doc->subject }}</a><span class="block text-xs text-stone-500">{{ $doc->number }}</span></td>
                            <td data-label="種類" class="px-4 py-3">{{ $doc->type_label }}</td>
                            <td data-label="発行日" class="px-4 py-3">{{ $doc->issue_date->format('Y/n/j') }}</td>
                            <td data-label="金額" class="px-4 py-3 text-right font-bold">{{ Calculator::yen($doc->total) }}</td>
                            <td data-label="状態" class="px-4 py-3"><x-doc-status :doc="$doc" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
