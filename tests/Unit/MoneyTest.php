<?php

namespace Tests\Unit;

use App\Domain\Money;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('validInputs')]
    public function test_parse_keeps_exact_minor_units(string $input, int $minor): void
    {
        $this->assertSame($minor, Money::parse($input)->minor);
    }

    public static function validInputs(): array
    {
        return [
            ['2,50', 250],
            ['2.50', 250],
            ['2500', 250000],
            ['2 500,05', 250005],
            ['0,1', 10],
            ['1234567890123,45', 123456789012345],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_parse_rejects_malformed_or_over_precise_values(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parse($input);
    }

    public static function invalidInputs(): array
    {
        return [[''], ['abc'], ['1,234'], ['1.2.3'], ['1e3'], ['-5'], ['99999999999999']];
    }

    public function test_sum_of_tenths_is_exact_where_float_is_not(): void
    {
        $sum = Money::parse('0,10')->plus(Money::parse('0,20'));

        $this->assertTrue($sum->equals(Money::parse('0,30')));
    }

    #[DataProvider('rounding')]
    public function test_percent_rounds_half_up_to_two_decimals(string $amount, int $basisPoints, string $expected): void
    {
        $this->assertSame($expected, Money::parse($amount)->percent($basisPoints)->format());
    }

    public static function rounding(): array
    {
        return [
            'tam 0,005 yuqoriga' => ['1,00', 50, '0,01'],
            '0,0049 pastga' => ['0,98', 50, '0,00'],
            '0,015 yuqoriga' => ['3,00', 50, '0,02'],
            '12,50' => ['2500,00', 50, '12,50'],
            '50 foiz' => ['2500,00', 5000, '1 250,00'],
            '30 foiz' => ['2500,00', 3000, '750,00'],
            'katta summa' => ['9000000000000,00', 9999, '8 999 100 000 000,00'],
        ];
    }

    public function test_percent_does_not_overflow_near_int_limit(): void
    {
        $this->assertSame(PHP_INT_MAX, Money::ofMinor(PHP_INT_MAX)->percent(10000)->minor);
    }

    public function test_times_refuses_overflow(): void
    {
        $this->expectException(OverflowException::class);

        Money::ofMinor(PHP_INT_MAX)->times(2);
    }

    public function test_format_groups_thousands_with_comma_decimals(): void
    {
        $this->assertSame('1 234 567,05', Money::ofMinor(123456705)->format());
        $this->assertSame('0,00', Money::zero()->format());
        $this->assertSame('-0,50', Money::ofMinor(-50)->format());
    }

    public function test_plain_round_trips_through_parse(): void
    {
        $money = Money::parse('2500,05');

        $this->assertSame('2500.05', $money->plain());
        $this->assertTrue($money->equals(Money::parse($money->plain())));
    }

    public function test_times_multiplies_by_quantity(): void
    {
        $this->assertSame('2 500,00', Money::parse('2,50')->times(1000)->format());
    }
}
