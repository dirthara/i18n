<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Currency;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\Currency\CurrencyFormatter;
use Dirthara\I18n\Formatter\Currency\CurrencyFormatterFactory;
use Dirthara\I18n\Contract\Factory\CurrencyFormatterFactory as CurrencyFormatterFactoryContract;

final class CurrencyFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_currency_formatter_factory_contract(): void
    {
        self::assertInstanceOf(CurrencyFormatterFactoryContract::class, new CurrencyFormatterFactory());
    }

    #[Test]
    public function it_creates_a_currency_formatter_for_a_locale(): void
    {
        $locale = new Locale('nl-NL');

        $formatter = new CurrencyFormatterFactory()->create($locale);

        self::assertInstanceOf(CurrencyFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
    }

    #[Test]
    public function it_creates_a_formatter_per_locale(): void
    {
        $factory = new CurrencyFormatterFactory();
        $euro = new Currency('EUR');

        self::assertSame("\u{20AC}12.50", $factory->create(new Locale('en-GB'))->format(12.5, $euro));
        self::assertSame("\u{20AC}\u{00A0}12,50", $factory->create(new Locale('nl-NL'))->format(12.5, $euro));
        self::assertSame("12,50\u{00A0}\u{20AC}", $factory->create(new Locale('fr-FR'))->format(12.5, $euro));
    }
}
