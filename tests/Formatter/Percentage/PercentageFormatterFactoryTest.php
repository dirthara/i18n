<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Percentage;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\Percentage\PercentageFormatter;
use Dirthara\I18n\Formatter\Percentage\PercentageFormatterFactory;
use Dirthara\I18n\Contract\Factory\PercentageFormatterFactory as PercentageFormatterFactoryContract;

final class PercentageFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_percentage_formatter_factory_contract(): void
    {
        self::assertInstanceOf(PercentageFormatterFactoryContract::class, new PercentageFormatterFactory());
    }

    #[Test]
    public function it_creates_a_percentage_formatter_for_a_locale(): void
    {
        $locale = new Locale('tr-TR');

        $formatter = new PercentageFormatterFactory()->create($locale);

        self::assertInstanceOf(PercentageFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame('%25', $formatter->format(0.25));
    }
}
