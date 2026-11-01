<?php

namespace Tests\Feature;

use App\Domain\Accounting\BudgetLedger;
use App\Domain\Accounting\PaymentSchedule;
use App\Domain\Accounting\PaymentService;
use App\Domain\Money;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsContracts;
use Tests\TestCase;

/**
 * Haqiqiy poyga: har bir buxgalter alohida PHP jarayon, baza umumiy SQLite fayli.
 * Bitta jarayon ichidagi test qulfni ko'rsata olmaydi, chunki u hech qachon kutmaydi.
 */
class ConcurrencyTest extends TestCase
{
    use BuildsContracts;

    private const WORKERS = 4;

    private string $database;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = tempnam(sys_get_temp_dir(), 'concurrency').'.sqlite';
        touch($this->database);

        config(['database.connections.sqlite.database' => $this->database]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);

        $this->travelTo('2026-03-20 12:00:00');
        $this->accountant = $this->user(Role::Accountant);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        array_map('unlink', glob($this->database.'*'));

        parent::tearDown();
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<string> har bir jarayonning javobi
     */
    private function race(array $tasks): array
    {
        DB::disconnect('sqlite');

        $barrier = $this->database.'.barrier';
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database];
        $script = base_path('tests/Support/concurrency_worker.php');

        $processes = [];
        foreach ($tasks as $worker => $task) {
            $task['user_id'] = $this->accountant->id;
            $process = new Process([PHP_BINARY, $script, $barrier, (string) $worker, json_encode($task)], base_path(), $env, null, 60);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 30;
        while (count(glob("{$barrier}.ready.*")) < count($tasks) && microtime(true) < $deadline) {
            usleep(1000);
        }
        touch("{$barrier}.go");

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

            return $process->getOutput();
        }, $processes);
    }

    /** @param list<string> $results */
    private function assertExactlyOneWon(array $results, string $loserMessage): void
    {
        $winners = array_filter($results, fn (string $result) => $result === 'OK');
        $losers = array_diff_key($results, $winners);

        $this->assertCount(1, $winners, implode(PHP_EOL, $results));

        foreach ($losers as $result) {
            $this->assertStringContainsString($loserMessage, $result);
        }
    }

    public function test_only_one_accountant_gets_the_last_budget_balance_of_a_month(): void
    {
        $march = $this->budget(3, '750');
        $tasks = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $tasks[] = [
                'action' => 'pay',
                'stage_id' => $this->stage($this->activeContract(), 1)->id,
                'budget_line_id' => $march->id,
                'amount' => '750',
            ];
        }

        $results = $this->race($tasks);

        $this->assertExactlyOneWon($results, 'smeta qoldig\'idan ko\'p');
        $this->assertSame(1, Payment::count());
        $this->assertSame('0,00', app(BudgetLedger::class)->remaining($march)->format());
    }

    public function test_only_one_accountant_pays_the_same_stage(): void
    {
        $march = $this->budget(3, '100000');
        $contract = $this->activeContract();
        $task = [
            'action' => 'pay',
            'stage_id' => $this->stage($contract, 1)->id,
            'budget_line_id' => $march->id,
            'amount' => '750',
        ];

        $results = $this->race(array_fill(0, self::WORKERS, $task));

        $this->assertExactlyOneWon($results, 'qolgan summasidan ko\'p');
        $this->assertSame(1, Payment::count());
        $this->assertTrue(app(PaymentSchedule::class)->for($contract->fresh())[0]->remaining->isZero());
    }

    public function test_only_one_void_of_the_same_payment_succeeds(): void
    {
        $march = $this->budget();
        $contract = $this->activeContract();
        $payment = app(PaymentService::class)->record(
            $this->stage($contract, 1), $march, Money::parse('750'),
            CarbonImmutable::parse('2026-03-20'), null, $this->accountant,
        );

        $results = $this->race(array_fill(0, self::WORKERS, ['action' => 'void', 'payment_id' => $payment->id]));

        $this->assertExactlyOneWon($results, 'allaqachon bekor qilingan');
        $this->assertSame('3 000,00', app(BudgetLedger::class)->remaining($march)->format());
    }
}
