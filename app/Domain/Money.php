<?php

namespace App\Domain;

use InvalidArgumentException;
use OverflowException;

/**
 * Pul qiymati. Butun tiyinda saqlanadi, float ishlatilmaydi.
 * Foiz va ko'paytirish bcmath orqali bajariladi, shuning uchun katta summalarda ham aniq.
 */
final readonly class Money
{
    private const MAX_WHOLE_DIGITS = 13;

    private function __construct(public int $minor) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofMinor(int $minor): self
    {
        return new self($minor);
    }

    /** "2500", "2,50", "2 500.05". Ikki xonadan ortiq yoki manfiy qiymat qabul qilinmaydi. */
    public static function parse(string $input): self
    {
        return new self(self::parseScaled($input));
    }

    /** Foizni ham shu yo'l bilan o'qiymiz: "33,33" => 3333 (basis point). */
    public static function parseScaled(string $input): int
    {
        $value = preg_replace('/[\s\x{00A0}]/u', '', $input);

        if (! preg_match('/^(\d+)(?:[.,](\d{1,2}))?$/', (string) $value, $m)) {
            throw new InvalidArgumentException("Noto'g'ri qiymat: {$input}");
        }

        if (strlen(ltrim($m[1], '0')) > self::MAX_WHOLE_DIGITS) {
            throw new InvalidArgumentException("Qiymat juda katta: {$input}");
        }

        return (int) bcadd(bcmul($m[1], '100'), str_pad($m[2] ?? '0', 2, '0'));
    }

    public function plus(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function minus(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    public function times(int $quantity): self
    {
        $result = bcmul((string) $this->minor, (string) $quantity);

        if (bccomp($result, (string) PHP_INT_MAX) > 0) {
            throw new OverflowException('Summa juda katta.');
        }

        return new self((int) $result);
    }

    /** Foizni oladi va 2 xonagacha yaxlitlaydi (0,005 va undan yuqorisi yuqoriga). 100% = 10000. */
    public function percent(int $basisPoints): self
    {
        $scaled = bcmul((string) $this->minor, (string) $basisPoints);

        return new self((int) bcdiv(bcadd($scaled, '5000'), '10000', 0));
    }

    public function min(self $other): self
    {
        return $this->minor <= $other->minor ? $this : $other;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        return $this->minor < $other->minor;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    /** Ko'rsatish uchun: "1 234,50". */
    public function format(): string
    {
        return ($this->minor < 0 ? '-' : '').
            preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $this->wholePart()).','.$this->fractionPart();
    }

    /** Forma qiymati uchun: "1234.50". */
    public function plain(): string
    {
        return ($this->minor < 0 ? '-' : '').$this->wholePart().'.'.$this->fractionPart();
    }

    private function wholePart(): string
    {
        return (string) intdiv(abs($this->minor), 100);
    }

    private function fractionPart(): string
    {
        return str_pad((string) (abs($this->minor) % 100), 2, '0', STR_PAD_LEFT);
    }
}
