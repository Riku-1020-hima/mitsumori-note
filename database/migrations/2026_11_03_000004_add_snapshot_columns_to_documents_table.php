<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // 取引年月日(納品日・作業完了日)。インボイス制度の請求書に必要
            $table->date('transaction_date')->nullable()->after('due_date');
            // 書類を作った時点の「自社の情報」と「宛名」。あとで設定や取引先を変えても、出した書類は変わらない
            $table->json('issuer')->nullable()->after('notes');
            $table->json('recipient')->nullable()->after('issuer');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['transaction_date', 'issuer', 'recipient']);
        });
    }
};
