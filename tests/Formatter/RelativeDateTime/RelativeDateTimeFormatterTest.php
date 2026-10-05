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
    #[DataProvider('britishTimes')]
    public function it_writes_a_named_form_or_a_number(string $offset, string $long, string $narrow): void
    {
        $now = $this->now();
        $formatter = $this->formatter('en-GB');

        self::assertSame($long, $formatter->format($now->modify($offset), $now));
        self::assertSame($narrow, $formatter->format($now->modify($offset), $now, RelativeDateTimeStyle::Narrow));
    }

    #[Test]
    #[DataProvider('otherLocales')]
    public function it_writes_the_forms_of_the_locale(
        string $locale,
        string $offset,
        RelativeDateTimeStyle $style,
        string $expected,
    ): void {
        $now = $this->now();

        self::assertSame($expected, $this->formatter($locale)->format($now->modify($offset), $now, $style));
    }

    #[Test]
    public function it_writes_the_short_style(): void
    {
        $now = $this->now();
        $formatter = $this->formatter('en-GB');

        self::assertSame('2 hr. ago', $formatter->format($now->modify('-2 hours'), $now, RelativeDateTimeStyle::Short));
        self::assertSame('next wk.', $formatter->format($now->modify('+1 week'), $now, RelativeDateTimeStyle::Short));
        self::assertSame('in 6 wk.', $formatter->format($now->modify('+6 weeks'), $now, RelativeDateTimeStyle::Short));
    }

    #[Test]
    public function it_uses_whole_calendar_months_and_years_when_the_difference_is_exactly_that(): void
    {
        $formatter = $this->formatter('en-GB');
        $end = new DateTimeImmutable('2026-01-31 12:00', new DateTimeZone('Europe/Amsterdam'));

        self::assertSame('in 2 months', $formatter->format($end->modify('+2 months'), $end));
        self::assertSame('in 4 weeks', $formatter->format(
            new DateTimeImmutable('2026-02-28 12:00', $end->getTimezone()),
            $end,
        ));
        self::assertSame('in 14 months', $formatter->format($end->modify('+14 months'), $end));
        self::assertSame('in 2 years', $formatter->format($end->modify('+2 years'), $end));
        self::assertSame('in 2 years', $formatter->format($end->modify('+2 years +3 days'), $end));
        self::assertSame('in 5 weeks', $formatter->format($end->modify('+40 days'), $end));
    }

    #[Test]
    public function it_counts_calendar_days_in_its_own_timezone_across_a_change_of_time(): void
    {
        $formatter = $this->formatter('en-GB');
        $amsterdam = new DateTimeZone('Europe/Amsterdam');
        $before = new DateTimeImmutable('2026-10-24 12:00', $amsterdam);
        $after = new DateTimeImmutable('2026-10-25 12:00', $amsterdam);

        self::assertSame('tomorrow', $formatter->format($after, $before));
        self::assertSame('yesterday', $formatter->format($before, $after));
        self::assertSame('tomorrow', $formatter->format(
            $after->setTimezone(new DateTimeZone('Asia/Tokyo')),
            $before->setTimezone(new DateTimeZone('America/New_York')),
        ));
        self::assertSame('in 3 hours', $formatter->format(
            new DateTimeImmutable('2026-10-25 03:30', $amsterdam),
            new DateTimeImmutable('2026-10-25 01:30', $amsterdam),
        ));
    }

    #[Test]
    public function it_treats_a_difference_below_a_second_as_now(): void
    {
        $now = $this->now();

        self::assertSame('now', $this->formatter('en-GB')->format($now->modify('+500 milliseconds'), $now));
    }

    #[Test]
    public function it_formats_the_same_moment_again_from_its_cache(): void
    {
        $formatter = $this->formatter('nl-NL');
        $now = $this->now();

        self::assertSame('over 5 minuten', $formatter->format($now->modify('+5 minutes'), $now));
        self::assertSame('over 5 minuten', $formatter->format($now->modify('+5 minutes'), $now));
        self::assertSame('morgen', $formatter->format($now->modify('+1 day'), $now));
        self::assertSame('morgen', $formatter->format($now->modify('+1 day'), $now));
    }

    #[Test]
    public function it_keeps_locales_and_styles_apart(): void
    {
        $now = $this->now();
        $british = $this->formatter('en-GB');
        $dutch = $this->formatter('nl-NL');

        self::assertSame('in 3 days', $british->format($now->modify('+3 days'), $now));
        self::assertSame('over 3 dagen', $dutch->format($now->modify('+3 days'), $now));
        self::assertSame('in 3d', $british->format($now->modify('+3 days'), $now, RelativeDateTimeStyle::Narrow));
        self::assertSame('in 3 days', $british->format($now->modify('+3 days'), $now));
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
     * @return iterable<string, array{string, string, string}>
     */
    public static function britishTimes(): iterable
    {
        yield 'now' => ['+0 seconds', 'now', 'now'];
        yield 'seconds ahead' => ['+30 seconds', 'in 30 seconds', 'in 30s'];
        yield 'a minute ago' => ['-1 minute', '1 minute ago', '1m ago'];
        yield 'hours ago' => ['-2 hours', '2 hours ago', '2h ago'];
        yield 'yesterday' => ['-1 day', 'yesterday', 'yesterday'];
        yield 'tomorrow' => ['+1 day', 'tomorrow', 'tomorrow'];
        yield 'two days ago' => ['-2 days', '2 days ago', '2d ago'];
        yield 'last week' => ['-1 week', 'last week', 'last wk.'];
        yield 'next week' => ['+1 week', 'next week', 'next wk.'];
        yield 'six weeks ahead' => ['+6 weeks', 'in 6 weeks', 'in 6w'];
        yield 'weeks ago' => ['-3 weeks', '3 weeks ago', '3w ago'];
        yield 'last month' => ['-1 month', 'last month', 'last mo.'];
        yield 'next month' => ['+1 month', 'next month', 'next mo.'];
        yield 'last year' => ['-1 year', 'last year', 'last yr.'];
        yield 'next year' => ['+1 year', 'next year', 'next yr.'];
        yield 'years ahead' => ['+2 years', 'in 2 years', 'in 2y'];
    }

    /**
     * @return iterable<string, array{string, string, RelativeDateTimeStyle, string}>
     */
    public static function otherLocales(): iterable
    {
        yield 'Dutch now' => ['nl-NL', '+0 seconds', RelativeDateTimeStyle::Long, 'nu'];
        yield 'Dutch the day before yesterday' => ['nl-NL', '-2 days', RelativeDateTimeStyle::Long, 'eergisteren'];
        yield 'Dutch the day after tomorrow' => ['nl-NL', '+2 days', RelativeDateTimeStyle::Long, 'overmorgen'];
        yield 'Dutch days' => ['nl-NL', '+3 days', RelativeDateTimeStyle::Long, 'over 3 dagen'];
        yield 'Dutch narrow from its own longer style' => [
            'nl-NL',
            '+6 weeks',
            RelativeDateTimeStyle::Narrow,
            'over 6 weken',
        ];
        yield 'German last week' => ['de-DE', '-1 week', RelativeDateTimeStyle::Long, 'letzte Woche'];
        yield 'German narrow weeks' => ['de-DE', '-3 weeks', RelativeDateTimeStyle::Narrow, 'vor 3 Wo.'];
        yield 'French next month' => ['fr-FR', '+1 month', RelativeDateTimeStyle::Long, 'le mois prochain'];
        yield 'French narrow days' => ['fr-FR', '+3 days', RelativeDateTimeStyle::Narrow, '+3 j'];
        yield 'Arabic dual' => ['ar', '-2 hours', RelativeDateTimeStyle::Long, 'قبل ساعتين'];
        yield 'Arabic few' => ['ar', '+3 days', RelativeDateTimeStyle::Long, 'خلال 3 أيام'];
        yield 'Arabic tomorrow' => ['ar', '+1 day', RelativeDateTimeStyle::Long, 'غدًا'];
        yield 'Japanese next year' => ['ja-JP', '+1 year', RelativeDateTimeStyle::Long, '来年'];
        yield 'Japanese years' => ['ja-JP', '+2 years', RelativeDateTimeStyle::Long, '2 年後'];
        yield 'language without locale data' => ['tlh', '+3 days', RelativeDateTimeStyle::Long, '+3 d'];
        yield 'language without locale data, a day' => ['tlh', '+1 day', RelativeDateTimeStyle::Long, '+1 d'];
        yield 'language without locale data, now' => ['tlh', '+0 seconds', RelativeDateTimeStyle::Long, '+0 s'];
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
