<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // 公開デモでは、みんなが同じアカウントを使うのでログイン情報は変えられない
        $demo = config('demo.enabled');
        if ($demo) {
            $request->merge(['email' => $user->email]);
            foreach (['password', 'password_confirmation', 'current_password'] as $key) {
                $request->request->remove($key);
            }
        }
        // 登録番号は全角や小文字でも受け付ける(t1234… → T1234…)
        if ($request->filled('invoice_number')) {
            $request->merge(['invoice_number' => strtoupper(str_replace([' ', '-', '　'], '', mb_convert_kana($request->input('invoice_number'), 'as')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'invoice_number' => ['nullable', 'regex:/^T\d{13}$/'],
            'bank_account' => ['nullable', 'string', 'max:300'],
            'rounding' => ['required', Rule::in(array_keys(User::ROUNDINGS))],
            'quote_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'payment_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'invoice_number.regex' => '登録番号は「T」と13けたの数字で入力してください(例:T1234567890123)。',
            'current_password.current_password' => '現在のパスワードが違います。',
            'current_password.required_with' => 'パスワードを変えるときは、現在のパスワードも入力してください。',
        ], [
            'name' => '会社名・屋号', 'postal_code' => '郵便番号', 'address' => '住所', 'phone' => '電話番号',
            'bank_account' => '振込先', 'quote_valid_days' => '見積の有効期限', 'payment_due_days' => 'お支払期限',
        ]);

        $user->fill(collect($data)->except(['current_password', 'password'])->all());
        if (! $demo && ! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('settings.edit')->with('status', '設定を保存しました。');
    }
}
