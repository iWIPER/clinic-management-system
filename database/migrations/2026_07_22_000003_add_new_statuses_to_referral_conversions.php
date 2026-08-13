<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Postgres nomeia a CHECK constraint de enum() como "<tabela>_<coluna>_check"
    // e não aceita ALTER COLUMN TYPE direto pra ampliar a lista de valores —
    // por isso precisa do DROP/ADD CONSTRAINT abaixo. SQLite (usado nos testes
    // via RefreshDatabase) não suporta essa sintaxe; lá o Schema::table()
    // ->enum()->change() já resolve nativamente recriando a tabela.
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE referral_conversions DROP CONSTRAINT referral_conversions_status_check');
            DB::statement("ALTER TABLE referral_conversions ADD CONSTRAINT referral_conversions_status_check CHECK (status IN ('testing', 'awaiting_payment', 'payment_confirmed', 'eligible', 'paid', 'cancelled', 'expired', 'refunded', 'under_review'))");

            return;
        }

        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->enum('status', [
                'testing',
                'awaiting_payment',
                'payment_confirmed',
                'eligible',
                'paid',
                'cancelled',
                'expired',
                'refunded',
                'under_review',
            ])->default('testing')->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE referral_conversions DROP CONSTRAINT referral_conversions_status_check');
            DB::statement("ALTER TABLE referral_conversions ADD CONSTRAINT referral_conversions_status_check CHECK (status IN ('testing', 'awaiting_payment', 'payment_confirmed', 'eligible', 'paid', 'cancelled', 'expired'))");

            return;
        }

        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->enum('status', [
                'testing',
                'awaiting_payment',
                'payment_confirmed',
                'eligible',
                'paid',
                'cancelled',
                'expired',
            ])->default('testing')->change();
        });
    }
};
