<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests;

use Dirthara\I18n\Money;
use Dirthara\I18n\Currency;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MoneyTest extends TestCase
{
    #[Test]
    public function it_holds_an_amount_in_a_currency(): void
    {
        $currency = new Currency('EUR');
        $money = new Money(1999, $currency);

        self::assertSame(1999, $money->amount);
        self::assertSame($currency, $money->currency);
    }

    #[Test]
    public function it_holds_a_negative_amount(): void
    {
        self::assertSame(-500, new Money(-500, new Currency('EUR'))->amount);
    }

    #[Test]
    public function it_equals_money_with_the_same_amount_and_currency(): void
    {
        $money = new Money(1999, new Currency('EUR'));

        self::assertTrue($money->equals($money));
        self::assertTrue($money->equals(new Money(1999, new Currency('eur'))));
    }

    #[Test]
    public function it_does_not_equal_money_with_another_amount(): void
    {
        self::assertFalse(new Money(1999, new Currency('EUR'))->equals(new Money(2000, new Currency('EUR'))));
    }

    #[Test]
    public function it_does_not_equal_money_in_another_currency(): void
    {
        self::assertFalse(new Money(1999, new Currency('EUR'))->equals(new Money(1999, new Currency('USD'))));
    }
}
