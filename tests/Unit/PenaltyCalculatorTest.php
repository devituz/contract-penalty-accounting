<?php

namespace Tests\Unit;

use App\Domain\Money;
use App\Domain\Penalty\PenaltyCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PenaltyCalculatorTest extends TestCase
{
    private function date(string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day);
    }

    /** @param array<string, string> $receipts sana => kelgan pullik tovar qiymati */
    private function calc(string $asOf, array $receipts = [], string $total = '2500', string $due = '2026-03-12')
    {
        $list = [];
        foreach ($receipts as $day => $value) {
            $list[] = ['date' => $this->date($day), 'value' => Money::parse($value)];
        }

        return (new PenaltyCalculator)->calculate($this->date($due), Money::parse($total), $list, $this->date($asOf));
    }

    public function test_task_example_1_nothing_delivered(): void
    {
        $result = $this->calc('2026-03-15');

        $this->assertSame('37,50', $result->total->format());
        $this->assertSame(3, $result->delayDays);
        $this->assertSame('2026-03-12', $result->dueDate->toDateString());
        $this->assertSame('1 250,00', $result->limit->format());
        $this->assertSame(['2026-03-13', '2026-03-14', '2026-03-15'], array_map(fn ($d) => $d->date->toDateString(), $result->days));
        $this->assertSame('12,50', $result->days[0]->penalty->format());
        $this->assertSame('2 500,00', $result->days[0]->undelivered->format());
    }

    public function test_task_example_2_partial_delivery_counts_from_next_day(): void
    {
        $result = $this->calc('2026-03-16', ['2026-03-14' => '1500']);

        $this->assertSame('35,00', $result->total->format());
        $this->assertSame(['12,50', '12,50', '5,00', '5,00'], array_map(fn ($d) => $d->penalty->format(), $result->days));
        $this->assertSame('1 000,00', $result->days[2]->undelivered->format());
    }

    public function test_as_of_before_deadline_gives_no_penalty(): void
    {
        $result = $this->calc('2026-03-05');

        $this->assertSame('0,00', $result->total->format());
        $this->assertSame(0, $result->delayDays);
        $this->assertSame([], $result->days);
    }

    public function test_deadline_day_itself_is_not_late(): void
    {
        $this->assertSame('0,00', $this->calc('2026-03-12')->total->format());
    }

    public function test_as_of_day_is_included(): void
    {
        $this->assertSame('12,50', $this->calc('2026-03-13')->total->format());
    }

    public function test_batch_on_deadline_day_reduces_first_late_day_and_limit(): void
    {
        $result = $this->calc('2026-03-13', ['2026-03-12' => '2000']);

        $this->assertSame('500,00', $result->days[0]->undelivered->format());
        $this->assertSame('2,50', $result->total->format());
        $this->assertSame('250,00', $result->limit->format());
    }

    public function test_batch_after_deadline_does_not_reduce_limit(): void
    {
        $this->assertSame('1 250,00', $this->calc('2026-03-20', ['2026-03-14' => '1500'])->limit->format());
    }

    public function test_full_delivery_on_day_x_still_charges_day_x_but_not_after(): void
    {
        $result = $this->calc('2026-04-01', ['2026-03-14' => '2500']);

        $this->assertSame('25,00', $result->total->format());
        $this->assertSame(2, $result->delayDays);
    }

    public function test_full_delivery_before_deadline_has_no_penalty(): void
    {
        $this->assertSame('0,00', $this->calc('2026-04-01', ['2026-03-10' => '2500'])->total->format());
    }

    public function test_batches_may_arrive_out_of_date_order(): void
    {
        $result = $this->calc('2026-03-16', ['2026-03-15' => '500', '2026-03-13' => '1000']);

        // 13: 2500, 14: 1500, 15: 1500, 16: 1000
        $this->assertSame(['12,50', '7,50', '7,50', '5,00'], array_map(fn ($d) => $d->penalty->format(), $result->days));
    }

    public function test_each_day_is_rounded_before_summing(): void
    {
        // Kelmagan 3,00: kuniga 0,015 => 0,02. Ikki kun 0,04 (jamini yaxlitlasak 0,03 bo'lardi).
        $result = $this->calc('2026-03-14', [], '3,00');

        $this->assertSame('0,04', $result->total->format());
    }

    public function test_total_is_capped_exactly_at_limit(): void
    {
        // Limit 1250, kuniga 12,50: 100-kuni aynan limitga yetadi.
        $atLimit = $this->calc('2026-06-20');
        $beyond = $this->calc('2026-12-31');

        $this->assertSame('1 250,00', $atLimit->total->format());
        $this->assertTrue($atLimit->limitReached);
        $this->assertSame('1 250,00', $beyond->total->format());
        $this->assertSame(294, $beyond->delayDays);
        $this->assertSame('0,00', $beyond->days[120]->penalty->format());
        $this->assertSame('12,50', $beyond->days[120]->accrued->format());
    }

    public function test_cap_cuts_the_last_day_partially(): void
    {
        // 99 kun × 12,50 = 1237,50. 19-iyundagi partiyadan keyin kuniga 8,00:
        // 20-iyun 1245,50, 21-iyunda faqat 4,50 qo'shiladi va limitga yetadi.
        $result = $this->calc('2026-06-25', ['2026-06-19' => '900']);

        $this->assertSame('1 250,00', $result->total->format());
        $this->assertSame('8,00', $result->days[99]->penalty->format());
        $this->assertSame('4,50', $result->days[100]->penalty->format());
        $this->assertSame('8,00', $result->days[100]->accrued->format());
        $this->assertSame('0,00', $result->days[101]->penalty->format());
    }

    public function test_table_sum_always_equals_total(): void
    {
        $result = $this->calc('2026-08-01', ['2026-03-14' => '1500', '2026-05-01' => '999,99']);

        $sum = array_reduce($result->days, fn ($carry, $d) => $carry->plus($d->penalty), Money::zero());

        $this->assertTrue($sum->equals($result->total));
    }

    public function test_year_boundary_and_leap_day_are_counted(): void
    {
        $result = $this->calc('2028-03-02', [], '2500', '2028-02-27');

        $this->assertSame(['2028-02-28', '2028-02-29', '2028-03-01', '2028-03-02'], array_map(fn ($d) => $d->date->toDateString(), $result->days));
    }
}
