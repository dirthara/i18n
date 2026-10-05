<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\RelativeDateTime;

use ValueError;
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Enum\RelativeDateTimeStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter;
use Dirthara\I18n\Contract\RelativeDateTimeFormatter as RelativeDateTimeFormatterContract;

final class RelativeDateTimeFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_relative_date_time_formatter_contract(): void
    {
        self::assertInstanceOf(RelativeDateTimeFormatterContract::class, $this->formatter('en-GB'));
    }

    #[Test]
    public function it_exposes_its_locale_and_timezone(): void
    {
        $locale = new Locale('nl-NL');
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $formatter = new RelativeDateTimeFormatter($locale, $timezone);

        self::assertSame($locale, $formatter->locale);
        self::assertSame($timezone, $formatter->timezone);
    }

    #[Test]
    #[DataProvider('relativeTimes')]
    public function it_formats_a_moment_relative_to_another(
        string $locale,
        string $offset,
        RelativeDateTimeStyle $style,
        string $expected,
    ): void {
        $now = $this->now();

        self::assertSame($expected, $this->formatter($locale)->format($now->modify($offset), $now, $style));
    }

    #[Test]
    public function it_formats_in_the_long_style_by_default(): void
    {
        $now = $this->now();

        self::assertSame('3 days ago', $this->formatter('en-GB')->format($now->modify('-3 days'), $now));
    }

    #[Test]
    public function it_counts_calendar_units_in_its_own_timezone(): void
    {
        $formatter = $this->formatter('en-GB');
        $before = new DateTimeImmutable('2026-10-24 12:00', new DateTimeZone('Europe/Amsterdam'));
        $after = new DateTimeImmutable('2026-10-25 12:00', new DateTimeZone('Europe/Amsterdam'));

        self::assertSame('in 1 day', $formatter->format($after, $before));
        self::assertSame('in 1 day', $formatter->format(
            $after->setTimezone(new DateTimeZone('Asia/Tokyo')),
            $before->setTimezone(new DateTimeZone('America/New_York')),
        ));
    }

    #[Test]
    public function it_formats_the_same_moment_again_from_its_cache(): void
    {
        $formatter = $this->formatter('nl-NL');
        $now = $this->now();

        self::assertSame('over 5 minuten', $formatter->format($now->modify('+5 minutes'), $now));
        self::assertSame('over 5 minuten', $formatter->format($now->modify('+5 minutes'), $now));
        self::assertSame('nu', $formatter->format($now, $now));
        self::assertSame('nu', $formatter->format($now, $now));
    }

    #[Test]
    public function it_reports_a_language_intl_has_no_data_for(): void
    {
        $now = $this->now();

        try {
            $this->formatter('zz')->format($now->modify('+3 days'), $now);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame('zz', $exception->context['locale']);
            self::assertInstanceOf(ValueError::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string, string, RelativeDateTimeStyle, string}>
     */
    public static function relativeTimes(): iterable
    {
        yield 'now' => ['en-GB', '+0 seconds', RelativeDateTimeStyle::Long, 'now'];
        yield 'seconds ahead' => ['en-GB', '+30 seconds', RelativeDateTimeStyle::Long, 'in 30 seconds'];
        yield 'one second ago' => ['en-GB', '-1 second', RelativeDateTimeStyle::Long, '1 second ago'];
        yield 'minutes ahead' => ['en-GB', '+5 minutes', RelativeDateTimeStyle::Long, 'in 5 minutes'];
        yield 'hours ago' => ['en-GB', '-2 hours', RelativeDateTimeStyle::Long, '2 hours ago'];
        yield 'one day ahead' => ['en-GB', '+1 day', RelativeDateTimeStyle::Long, 'in 1 day'];
        yield 'a week rounded down' => ['en-GB', '+8 days', RelativeDateTimeStyle::Long, 'in 1 week'];
        yield 'weeks ago' => ['en-GB', '-20 days', RelativeDateTimeStyle::Long, '2 weeks ago'];
        yield 'a month ahead' => ['en-GB', '+1 month', RelativeDateTimeStyle::Long, 'in 1 month'];
        yield 'a year rounded down' => ['en-GB', '-14 months', RelativeDateTimeStyle::Long, '1 year ago'];
        yield 'years ahead' => ['en-GB', '+2 years', RelativeDateTimeStyle::Long, 'in 2 years'];
        yield 'short' => ['en-GB', '-2 hours', RelativeDateTimeStyle::Short, '2 hr. ago'];
        yield 'narrow' => ['en-GB', '-2 hours', RelativeDateTimeStyle::Narrow, '2h ago'];
        yield 'narrow now' => ['en-GB', '+0 seconds', RelativeDateTimeStyle::Narrow, 'now'];
        yield 'Dutch' => ['nl-NL', '-3 days', RelativeDateTimeStyle::Long, '3 dagen geleden'];
        yield 'Dutch short from its own long style' => [
            'nl-NL',
            '+8 days',
            RelativeDateTimeStyle::Short,
            'over 1 week',
        ];
        yield 'German narrow' => ['de-DE', '-20 days', RelativeDateTimeStyle::Narrow, 'vor 2 Wo.'];
        yield 'Arabic dual' => ['ar', '-2 hours', RelativeDateTimeStyle::Long, 'قبل ساعتين'];
        yield 'Japanese' => ['ja-JP', '+2 years', RelativeDateTimeStyle::Long, '2 年後'];
        yield 'language without locale data' => ['tlh', '+3 days', RelativeDateTimeStyle::Long, '+3 d'];
    }

    private function formatter(string $locale): RelativeDateTimeFormatter
    {
        return new RelativeDateTimeFormatter(new Locale($locale), new DateTimeZone('Europe/Amsterdam'));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-05 14:30:00', new DateTimeZone('Europe/Amsterdam'));
    }
}
