@extends('layouts.app', ['title' => $client->exists ? $client->name.'の編集' : '取引先を追加'])

@section('content')
<a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="text-sm text-stone-500 hover:text-ink">← {{ $client->exists ? $client->name : '取引先の一覧' }}</a>
<h1 class="text-2xl font-bold mt-2 mb-5">{{ $client->exists ? '取引先の編集' : '取引先を追加' }}</h1>

<form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}"
      data-draft="client-{{ $client->id ?? 'new' }}" class="max-w-2xl bg-white rounded-xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if ($client->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-[1fr_auto] gap-4">
        <x-field label="会社名・お名前" name="name">
            <input type="text" name="name" value="{{ old('name', $client->name) }}" required maxlength="100" placeholder="例:株式会社さくら不動産" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="敬称" name="honorific">
            <select name="honorific" class="rounded-lg border border-stone-300 px-3 py-2 bg-white">
                @foreach (\App\Models\Client::HONORIFICS as $h)
                    <option value="{{ $h }}" @selected(old('honorific', $client->honorific) === $h)>{{ $h }}</option>
                @endforeach
            </select>
        </x-field>
    </div>
    <p class="-mt-2 text-xs text-stone-500">会社なら「御中」、個人のお客さんなら「様」にします。</p>
    <x-field label="ご担当者(任意)" name="contact_person" hint="書類に「ご担当:〇〇 様」と入ります">
        <input type="text" name="contact_person" value="{{ old('contact_person', $client->contact_person) }}" maxlength="50" class="w-full rounded-lg border border-stone-300 px-3 py-2">
    </x-field>
    <div class="grid sm:grid-cols-[10rem_1fr] gap-4">
        <x-field label="郵便番号" name="postal_code">
            <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}" maxlength="10" placeholder="150-0001" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="住所" name="address">
            <input type="text" name="address" value="{{ old('address', $client->address) }}" maxlength="200" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="電話番号" name="phone">
            <input type="tel" name="phone" value="{{ old('phone', $client->phone) }}" maxlength="30" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="メールアドレス" name="email">
            <input type="email" name="email" value="{{ old('email', $client->email) }}" maxlength="255" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
    </div>
    <x-field label="メモ(任意)" name="memo" hint="書類には出ません">
        <textarea name="memo" rows="2" maxlength="1000" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('memo', $client->memo) }}</textarea>
    </x-field>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">{{ $client->exists ? '保存する' : '登録する' }}</button>
        <a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" data-discard class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>

@if ($client->exists)
    <form method="POST" action="{{ route('clients.destroy', $client) }}" class="max-w-2xl mt-6 text-right"
          onsubmit="return confirm('{{ $client->name }}を削除します。この取引先の書類は残りますが、宛先が空になります。よろしいですか?')">
        @csrf @method('DELETE')
        <button class="text-sm text-alert-600 underline">この取引先を削除する</button>
    </form>
@endif
@endsection
