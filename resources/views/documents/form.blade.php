@extends('layouts.app', ['title' => ($doc->exists ? $doc->type_label.'の編集' : $doc->type_label.'を作る')])

@use('App\Models\DocumentItem')

@section('content')
<a href="{{ $doc->exists ? route($base.'.show', $doc) : route($base.'.index') }}" class="text-sm text-stone-500 hover:text-ink">← {{ $doc->exists ? $doc->number : $doc->type_label.'の一覧' }}</a>
<h1 class="text-2xl font-bold mt-2 mb-5">{{ $doc->exists ? $doc->type_label.'の編集' : $doc->type_label.'を作る' }}</h1>

@if ($doc->exists && ! $doc->isQuote())
    <p class="mb-5 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm">
        すでにお客さんに送った請求書を直すときは、直したPDFを送り直して、変わった点をお客さんにも伝えてください。保存すると、今の自社の情報と宛名で作り直されます。
    </p>
@endif

<form method="POST" action="{{ $doc->exists ? route($base.'.update', $doc) : route($base.'.store') }}"
      data-draft="{{ $base }}-{{ $doc->id ?? 'new' }}" class="space-y-5">
    @csrf
    @if ($doc->exists) @method('PUT') @endif

    <section data-tour="doc-basic" class="bg-white rounded-xl border border-stone-200 p-6 grid sm:grid-cols-2 gap-4">
        <x-field label="取引先" name="client_id" hint="新しい取引先は、先に「取引先」から登録してください">
            <select name="client_id" class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                <option value="">(選ばない)</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}" @selected((string) old('client_id', $doc->client_id) === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="件名" name="subject">
            <input type="text" name="subject" value="{{ old('subject', $doc->subject) }}" required maxlength="100" placeholder="例:浴室リフォーム工事" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="発行日" name="issue_date">
            <input type="date" name="issue_date" value="{{ old('issue_date', $doc->issue_date?->toDateString()) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        @unless ($doc->isQuote())
            <x-field label="取引日(納品・作業完了日)" name="transaction_date" hint="請求書に「取引年月日」として印字されます(インボイス制度で必要)。空欄なら発行日と同じ">
                <input type="date" name="transaction_date" value="{{ old('transaction_date', $doc->transaction_date?->toDateString()) }}" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
        @endunless
        <x-field :label="$doc->due_label" name="due_date" :hint="$doc->isQuote() ? '空欄なら書類に出ません' : '過ぎても入金がないと「期限切れ」になります'">
            <input type="date" name="due_date" value="{{ old('due_date', $doc->due_date?->toDateString()) }}" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
    </section>

    <section data-tour="doc-items" class="bg-white rounded-xl border border-stone-200 p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="font-bold">明細</h2>
            <p class="text-xs text-stone-500">単価は税抜で入れます。品目が空の行は保存されません。行が足りないときは、保存して「編集」を開くと空の行が増えます。</p>
        </div>
        @error('items')<p class="mb-3 rounded-lg bg-alert-50 border border-alert-500/40 text-alert-600 font-bold text-sm px-3 py-2">⚠ {{ $message }}</p>@enderror

        <datalist id="units">@foreach ($units as $u)<option value="{{ $u }}">@endforeach</datalist>
        <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="text-left text-stone-500">
                    <tr>
                        <th class="py-2 pr-2 font-medium w-8">#</th>
                        <th class="py-2 pr-2 font-medium">品目</th>
                        <th class="py-2 pr-2 font-medium w-24">数量</th>
                        <th class="py-2 pr-2 font-medium w-20">単位</th>
                        <th class="py-2 pr-2 font-medium w-32">単価(円)</th>
                        <th class="py-2 font-medium w-32">税率</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr class="align-top">
                            <td class="py-1.5 pr-2 pt-3.5 text-stone-400">{{ $i + 1 }}</td>
                            <td class="py-1.5 pr-2">
                                <input type="text" name="items[{{ $i }}][name]" value="{{ $row['name'] }}" maxlength="100" aria-label="{{ $i + 1 }}行目の品目" class="w-full rounded-lg border border-stone-300 px-2.5 py-2">
                                @error("items.$i.name")<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" inputmode="decimal" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] }}" placeholder="1" aria-label="{{ $i + 1 }}行目の数量" class="w-full rounded-lg border border-stone-300 px-2.5 py-2 text-right">
                                @error("items.$i.quantity")<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" name="items[{{ $i }}][unit]" value="{{ $row['unit'] }}" list="units" maxlength="10" aria-label="{{ $i + 1 }}行目の単位" class="w-full rounded-lg border border-stone-300 px-2.5 py-2">
                            </td>
                            <td class="py-1.5 pr-2">
                                <input type="text" inputmode="numeric" name="items[{{ $i }}][unit_price]" value="{{ $row['unit_price'] }}" aria-label="{{ $i + 1 }}行目の単価" class="w-full rounded-lg border border-stone-300 px-2.5 py-2 text-right">
                                @error("items.$i.unit_price")<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
                            </td>
                            <td class="py-1.5">
                                <select name="items[{{ $i }}][tax_rate]" aria-label="{{ $i + 1 }}行目の税率" class="w-full rounded-lg border border-stone-300 px-2 py-2 bg-white">
                                    @foreach (DocumentItem::TAX_RATES as $rate => $name)
                                        <option value="{{ $rate }}" @selected((string) ($row['tax_rate'] ?? 10) === (string) $rate)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-stone-500">合計と消費税は、保存すると自動で計算されます(税率ごとにまとめて計算し、端数は<a href="{{ route('settings.edit') }}" class="underline">設定</a>の方法で処理します)。</p>
    </section>

    <section class="bg-white rounded-xl border border-stone-200 p-6">
        <x-field label="備考(任意)" name="notes" hint="書類の下に印字されます。工期・支払条件・注意事項など">
            <textarea name="notes" rows="3" maxlength="1000" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('notes', $doc->notes) }}</textarea>
        </x-field>
    </section>

    <div class="flex flex-wrap gap-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5">{{ $doc->exists ? '保存する' : '作成する' }}</button>
        <a href="{{ $doc->exists ? route($base.'.show', $doc) : route($base.'.index') }}" data-discard class="rounded-lg border border-stone-300 bg-white px-6 py-2.5">キャンセル</a>
    </div>
</form>
@endsection
