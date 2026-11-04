<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 自社の情報(見積書・請求書の発行元として印字する)
        Schema::table('users', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable()->after('name');
            $table->string('address')->nullable()->after('postal_code');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('invoice_number', 14)->nullable()->after('phone');   // 適格請求書発行事業者の登録番号(T+13桁)
            $table->text('bank_account')->nullable()->after('invoice_number');   // 振込先
            $table->string('rounding', 10)->default('floor')->after('bank_account'); // 消費税の端数処理 floor/round/ceil
            $table->unsignedSmallInteger('quote_valid_days')->default(30)->after('rounding');   // 見積の有効期限(日数)
            $table->unsignedSmallInteger('payment_due_days')->default(30)->after('quote_valid_days'); // 支払期限(日数)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['postal_code', 'address', 'phone', 'invoice_number', 'bank_account', 'rounding', 'quote_valid_days', 'payment_due_days']);
        });
    }
};
