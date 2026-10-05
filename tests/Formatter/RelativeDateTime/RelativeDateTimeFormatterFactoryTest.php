<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\RelativeDateTime;

use DateTimeZone;
use DateTimeImmutable;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter;
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatterFactory;
use Dirthara\I18n\Contract\Factory\RelativeDateTimeFormatterFactory as RelativeDateTimeFormatterFactoryContract;

final class RelativeDateTimeFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_relative_date_time_formatter_factory_contract(): void
    {
        self::assertInstanceOf(RelativeDateTimeFormatterFactoryContract::class, new RelativeDateTimeFormatterFactory());
    }

    #[Test]
    public function it_creates_a_relative_date_time_formatter_for_a_locale_and_timezone(): void
    {
        $locale = new Locale('de-DE');
        $timezone = new DateTimeZone('Europe/Berlin');
        $now = new DateTimeImmutable('2026-10-05 14:30', $timezone);

        $formatter = new RelativeDateTimeFormatterFactory()->create($locale, $timezone);

        self::assertInstanceOf(RelativeDateTimeFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame($timezone, $formatter->timezone);
        self::assertSame('vor 3 Tagen', $formatter->format($now->modify('-3 days'), $now));
    }
}
