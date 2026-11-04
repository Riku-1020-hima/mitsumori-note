<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 見積書と請求書(type で見分ける)
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);                 // quote(見積書) / invoice(請求書)
            $table->string('number', 30);               // 見-2026-001 など
            $table->string('subject');                  // 件名
            $table->date('issue_date');                 // 発行日
            $table->date('due_date')->nullable();       // 見積:有効期限 / 請求:お支払期限
            $table->string('status', 20);               // 見積:draft/sent/won/lost 請求:unpaid/paid
            $table->date('paid_on')->nullable();        // 入金日
            $table->text('notes')->nullable();          // 備考
            $table->foreignId('source_id')->nullable()->constrained('documents')->nullOnDelete(); // 元になった見積書
            // 一覧を速くするため、保存のたびに合計を入れておく
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'type', 'status']);
        });

        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');                         // 品目
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 10)->nullable();         // 式・個・時間など
            $table->unsignedBigInteger('unit_price')->default(0); // 単価(円・税抜)
            $table->unsignedTinyInteger('tax_rate')->default(10); // 10 / 8(軽減税率)/ 0(非課税)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_items');
        Schema::dropIfExists('documents');
    }
};
