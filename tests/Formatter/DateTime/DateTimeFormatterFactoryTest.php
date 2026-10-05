<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\DateTime;

use DateTimeZone;
use DateTimeImmutable;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Formatter\DateTime\DateTimeFormatter;
use Dirthara\I18n\Formatter\DateTime\DateTimeFormatterFactory;
use Dirthara\I18n\Contract\Factory\DateTimeFormatterFactory as DateTimeFormatterFactoryContract;

final class DateTimeFormatterFactoryTest extends TestCase
{
    #[Test]
    public function it_implements_the_date_time_formatter_factory_contract(): void
    {
        self::assertInstanceOf(DateTimeFormatterFactoryContract::class, new DateTimeFormatterFactory());
    }

    #[Test]
    public function it_creates_a_date_time_formatter_for_a_locale_and_timezone(): void
    {
        $locale = new Locale('nl-NL');
        $timezone = new DateTimeZone('Europe/Amsterdam');

        $formatter = new DateTimeFormatterFactory()->create($locale, $timezone);

        self::assertInstanceOf(DateTimeFormatter::class, $formatter);
        self::assertSame($locale, $formatter->locale);
        self::assertSame($timezone, $formatter->timezone);
        self::assertSame('5 okt 2026', $formatter->formatDate(new DateTimeImmutable('2026-10-05 12:00', $timezone)));
    }
}
