{{--
    見積書・請求書の「紙面」。PDF(mPDF)と画面のプレビューで同じものを使う。
    mPDF は使える CSS が限られるので、表(table)と style 属性だけで組んでいる。
--}}
@use('App\Services\Calculator')
@php
    $isQuote = $doc->isQuote();
    $issuer = $doc->issuerFor($company);   // 作ったときの自社の情報
    $to = $doc->recipient_info;            // 作ったときの宛名
    $line = '#cbd5e1';
    $head = '#eef3fe';
    $ink = '#1f2a37';
    $muted = '#57534e';
@endphp
<div style="color: {{ $ink }}; font-size: 10pt; line-height: 1.5">
    <table style="width: 100%; border-collapse: collapse">
        <tr>
            <td style="text-align: center; padding-bottom: 14pt">
                <span style="font-size: 20pt; font-weight: bold; letter-spacing: 6pt">{{ $isQuote ? '御見積書' : '請求書' }}</span>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse">
        <tr>
            <td style="width: 56%; vertical-align: top; padding-right: 12pt">
                {{-- 宛名の下線:mPDF は div の下線と余白がずれるので、表の枠線で引く --}}
                <table style="width: 100%; border-collapse: collapse">
                    <tr>
                        <td style="font-size: 14pt; font-weight: bold; line-height: 1.4; padding: 0 0 5pt 0; border-bottom: 1.5pt solid {{ $ink }}">{{ $to ? $to['name'].' '.$to['honorific'] : '(取引先未設定)' }}</td>
                    </tr>
                </table>
                @if (! empty($to['contact_person']))
                    <div style="margin-top: 3pt">ご担当:{{ $to['contact_person'] }} 様</div>
                @endif
                <div style="margin-top: 12pt">{{ $isQuote ? '下記の通り、御見積申し上げます。' : '下記の通り、ご請求申し上げます。' }}</div>
                <table style="width: 100%; border-collapse: collapse; margin-top: 8pt">
                    <tr>
                        <td style="width: 22%; padding: 3pt 0; color: {{ $muted }}">件名</td>
                        <td style="padding: 3pt 0">{{ $doc->subject }}</td>
                    </tr>
                    @unless ($isQuote)
                        <tr>
                            <td style="padding: 3pt 0; color: {{ $muted }}">取引年月日</td>
                            <td style="padding: 3pt 0">{{ $doc->transaction_day->format('Y年n月j日') }}</td>
                        </tr>
                    @endunless
                    @if ($doc->due_date)
                        <tr>
                            <td style="padding: 3pt 0; color: {{ $muted }}">{{ $doc->due_label }}</td>
                            <td style="padding: 3pt 0">{{ $doc->due_date->format('Y年n月j日') }}</td>
                        </tr>
                    @endif
                </table>
                <table style="width: 100%; border-collapse: collapse; margin-top: 10pt">
                    <tr>
                        <td style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 6pt 8pt; font-weight: bold; width: 42%">{{ $isQuote ? '御見積金額' : 'ご請求金額' }}</td>
                        <td style="border: 1pt solid {{ $line }}; padding: 6pt 8pt; text-align: right; font-size: 15pt; font-weight: bold">{{ Calculator::yen($totals['total']) }}<span style="font-size: 9pt; font-weight: normal">(税込)</span></td>
                    </tr>
                </table>
            </td>
            <td style="width: 44%; vertical-align: top; font-size: 9pt">
                <table style="width: 100%; border-collapse: collapse">
                    <tr><td style="color: {{ $muted }}; width: 30%">番号</td><td style="text-align: right">{{ $doc->number }}</td></tr>
                    <tr><td style="color: {{ $muted }}">発行日</td><td style="text-align: right">{{ $doc->issue_date->format('Y年n月j日') }}</td></tr>
                </table>
                <div style="margin-top: 12pt; font-size: 11pt; font-weight: bold">{{ $issuer['name'] }}</div>
                @if ($issuer['postal_code'])<div>〒{{ $issuer['postal_code'] }}</div>@endif
                @if ($issuer['address'])<div>{{ $issuer['address'] }}</div>@endif
                @if ($issuer['phone'])<div>TEL {{ $issuer['phone'] }}</div>@endif
                @if ($issuer['invoice_number'])<div style="margin-top: 3pt">登録番号:{{ $issuer['invoice_number'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16pt">
        <thead>
            <tr>
                <th style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 5pt; text-align: left">品目</th>
                <th style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 5pt; text-align: right; width: 11%">数量</th>
                <th style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 5pt; text-align: center; width: 9%">単位</th>
                <th style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 5pt; text-align: right; width: 16%">単価</th>
                <th style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 5pt; text-align: right; width: 18%">金額</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc->items as $item)
                <tr>
                    <td style="border: 1pt solid {{ $line }}; padding: 5pt">{{ $item->name }}@if ($item->tax_rate === 8) <span style="color: {{ $muted }}">※</span>@elseif ($item->tax_rate === 0) <span style="color: {{ $muted }}">(非課税)</span>@endif</td>
                    <td style="border: 1pt solid {{ $line }}; padding: 5pt; text-align: right">{{ $item->quantity_label }}</td>
                    <td style="border: 1pt solid {{ $line }}; padding: 5pt; text-align: center">{{ $item->unit }}</td>
                    <td style="border: 1pt solid {{ $line }}; padding: 5pt; text-align: right">{{ number_format($item->unit_price) }}</td>
                    <td style="border: 1pt solid {{ $line }}; padding: 5pt; text-align: right">{{ number_format($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-top: 8pt">
        <tr>
            <td style="width: 46%; vertical-align: top; font-size: 8.5pt; color: {{ $muted }}; padding-right: 12pt">
                @if (isset($totals['rates'][8]))※は軽減税率(8%)の対象です。@endif
            </td>
            <td style="width: 54%; vertical-align: top">
                <table style="width: 100%; border-collapse: collapse">
                    <tr>
                        <td style="border: 1pt solid {{ $line }}; padding: 4pt 6pt; background: {{ $head }}">小計(税抜)</td>
                        <td style="border: 1pt solid {{ $line }}; padding: 4pt 6pt; text-align: right">{{ Calculator::yen($totals['subtotal']) }}</td>
                    </tr>
                    @foreach ($totals['rates'] as $rate => $r)
                        <tr>
                            <td style="border: 1pt solid {{ $line }}; padding: 4pt 6pt; font-size: 9pt">{{ $rate === 0 ? '非課税' : $rate.'%対象' }}</td>
                            <td style="border: 1pt solid {{ $line }}; padding: 4pt 6pt; text-align: right; font-size: 9pt">{{ Calculator::yen($r['base']) }}@if ($rate > 0)(消費税 {{ Calculator::yen($r['tax']) }})@endif</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="border: 1pt solid {{ $line }}; padding: 4pt 6pt; background: {{ $head }}">消費税</td>
                        <td style="border: 1pt solid {{ $line }}; padding: 4pt 6pt; text-align: right">{{ Calculator::yen($totals['tax']) }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1pt solid {{ $line }}; padding: 5pt 6pt; background: {{ $head }}; font-weight: bold">合計(税込)</td>
                        <td style="border: 1pt solid {{ $line }}; padding: 5pt 6pt; text-align: right; font-weight: bold; font-size: 12pt">{{ Calculator::yen($totals['total']) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (! $isQuote && $issuer['bank_account'])
        <table style="width: 100%; border-collapse: collapse; margin-top: 14pt">
            <tr><td style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 4pt 6pt; font-weight: bold">お振込先</td></tr>
            <tr><td style="border: 1pt solid {{ $line }}; padding: 6pt">{!! nl2br(e($issuer['bank_account'])) !!}<div style="margin-top: 3pt; font-size: 8.5pt; color: {{ $muted }}">恐れ入りますが、振込手数料はご負担くださいますようお願いいたします。</div></td></tr>
        </table>
    @endif

    @if ($doc->notes)
        <table style="width: 100%; border-collapse: collapse; margin-top: 14pt">
            <tr><td style="background: {{ $head }}; border: 1pt solid {{ $line }}; padding: 4pt 6pt; font-weight: bold">備考</td></tr>
            <tr><td style="border: 1pt solid {{ $line }}; padding: 6pt">{!! nl2br(e($doc->notes)) !!}</td></tr>
        </table>
    @endif
</div>
