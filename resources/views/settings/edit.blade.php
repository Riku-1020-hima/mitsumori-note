@extends('layouts.app', ['title' => '設定'])

@use('App\Models\User')

@section('content')
<h1 class="text-2xl font-bold mb-5">設定</h1>

<form method="POST" action="{{ route('settings.update') }}" class="max-w-2xl space-y-5">
    @csrf @method('PUT')

    <section data-tour="set-company" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        <div>
            <h2 class="font-bold">自社の情報</h2>
            <p class="text-sm text-stone-500">見積書・請求書の右上に、発行元として印字されます。</p>
        </div>
        <x-field label="会社名・屋号" name="name">
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <div class="grid sm:grid-cols-[10rem_1fr] gap-4">
            <x-field label="郵便番号" name="postal_code">
                <input type="text" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" maxlength="10" placeholder="150-0001" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
            <x-field label="住所" name="address">
                <input type="text" name="address" value="{{ old('address', $user->address) }}" maxlength="200" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="電話番号" name="phone">
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
            <x-field label="登録番号(インボイス)" name="invoice_number" hint="適格請求書発行事業者の番号。登録していなければ空欄でOK">
                <input type="text" name="invoice_number" value="{{ old('invoice_number', $user->invoice_number) }}" maxlength="20" placeholder="T1234567890123" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
        </div>
        <x-field label="振込先" name="bank_account" hint="請求書の下に印字されます">
            <textarea name="bank_account" rows="3" maxlength="300" placeholder="例:さくら銀行 渋谷支店 普通 1234567 サクラコウムテン" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('bank_account', $user->bank_account) }}</textarea>
        </x-field>
    </section>

    <section data-tour="set-rules" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        <h2 class="font-bold">計算と期限のきまり</h2>
        <div>
            <span class="block text-sm font-medium mb-2">消費税の1円未満の端数</span>
            <div class="flex flex-wrap gap-2">
                @foreach (User::ROUNDINGS as $key => $name)
                    <label>
                        <input type="radio" name="rounding" value="{{ $key }}" class="peer sr-only" @checked(old('rounding', $user->rounding ?? 'floor') === $key)>
                        <span class="inline-block rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm cursor-pointer select-none peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">{{ $name }}</span>
                    </label>
                @endforeach
            </div>
            <span class="block text-xs text-stone-500 mt-2">税率ごとに合計してから、1回だけ端数を処理します(インボイス制度のルール)。迷ったら「切り捨て」が一般的です</span>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-field label="見積の有効期限" name="quote_valid_days">
                <div class="flex items-center gap-2 text-sm">発行日から <input type="number" name="quote_valid_days" value="{{ old('quote_valid_days', $user->quote_valid_days ?? 30) }}" min="1" max="365" required class="w-20 rounded-lg border border-stone-300 px-3 py-2"> 日</div>
            </x-field>
            <x-field label="請求のお支払期限" name="payment_due_days">
                <div class="flex items-center gap-2 text-sm">発行日から <input type="number" name="payment_due_days" value="{{ old('payment_due_days', $user->payment_due_days ?? 30) }}" min="1" max="365" required class="w-20 rounded-lg border border-stone-300 px-3 py-2"> 日</div>
            </x-field>
        </div>
        <p class="text-xs text-stone-500">新しく書類を作るときの初期値です。書類ごとに変えることもできます。</p>
    </section>

    @if (config('demo.enabled'))
        <section class="bg-white rounded-xl border border-stone-200 p-6 space-y-2">
            <h2 class="font-bold">ログイン情報</h2>
            <p class="text-sm text-stone-500">デモ版では、みんなが同じアカウントを使うため、メールアドレスとパスワードは変えられません。</p>
        </section>
    @else
        <section class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
            <h2 class="font-bold">ログイン情報</h2>
            <x-field label="メールアドレス" name="email">
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
            <div class="pt-2 border-t border-stone-100">
                <p class="text-sm text-stone-500 mb-3">パスワードを変えるときだけ入力してください。</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <x-field label="現在のパスワード" name="current_password">
                        <input type="password" name="current_password" autocomplete="current-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    </x-field>
                    <x-field label="新しいパスワード" name="password">
                        <input type="password" name="password" autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    </x-field>
                    <x-field label="新しいパスワード(確認)" name="password_confirmation">
                        <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    </x-field>
                </div>
            </div>
        </section>
    @endif

    <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5">保存する</button>
</form>
@endsection
