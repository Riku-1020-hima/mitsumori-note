<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use RuntimeException;

/**
 * 見積書・請求書のPDFを作る(mPDF を使用)
 * 日本語は resources/fonts の Noto Sans JP(Google Fonts・SIL Open Font License)で表示する
 */
class PdfService
{
    public const FONTS = ['R' => 'NotoSansJP-Regular.ttf', 'B' => 'NotoSansJP-Bold.ttf'];

    public function render(Document $doc, User $company): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException('PDFの部品(mpdf)が入っていません。composer require mpdf/mpdf を実行してください。');
        }
        foreach (self::FONTS as $file) {
            if (! is_file(resource_path('fonts/'.$file))) {
                throw new RuntimeException('日本語フォント('.$file.')が見つかりません。resources/fonts に置いてください。');
            }
        }

        $doc->loadMissing(['items', 'client']);
        $html = view('documents.pdf', [
            'doc' => $doc,
            'company' => $company,
            'totals' => Calculator::totals($doc->items, $doc->roundingFor($company)),
        ])->render();

        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $defaults = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16, 'margin_right' => 16, 'margin_top' => 16, 'margin_bottom' => 16,
            'tempDir' => $tmp,
            'fontDir' => array_merge($defaults['fontDir'], [resource_path('fonts')]),
            'fontdata' => $fonts['fontdata'] + ['notosansjp' => self::FONTS],
            'default_font' => 'notosansjp',
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
        ]);
        $mpdf->SetTitle($doc->type_label.' '.$doc->number);
        if ($doc->isVoid()) {
            // 取り消した請求書には大きく「取消」と透かしを入れる
            $mpdf->SetWatermarkText('取消', 0.12);
            $mpdf->watermark_font = 'notosansjp';
            $mpdf->showWatermarkText = true;
        }
        $mpdf->SetAuthor($company->name);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
