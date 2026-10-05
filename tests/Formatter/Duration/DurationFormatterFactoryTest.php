<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Duration;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\Duration\DurationFormatter;
use Dirthara\I18n\Formatter\Duration\DurationFormatterFactory;
use Dirthara\I18n\Contract\Factory\DurationFormatterFactory as DurationFormatterFactoryContract;

final class DurationFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_duration_formatter_factory_contract(): void
    {
        self::assertInstanceOf(DurationFormatterFactoryContract::class, new DurationFormatterFactory());
    }

    #[Test]
    public function it_creates_a_duration_formatter_for_a_locale(): void
    {
        $locale = new Locale('nl-NL');

        $formatter = new DurationFormatterFactory()->create($locale);

        self::assertInstanceOf(DurationFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame('2 minuten en 5 seconden', $formatter->format(125));
    }
}
