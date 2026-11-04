<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** 見積書 */
class QuoteController extends DocumentController
{
    protected const TYPE = 'quote';

    /** 見積書から請求書を作る */
    public function convert(Request $request, string $id): RedirectResponse
    {
        $quote = $this->findDoc($request, $id);
        if ($existing = $quote->invoice()->first()) {
            return redirect()->route('invoices.show', $existing)->with('status', 'この見積書の請求書はもう作ってあります。');
        }

        $invoice = $this->service->convertToInvoice($request->user(), $quote);

        return redirect()->route('invoices.show', $invoice)->with('status', '見積書「'.$quote->number.'」から請求書を作りました。日付や期限を確認してください。');
    }
}
