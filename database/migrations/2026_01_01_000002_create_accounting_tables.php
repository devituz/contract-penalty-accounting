<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('expense_item');
            $table->unsignedTinyInteger('month');
            $table->bigInteger('allocated');
            $table->timestamps();

            $table->unique(['fiscal_year', 'expense_item', 'month']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_line_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['budget_line_id', 'voided_at']);
            $table->index(['payment_stage_id', 'voided_at']);
        });

        // Reestr baza darajasida ham himoyalangan: kod chetlab o'tilsa ham yozuv o'chmaydi va o'zgarmaydi.
        // Ruxsat etilgan yagona o'zgarish: faol yozuvni bir marta bekor qilish.
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER payments_no_delete BEFORE DELETE ON payments
                BEGIN
                    SELECT RAISE(ABORT, 'Reestr yozuvi o''chirilmaydi');
                END;

                CREATE TRIGGER payments_immutable BEFORE UPDATE ON payments
                WHEN OLD.voided_at IS NOT NULL
                    OR NEW.amount <> OLD.amount
                    OR NEW.paid_on <> OLD.paid_on
                    OR NEW.payment_stage_id <> OLD.payment_stage_id
                    OR NEW.budget_line_id <> OLD.budget_line_id
                    OR NEW.created_by <> OLD.created_by
                    OR NEW.reference IS NOT OLD.reference
                BEGIN
                    SELECT RAISE(ABORT, 'Reestr yozuvi o''zgartirilmaydi');
                END;
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('budget_lines');
    }
};
