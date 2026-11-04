<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\User;
use App\Services\Calculator;
use App\Services\DocumentService;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MitsumoriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');
    }

    private function company(array $attrs = []): User
    {
        return User::create($attrs + ['name' => 'テスト工務店', 'email' => 'c'.uniqid().'@example.com', 'password' => 'password']);
    }

    private function client(User $u, string $name = '株式会社テスト'): Client
    {
        return $u->clients()->create(['name' => $name, 'honorific' => '御中']);
    }

    private function quotePayload(Client $client, ?array $items = null): array
    {
        return [
            'client_id' => $client->id,
            'subject' => '浴室リフォーム',
            'issue_date' => '2026-11-10',
            'due_date' => '2026-12-10',
            'items' => $items ?? [
                ['name' => 'ユニットバス', 'quantity' => '1', 'unit' => '台', 'unit_price' => '680,000', 'tax_rate' => '10'],
                ['name' => '差し入れ', 'quantity' => '2', 'unit' => '本', 'unit_price' => '¥150', 'tax_rate' => '8'],
                ['name' => '', 'quantity' => '', 'unit' => '', 'unit_price' => '', 'tax_rate' => '10'], // 空の行は無視
            ],
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('ミツモリノート');
    }

    public function test_calculator_sums_tax_per_rate_with_rounding(): void
    {
        $items = [
            ['quantity' => 3, 'unit_price' => 1001, 'tax_rate' => 10],
            ['quantity' => 1.5, 'unit_price' => 333, 'tax_rate' => 8],   // 499.5 → 500円
            ['quantity' => 1, 'unit_price' => 1100, 'tax_rate' => 10],
            ['quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0],
        ];
        $floor = Calculator::totals($items, 'floor');
        $this->assertSame(['base' => 4103, 'tax' => 410], $floor['rates'][10]);
        $this->assertSame(['base' => 500, 'tax' => 40], $floor['rates'][8]);
        $this->assertSame(['base' => 5000, 'tax' => 0], $floor['rates'][0]);
        $this->assertSame([9603, 450, 10053], [$floor['subtotal'], $floor['tax'], $floor['total']]);

        $this->assertSame(411, Calculator::totals($items, 'ceil')['rates'][10]['tax']);
        // 浮動小数点の誤差で切り上げにならない
        $this->assertSame(110, Calculator::totals([['quantity' => 1, 'unit_price' => 1100, 'tax_rate' => 10]], 'ceil')['tax']);
    }

    public function test_create_quote_with_items_and_totals(): void
    {
        $u = $this->company();
        $c = $this->client($u);

        $this->actingAs($u)->post('/quotes', $this->quotePayload($c))->assertRedirect();

        $q = Document::firstOrFail();
        $this->assertSame('quote', $q->type);
        $this->assertSame('Q2026-001', $q->number);
        $this->assertSame('draft', $q->status);
        $this->assertCount(2, $q->items);
        // 680,000 + 10% = 748,000 / 300 + 8% = 324 → 748,324
        $this->assertSame([680300, 68024, 748324], [$q->subtotal, $q->tax, $q->total]);

        $this->get("/quotes/{$q->id}")->assertOk()->assertSee('御見積書')->assertSee('¥748,324')->assertSee('軽減税率');
        $this->get('/quotes')->assertOk()->assertSee('浴室リフォーム');
    }

    public function test_numbers_count_up_per_type_and_year(): void
    {
        $u = $this->company();
        $s = app(DocumentService::class);
        $this->assertSame('Q2026-001', $s->nextNumber($u, 'quote'));
        $this->actingAs($u)->post('/quotes', $this->quotePayload($this->client($u)));
        $this->assertSame('Q2026-002', $s->nextNumber($u, 'quote'));
        $this->assertSame('INV2026-001', $s->nextNumber($u, 'invoice'));
        $this->assertSame('Q2027-001', $s->nextNumber($u, 'quote', \Carbon\CarbonImmutable::parse('2027-01-05')));
    }

    public function test_validation_requires_at_least_one_item(): void
    {
        $u = $this->company();
        $payload = $this->quotePayload($this->client($u), [['name' => '', 'quantity' => '1', 'unit_price' => '100', 'tax_rate' => '10']]);
        $this->actingAs($u)->post('/quotes', $payload)->assertSessionHasErrors('items');

        $payload = $this->quotePayload($this->client($u));
        $payload['due_date'] = '2026-11-01'; // 発行日より前
        $this->post('/quotes', $payload)->assertSessionHasErrors('due_date');
        $this->assertSame(0, Document::count());
    }

    public function test_convert_quote_to_invoice_and_mark_paid(): void
    {
        $u = $this->company(['payment_due_days' => 20]);
        $this->actingAs($u)->post('/quotes', $this->quotePayload($this->client($u)));
        $q = Document::firstOrFail();

        $this->post("/quotes/{$q->id}/convert")->assertRedirect();
        $inv = Document::where('type', 'invoice')->firstOrFail();
        $this->assertSame('INV2026-001', $inv->number);
        $this->assertSame($q->id, (int) $inv->source_id);
        $this->assertSame($q->total, $inv->total);
        $this->assertSame('2026-11-30', $inv->due_date->toDateString());
        $this->assertSame('won', $q->fresh()->status);

        // 2回押しても請求書は増えない
        $this->post("/quotes/{$q->id}/convert");
        $this->assertSame(1, Document::where('type', 'invoice')->count());

        $this->get("/invoices/{$inv->id}")->assertOk()->assertSee('請求書')->assertSee('入金済みにする');
        $this->post("/invoices/{$inv->id}/status", ['status' => 'paid', 'paid_on' => '2026-11-15']);
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertSame('2026-11-15', $inv->fresh()->paid_on->toDateString());

        $this->post("/invoices/{$inv->id}/status", ['status' => 'unpaid']);
        $this->assertNull($inv->fresh()->paid_on);
    }

    public function test_documents_keep_company_info_from_when_they_were_made(): void
    {
        $u = $this->company(['address' => '東京都 旧住所1-1', 'rounding' => 'ceil']);
        $c = $this->client($u, '旧社名株式会社');
        $this->actingAs($u)->post('/invoices', $this->quotePayload($c, [['name' => '作業', 'quantity' => '1', 'unit' => '式', 'unit_price' => '1001', 'tax_rate' => '10']]));
        $inv = Document::firstOrFail();
        $this->assertSame(101, $inv->tax); // 100.1 → 切り上げ

        // あとから住所・端数処理・取引先名を変える
        $u->update(['address' => '大阪府 新住所2-2', 'rounding' => 'floor']);
        $c->update(['name' => '新社名株式会社']);

        // 出した請求書は、作ったときのまま
        $this->get("/invoices/{$inv->id}")->assertOk()
            ->assertSee('東京都 旧住所1-1')->assertDontSee('大阪府 新住所2-2')
            ->assertSee('旧社名株式会社 御中')->assertSee('¥1,102');

        // 編集して保存し直すと、今の情報で作り直す
        $this->put("/invoices/{$inv->id}", $this->quotePayload($c, [['name' => '作業', 'quantity' => '1', 'unit' => '式', 'unit_price' => '1001', 'tax_rate' => '10']]));
        $this->get("/invoices/{$inv->id}")->assertSee('大阪府 新住所2-2')->assertSee('新社名株式会社 御中')->assertSee('¥1,101');
    }

    public function test_invoice_shows_transaction_date(): void
    {
        $u = $this->company();
        $payload = $this->quotePayload($this->client($u)) + ['transaction_date' => '2026-11-05'];
        $this->actingAs($u)->post('/invoices', $payload);
        $inv = Document::firstOrFail();
        $this->assertSame('2026-11-05', $inv->transaction_date->toDateString());
        $this->get("/invoices/{$inv->id}")->assertSee('取引年月日')->assertSee('2026年11月5日');

        // 空なら発行日を印字する
        $this->post('/invoices', $this->quotePayload($this->client($u)));
        $second = Document::latest('id')->first();
        $this->assertNull($second->transaction_date);
        $this->get("/invoices/{$second->id}")->assertSee('2026年11月10日');

        // 見積書には取引年月日を出さない
        $this->post('/quotes', $payload);
        $quote = Document::where('type', 'quote')->firstOrFail();
        $this->assertNull($quote->transaction_date);
        $this->get("/quotes/{$quote->id}")->assertDontSee('取引年月日');
    }

    public function test_invoices_are_voided_not_deleted(): void
    {
        $u = $this->company();
        $this->actingAs($u)->post('/invoices', $this->quotePayload($this->client($u)));
        $inv = Document::firstOrFail();

        $this->delete("/invoices/{$inv->id}")->assertSessionHas('status');
        $this->assertNotNull(Document::find($inv->id)); // 消えない

        $this->post("/invoices/{$inv->id}/status", ['status' => 'void']);
        $this->assertTrue($inv->fresh()->isVoid());
        $this->get("/invoices/{$inv->id}")->assertSee('取り消し済み');
        $this->get('/invoices?status=void')->assertSee('浴室リフォーム');
        // 合計・未入金に数えない
        $this->get('/')->assertSee('¥0');
        $this->assertSame(0, $u->documents()->overdue()->count());

        // 次の請求書の番号は飛ばない(取り消しても番号は使ったまま)
        $this->assertSame('INV2026-002', app(DocumentService::class)->nextNumber($u, 'invoice'));

        $this->post("/invoices/{$inv->id}/status", ['status' => 'unpaid']);
        $this->assertSame('unpaid', $inv->fresh()->status);
    }

    public function test_paid_and_void_invoices_cannot_be_edited(): void
    {
        $u = $this->company();
        $c = $this->client($u);
        $this->actingAs($u)->post('/invoices', $this->quotePayload($c));
        $inv = Document::firstOrFail();

        // 未入金なら直せる(注意書きつき)
        $this->get("/invoices/{$inv->id}/edit")->assertOk()->assertSee('すでにお客さんに送った請求書');

        foreach (['paid', 'void'] as $status) {
            $this->post("/invoices/{$inv->id}/status", ['status' => $status]);
            $this->get("/invoices/{$inv->id}/edit")->assertRedirect("/invoices/{$inv->id}");
            $this->put("/invoices/{$inv->id}", $this->quotePayload($c, [['name' => '書き換え', 'quantity' => '1', 'unit_price' => '1', 'tax_rate' => '10']]))
                ->assertRedirect("/invoices/{$inv->id}");
            $this->assertSame(748324, $inv->fresh()->total); // 変わらない
            $this->get("/invoices/{$inv->id}")->assertDontSee("/invoices/{$inv->id}/edit");
            $this->post("/invoices/{$inv->id}/status", ['status' => 'unpaid']);
        }

        // 見積書は受注後も直せる
        $this->post('/quotes', $this->quotePayload($c));
        $q = Document::where('type', 'quote')->firstOrFail();
        $this->post("/quotes/{$q->id}/status", ['status' => 'won']);
        $this->get("/quotes/{$q->id}/edit")->assertOk();
    }

    public function test_overdue_invoices_show_on_dashboard(): void
    {
        $u = $this->company();
        $c = $this->client($u, '遅れ商事');
        app(DocumentService::class)->save($u, new Document(['type' => 'invoice']), [
            'type' => 'invoice', 'client_id' => $c->id, 'subject' => '修繕工事', 'issue_date' => '2026-10-01',
            'due_date' => '2026-10-31', 'status' => 'unpaid',
        ], [['name' => '修繕', 'quantity' => 1, 'unit' => '式', 'unit_price' => 10000, 'tax_rate' => 10]]);

        $this->actingAs($u)->get('/')->assertOk()->assertSee('遅れ商事')->assertSee('期限切れ');
        $this->get('/invoices?status=overdue')->assertOk()->assertSee('修繕工事');
        $this->assertSame(1, $u->documents()->overdue()->count());
    }

    public function test_edit_duplicate_and_delete(): void
    {
        $u = $this->company();
        $c = $this->client($u);
        $this->actingAs($u)->post('/quotes', $this->quotePayload($c));
        $q = Document::firstOrFail();

        $this->get("/quotes/{$q->id}/edit")->assertOk()->assertSee('ユニットバス');
        $payload = $this->quotePayload($c, [['name' => '変更後', 'quantity' => '2', 'unit' => '式', 'unit_price' => '1000', 'tax_rate' => '10']]);
        $this->put("/quotes/{$q->id}", $payload)->assertRedirect("/quotes/{$q->id}");
        $this->assertSame(2200, $q->fresh()->total);
        $this->assertSame('Q2026-001', $q->fresh()->number); // 番号は変わらない

        $this->post("/quotes/{$q->id}/duplicate")->assertRedirect();
        $copy = Document::where('id', '!=', $q->id)->firstOrFail();
        $this->assertSame('Q2026-002', $copy->number);
        $this->assertSame(2200, $copy->total);

        $this->delete("/quotes/{$q->id}")->assertRedirect('/quotes');
        $this->assertNull(Document::find($q->id));
    }

    public function test_other_companies_cannot_see_documents(): void
    {
        $a = $this->company();
        $b = $this->company();
        $this->actingAs($b)->post('/quotes', $this->quotePayload($this->client($b)));
        $q = Document::firstOrFail();
        $other = $this->client($b, '他社の取引先');

        $this->actingAs($a);
        $this->get("/quotes/{$q->id}")->assertNotFound();
        $this->get("/quotes/{$q->id}/pdf")->assertNotFound();
        $this->get("/invoices/{$q->id}")->assertNotFound(); // 見積書を請求書のURLで開いても出ない
        $this->get("/clients/{$other->id}")->assertNotFound();
        // 他社の取引先は宛先に選べない
        $this->post('/quotes', $this->quotePayload($other))->assertSessionHasErrors('client_id');
    }

    public function test_clients_crud_and_totals(): void
    {
        $u = $this->company();
        $this->actingAs($u)->post('/clients', ['name' => '山田 太郎', 'honorific' => '様'])->assertRedirect();
        $c = $u->clients()->firstOrFail();
        $this->assertSame('山田 太郎 様', $c->addressee);

        $this->post('/invoices', $this->quotePayload($c));
        $this->get('/clients')->assertOk()->assertSee('山田 太郎 様')->assertSee('¥748,324');
        $this->get("/clients/{$c->id}")->assertOk()->assertSee('浴室リフォーム');

        // 取引先を消しても書類は残る
        $this->delete("/clients/{$c->id}")->assertRedirect('/clients');
        $this->assertSame(1, Document::count());
        $this->assertNull(Document::first()->client_id);
    }

    public function test_settings_validate_invoice_number(): void
    {
        $u = $this->company();
        $base = ['name' => '新しい屋号', 'email' => $u->email, 'rounding' => 'round', 'quote_valid_days' => 14, 'payment_due_days' => 45];

        $this->actingAs($u)->put('/settings', $base + ['invoice_number' => '1234'])->assertSessionHasErrors('invoice_number');
        $this->put('/settings', $base + ['invoice_number' => 't1234 5678 90123'])->assertRedirect('/settings');

        $u->refresh();
        $this->assertSame('T1234567890123', $u->invoice_number);
        $this->assertSame('round', $u->rounding);
        $this->assertSame(45, $u->payment_due_days);
    }

    public function test_pdf_is_generated_with_japanese_font(): void
    {
        foreach (PdfService::FONTS as $file) {
            if (! is_file(resource_path('fonts/'.$file))) {
                $this->markTestSkipped('日本語フォントがないため、PDFのテストを飛ばします。');
            }
        }

        $u = $this->company(['invoice_number' => 'T1234567890123']);
        $this->actingAs($u)->post('/quotes', $this->quotePayload($this->client($u)));
        $q = Document::firstOrFail();

        $res = $this->get("/quotes/{$q->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('inline', $res->headers->get('Content-Disposition'));

        $this->get("/quotes/{$q->id}/pdf?download=1")->assertHeader('Content-Disposition');

        // 取り消した請求書のPDFも作れる(透かし入り)
        $this->post("/quotes/{$q->id}/convert");
        $inv = Document::where('type', 'invoice')->firstOrFail();
        $this->post("/invoices/{$inv->id}/status", ['status' => 'void']);
        $this->assertStringStartsWith('%PDF', $this->get("/invoices/{$inv->id}/pdf")->assertOk()->getContent());
    }

    public function test_demo_seed_renders_every_page(): void
    {
        $this->seed();
        $u = User::where('email', 'demo@example.com')->firstOrFail();
        $this->assertGreaterThan(0, $u->documents()->overdue()->count());

        $this->actingAs($u);
        $quote = $u->documents()->quotes()->first();
        $invoice = $u->documents()->invoices()->where('status', 'unpaid')->first(); // 編集できる請求書
        $paid = $u->documents()->invoices()->where('status', 'paid')->first();
        $client = $u->clients()->first();
        foreach (['/', '/quotes', '/quotes/create', '/invoices', '/invoices/create', '/invoices?status=overdue', '/clients', '/clients/create',
            "/clients/{$client->id}", "/clients/{$client->id}/edit", '/settings',
            "/quotes/{$quote->id}", "/quotes/{$quote->id}/edit", "/invoices/{$invoice->id}", "/invoices/{$invoice->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
        // 入金済みの請求書は、編集画面を開こうとしても請求書の画面に戻る
        $this->get("/invoices/{$paid->id}")->assertOk();
        $this->get("/invoices/{$paid->id}/edit")->assertRedirect("/invoices/{$paid->id}");
    }
}
