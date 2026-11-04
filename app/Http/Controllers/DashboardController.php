<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $monthStart = CarbonImmutable::today()->startOfMonth();
        $nextMonth = $monthStart->addMonth();
        $yearStart = CarbonImmutable::today()->startOfYear();

        // 日付は「次の月の1日より前」で比べる(データベースによる日付の持ち方の違いを気にしなくてよい)
        $invoices = $user->documents()->invoices()->where('status', '!=', 'void');
        $billedThisMonth = (clone $invoices)->where('issue_date', '>=', $monthStart->toDateString())->where('issue_date', '<', $nextMonth->toDateString())->sum('total');
        $unpaid = (clone $invoices)->where('status', 'unpaid');
        $overdue = $user->documents()->overdue()->with('client')->orderBy('due_date')->get();

        $decided = $user->documents()->quotes()->whereIn('status', ['won', 'lost'])->where('issue_date', '>=', $yearStart->toDateString());
        $won = (clone $decided)->where('status', 'won')->count();
        $decidedCount = (clone $decided)->count();

        return view('dashboard', [
            'billedThisMonth' => (int) $billedThisMonth,
            'unpaidTotal' => (int) (clone $unpaid)->sum('total'),
            'unpaidCount' => (clone $unpaid)->count(),
            'overdue' => $overdue,
            'winRate' => $decidedCount ? (int) round($won / $decidedCount * 100) : null,
            'wonCount' => $won,
            'decidedCount' => $decidedCount,
            'waiting' => $user->documents()->quotes()->where('status', 'sent')->with('client')->orderBy('due_date')->limit(5)->get(),
            'recent' => $user->documents()->with('client')->orderByDesc('updated_at')->limit(6)->get(),
            'setup' => [
                ['done' => (bool) ($user->address && $user->bank_account), 'label' => '自社の情報(住所・振込先・登録番号)を入れる', 'url' => route('settings.edit')],
                ['done' => $user->clients()->exists(), 'label' => '取引先を登録する', 'url' => route('clients.create')],
                ['done' => $user->documents()->quotes()->exists(), 'label' => '見積書を作ってPDFで確かめる', 'url' => route('quotes.create')],
            ],
            'month' => $monthStart,
        ]);
    }
}
