<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * デモ用データ(架空の工務店)。ログイン: demo@example.com / password
 *  取引先6社、見積書(下書き・提出済み・受注・失注)、請求書(入金済み・未入金・期限切れ)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'さくら工務店',
            'postal_code' => '150-0000',
            'address' => '東京都渋谷区さくら町1-2-3',
            'phone' => '03-0000-0000',
            'invoice_number' => 'T0000000000000',
            'bank_account' => "さくら銀行 渋谷支店 普通 0000000\nサクラコウムテン",
            'rounding' => 'floor',
            'quote_valid_days' => 30,
            'payment_due_days' => 30,
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $clients = collect([
            ['name' => '株式会社みどり不動産', 'honorific' => '御中', 'contact_person' => '田中'],
            ['name' => 'あおぞら管理株式会社', 'honorific' => '御中', 'contact_person' => '鈴木'],
            ['name' => '山田 太郎', 'honorific' => '様', 'contact_person' => null],
            ['name' => 'カフェ こもれび', 'honorific' => '御中', 'contact_person' => '佐藤'],
            ['name' => '佐々木 花子', 'honorific' => '様', 'contact_person' => null],
            ['name' => '有限会社ひかり商事', 'honorific' => '御中', 'contact_person' => '高橋'],
        ])->map(fn ($c) => $user->clients()->create($c + ['address' => '東京都内', 'phone' => '03-0000-0000']));

        $service = app(DocumentService::class);
        $today = CarbonImmutable::today();

        // [取引先, 件名, 何日前に発行, 見積の状態, 請求(なし/unpaid/paid/overdue), 明細]
        $jobs = [
            [0, '空室3部屋の原状回復工事', 75, 'won', 'paid', [
                ['クロス張り替え', 120, '㎡', 1200, 10], ['床クッションフロア張り替え', 45, '㎡', 3800, 10], ['ハウスクリーニング', 3, '室', 25000, 10], ['廃材処分費', 1, '式', 18000, 10]]],
            [2, '浴室リフォーム工事', 60, 'won', 'overdue', [
                ['ユニットバス本体(1616サイズ)', 1, '台', 680000, 10], ['解体・撤去工事', 1, '式', 85000, 10], ['給排水・電気工事', 1, '式', 120000, 10], ['組立・設置工事', 1, '式', 95000, 10]]],
            [3, '店舗カウンター造作工事', 40, 'won', 'unpaid', [
                ['カウンター造作(木製・L字)', 1, '式', 380000, 10], ['照明器具取付', 4, '台', 12000, 10], ['オープン記念配布用 焼き菓子(手配代行)', 30, '袋', 300, 8]]],
            [1, '共用廊下の照明LED化', 30, 'won', 'unpaid_recent', [
                ['LED照明器具', 24, '台', 9800, 10], ['取付工事', 24, '台', 4500, 10], ['既存器具撤去・処分', 1, '式', 30000, 10]]],
            [4, 'キッチン水栓交換', 20, 'lost', null, [
                ['混合水栓(シングルレバー)', 1, '個', 32000, 10], ['交換工事', 1, '式', 15000, 10]]],
            [5, '事務所の間仕切り工事', 12, 'sent', null, [
                ['軽量鉄骨間仕切り', 18, 'm', 14000, 10], ['石膏ボード・仕上げ', 36, '㎡', 5500, 10], ['ドア取付(片開き)', 2, '台', 65000, 10]]],
            [0, '外壁塗装工事(2階建てアパート)', 6, 'sent', null, [
                ['足場仮設', 320, '㎡', 900, 10], ['高圧洗浄', 280, '㎡', 250, 10], ['外壁塗装(シリコン・3回塗り)', 280, '㎡', 2800, 10], ['付帯部塗装', 1, '式', 150000, 10]]],
            [2, 'トイレ交換工事', 2, 'draft', null, [
                ['トイレ本体(節水型)', 1, '台', 98000, 10], ['交換工事', 1, '式', 38000, 10]]],
        ];

        foreach ($jobs as [$ci, $subject, $daysAgo, $quoteStatus, $invoice, $items]) {
            $issued = $today->subDays($daysAgo);
            $rows = array_map(fn ($i) => ['name' => $i[0], 'quantity' => $i[1], 'unit' => $i[2], 'unit_price' => $i[3], 'tax_rate' => $i[4]], $items);

            $quote = $service->save($user, new Document(['type' => 'quote']), [
                'type' => 'quote', 'client_id' => $clients[$ci]->id, 'subject' => $subject,
                'issue_date' => $issued->toDateString(), 'due_date' => $issued->addDays(30)->toDateString(),
                'status' => $quoteStatus, 'notes' => '工期:ご契約後、約2週間を予定しています。',
            ], $rows);

            if (! $invoice) {
                continue;
            }
            // 請求書は見積の2週間後(期限切れのものは、期限が過ぎるように早めに出したことにする)
            $invIssued = match ($invoice) {
                'overdue' => $today->subDays(45),
                'unpaid_recent' => $today->subDays(3),
                default => $issued->addDays(14),
            };
            $service->save($user, new Document(['type' => 'invoice']), [
                'type' => 'invoice', 'client_id' => $clients[$ci]->id, 'subject' => $subject,
                'issue_date' => $invIssued->toDateString(), 'due_date' => $invIssued->addDays(30)->toDateString(),
                'transaction_date' => $invIssued->subDays(2)->toDateString(), // 工事が終わった日
                'status' => $invoice === 'paid' ? 'paid' : 'unpaid',
                'paid_on' => $invoice === 'paid' ? $invIssued->addDays(20)->toDateString() : null,
                'source_id' => $quote->id,
            ], $rows);
        }
    }
}
