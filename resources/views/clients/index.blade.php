@extends('layouts.app', ['title' => '取引先'])

@use('App\Services\Calculator')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold">取引先</h1>
    <a href="{{ route('clients.create') }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ 取引先を追加</a>
</div>

<form method="GET" action="{{ route('clients.index') }}" class="mb-4 flex gap-2 max-w-md">
    <label for="q" class="sr-only">検索</label>
    <input type="search" id="q" name="q" value="{{ $q }}" placeholder="会社名・お名前で探す" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
    <button class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2 text-sm">検索</button>
</form>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    @if ($clients->isEmpty())
        @if ($q !== '')
            <x-empty-state message="「{{ $q }}」に合う取引先はありません。" />
        @else
            <x-empty-state message="まだ取引先がありません。見積書・請求書の宛先になる会社やお客さんを登録しましょう。" action="取引先を追加する" :url="route('clients.create')" />
        @endif
    @else
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">会社名・お名前</th>
                    <th class="px-4 py-3 font-medium">ご担当者</th>
                    <th class="px-4 py-3 font-medium text-right">見積書</th>
                    <th class="px-4 py-3 font-medium text-right">請求額の合計</th>
                    <th class="px-4 py-3 font-medium text-right">未入金</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($clients as $client)
                    <tr>
                        <td class="rt-main px-4 py-3"><a href="{{ route('clients.show', $client) }}" class="font-bold hover:underline">{{ $client->addressee }}</a></td>
                        <td data-label="ご担当者" class="px-4 py-3">{{ $client->contact_person ?: '—' }}</td>
                        <td data-label="見積書" class="px-4 py-3 text-right">{{ $client->quotes_count }}件</td>
                        <td data-label="請求額" class="px-4 py-3 text-right">{{ Calculator::yen((int) $client->billed) }}</td>
                        <td data-label="未入金" class="px-4 py-3 text-right {{ $client->unpaid ? 'font-bold' : 'text-stone-400' }}">{{ Calculator::yen((int) $client->unpaid) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
