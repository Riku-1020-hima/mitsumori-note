<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** 取引先 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $clients = $request->user()->clients()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->withCount(['documents as quotes_count' => fn ($d) => $d->where('type', 'quote')])
            ->withSum(['documents as billed' => fn ($d) => $d->where('type', 'invoice')->where('status', '!=', 'void')], 'total')
            ->withSum(['documents as unpaid' => fn ($d) => $d->where('type', 'invoice')->where('status', 'unpaid')], 'total')
            ->orderBy('name')->get();

        return view('clients.index', compact('clients', 'q'));
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client(['honorific' => '御中'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $request->user()->clients()->create($this->validated($request));

        return redirect()->route('clients.show', $client)->with('status', '取引先を登録しました。');
    }

    public function show(Request $request, Client $client): View
    {
        $this->authorizeOwner($request, $client);
        $docs = $client->documents()->orderByDesc('issue_date')->orderByDesc('id')->get();

        return view('clients.show', compact('client', 'docs'));
    }

    public function edit(Request $request, Client $client): View
    {
        $this->authorizeOwner($request, $client);

        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeOwner($request, $client);
        $client->update($this->validated($request));

        return redirect()->route('clients.show', $client)->with('status', '取引先を更新しました。');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeOwner($request, $client);
        $name = $client->name;
        $client->delete(); // 書類は残し、取引先の欄だけ空にする

        return redirect()->route('clients.index')->with('status', $name.'を削除しました。この取引先の書類は残っています。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'honorific' => ['required', Rule::in(Client::HONORIFICS)],
            'contact_person' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'name' => '会社名・お名前', 'honorific' => '敬称', 'contact_person' => 'ご担当者', 'postal_code' => '郵便番号',
            'address' => '住所', 'email' => 'メールアドレス', 'phone' => '電話番号', 'memo' => 'メモ',
        ]);
    }

    private function authorizeOwner(Request $request, Client $client): void
    {
        abort_unless((int) $client->user_id === (int) $request->user()->id, 404);
    }
}
