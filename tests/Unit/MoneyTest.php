<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parses_and_formats_without_float_drift()
    {
        $this->assertSame(12345, Money::toMinor('123.45'));
        $this->assertSame(10, Money::toMinor('0.1'));
        $this->assertSame(-250, Money::toMinor('-2.5'));
        $this->assertSame(13, Money::toMinor('0.125'));
        $this->assertSame(500, Money::toMinor(5));
        $this->assertSame('0.30', Money::fromMinor(Money::toMinor('0.1') + Money::toMinor('0.2')));
        $this->assertSame('-1.05', Money::fromMinor(-105));
    }

    public function test_multiplication_and_percentages_round_half_up()
    {
        $this->assertSame(Money::toMinor('1000.00'), Money::multiply(Money::toMinor('10'), Money::toMinor('100')));
        $this->assertSame(Money::toMinor('3.70'), Money::multiply(Money::toMinor('3.33'), Money::toMinor('1.11')));
        $this->assertSame(Money::toMinor('90.00'), Money::percentOf(Money::toMinor('1000'), Money::toMinor('9')));
        $this->assertSame(Money::toMinor('0.13'), Money::percentOf(Money::toMinor('2.50'), Money::toMinor('5')));
    }

    public function test_rejects_malformed_input()
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toMinor('12abc');
    }
}
