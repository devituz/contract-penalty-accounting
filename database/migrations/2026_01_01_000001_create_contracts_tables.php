<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pul ustunlari butun tiyinda (bigInteger). SQLite'da decimal aslida REAL bo'lib, float xatosini beradi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('supplier');
            $table->date('start_date');
            $table->unsignedInteger('term_days');
            $table->string('status')->default('draft')->index();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('paid_qty');
            $table->unsignedInteger('bonus_qty')->default(0);
            $table->bigInteger('price');
            $table->timestamps();
        });

        Schema::create('payment_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('name');
            $table->unsignedInteger('percent_bp'); // 100% = 10000
            $table->string('condition');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'position']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->date('delivered_on');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('paid_qty')->default(0);
            $table->unsignedInteger('bonus_qty')->default(0);
            $table->timestamps();
        });

        // Boshliq qarorlari jurnali: yangi qaror eskisini almashtiradi, tarix saqlanadi.
        Schema::create('penalty_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->date('as_of');
            $table->bigInteger('calculated');
            $table->bigInteger('approved')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_decisions');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('payment_stages');
        Schema::dropIfExists('contract_lines');
        Schema::dropIfExists('contracts');
    }
};
