<?php

/**
 * Konkurent testning bitta "buxgalteri": alohida PHP jarayon.
 * Argumentlar: <to'siq fayli prefiksi> <worker raqami> <JSON topshiriq>
 */

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Domain\RuleViolation;
use App\Models\BudgetLine;
use App\Models\Payment;
use App\Models\PaymentStage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $barrier, $worker, $json] = $argv;
$task = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

// Tekshiruv va yozish orasidagi oynani kengaytiramiz: qulf bo'lmasa poyga kafolatli yutqaziladi.
$app->bind(BudgetLedger::class, fn () => new class extends BudgetLedger
{
    public function remaining(BudgetLine $line): Money
    {
        $remaining = parent::remaining($line);
        usleep(150_000);

        return $remaining;
    }
});

$actor = User::findOrFail($task['user_id']);

// Hamma jarayon yuklanib bo'lgach, bir vaqtda boshlaymiz.
touch("{$barrier}.ready.{$worker}");
while (! file_exists("{$barrier}.go")) {
    usleep(500);
}

$service = $app->make(PaymentService::class);

try {
    if ($task['action'] === 'void') {
        $service->void(Payment::findOrFail($task['payment_id']), "Worker {$worker}", $actor);
    } else {
        $service->record(
            PaymentStage::findOrFail($task['stage_id']),
            BudgetLine::findOrFail($task['budget_line_id']),
            Money::parse($task['amount']),
            CarbonImmutable::parse('2026-03-20'),
            "Worker {$worker}",
            $actor,
        );
    }

    echo 'OK';
} catch (RuleViolation $e) {
    echo 'REJECTED: '.$e->getMessage();
}
