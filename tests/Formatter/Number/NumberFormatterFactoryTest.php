<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Number;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\Number\NumberFormatter;
use Dirthara\I18n\Formatter\Number\NumberFormatterFactory;
use Dirthara\I18n\Contract\Factory\NumberFormatterFactory as NumberFormatterFactoryContract;

final class NumberFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_number_formatter_factory_contract(): void
    {
        self::assertInstanceOf(NumberFormatterFactoryContract::class, new NumberFormatterFactory());
    }

    #[Test]
    public function it_creates_a_number_formatter_for_a_locale(): void
    {
        $locale = new Locale('nl-NL');

        $formatter = new NumberFormatterFactory()->create($locale);

        self::assertInstanceOf(NumberFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame('1.500', $formatter->format(1500));
    }
}
