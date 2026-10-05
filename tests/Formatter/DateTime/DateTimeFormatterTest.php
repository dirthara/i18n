<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\DateTime;

use ValueError;
use DateTimeZone;
use IntlException;
use DateTimeImmutable;
use IntlDateFormatter;
use ReflectionProperty;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Enum\DateStyle;
use Dirthara\I18n\Enum\TimeStyle;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Tests\Fixtures\FailingDateFormatter;
use Dirthara\I18n\Formatter\DateTime\DateTimeFormatter;
use Dirthara\I18n\Exception\InvalidDateTimeStyleException;
use Dirthara\I18n\Contract\DateTimeFormatter as DateTimeFormatterContract;

use function sprintf;

final class DateTimeFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_date_time_formatter_contract(): void
    {
        self::assertInstanceOf(DateTimeFormatterContract::class, $this->formatter('en-GB'));
    }

    #[Test]
    public function it_exposes_its_locale_and_timezone(): void
    {
        $locale = new Locale('nl-NL');
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $formatter = new DateTimeFormatter($locale, $timezone);

        self::assertSame($locale, $formatter->locale);
        self::assertSame($timezone, $formatter->timezone);
    }

    #[Test]
    #[DataProvider('formattedDateTimes')]
    public function it_formats_a_date_and_time_for_its_locale_in_its_timezone(
        string $locale,
        DateStyle $dateStyle,
        TimeStyle $timeStyle,
        string $expected,
    ): void {
        self::assertSame($expected, $this->formatter($locale)->format($this->moment(), $dateStyle, $timeStyle));
    }

    #[Test]
    public function it_formats_a_medium_date_by_default(): void
    {
        self::assertSame('5 Oct 2026', $this->formatter('en-GB')->format($this->moment()));
    }

    #[Test]
    public function it_formats_a_date_on_its_own(): void
    {
        $formatter = $this->formatter('en-GB');

        self::assertSame('5 Oct 2026', $formatter->formatDate($this->moment()));
        self::assertSame('Monday, 5 October 2026', $formatter->formatDate($this->moment(), DateStyle::Full));
    }

    #[Test]
    public function it_formats_a_time_on_its_own(): void
    {
        $formatter = $this->formatter('en-GB');

        self::assertSame('16:30:15', $formatter->formatTime($this->moment()));
        self::assertSame('16:30', $formatter->formatTime($this->moment(), TimeStyle::Short));
    }

    #[Test]
    public function it_formats_in_its_own_timezone_whatever_the_timezone_of_the_value(): void
    {
        $tokyo = new DateTimeFormatter(new Locale('en-GB'), new DateTimeZone('Asia/Tokyo'));

        self::assertSame('23:30', $tokyo->formatTime($this->moment(), TimeStyle::Short));
        self::assertSame('16:30', $this->formatter('en-GB')->formatTime(
            $this->moment()->setTimezone(new DateTimeZone('America/New_York')),
            TimeStyle::Short,
        ));
    }

    #[Test]
    public function it_formats_in_a_timezone_given_as_an_offset(): void
    {
        $formatter = new DateTimeFormatter(new Locale('en-GB'), new DateTimeZone('+02:00'));

        self::assertSame('16:30', $formatter->formatTime($this->moment(), TimeStyle::Short));
    }

    #[Test]
    public function it_names_nearby_days_in_a_relative_style(): void
    {
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $formatter = new DateTimeFormatter(new Locale('en-GB'), $timezone);

        self::assertSame('today', $formatter->formatDate(
            new DateTimeImmutable('today 12:00', $timezone),
            DateStyle::RelativeMedium,
        ));
        self::assertSame('yesterday', $formatter->formatDate(
            new DateTimeImmutable('yesterday 12:00', $timezone),
            DateStyle::RelativeLong,
        ));
        self::assertSame('tomorrow, 12:00', $formatter->format(
            new DateTimeImmutable('tomorrow 12:00', $timezone),
            DateStyle::RelativeFull,
            TimeStyle::Short,
        ));

        $farAway = new DateTimeImmutable('+40 days 12:00', $timezone);

        self::assertSame(
            $formatter->formatDate($farAway, DateStyle::Medium),
            $formatter->formatDate($farAway, DateStyle::RelativeMedium),
        );
    }

    #[Test]
    #[DataProvider('emptyStyles')]
    public function it_rejects_a_format_without_a_date_or_a_time(DateStyle $dateStyle, TimeStyle $timeStyle): void
    {
        try {
            $this->formatter('en-GB')->format($this->moment(), $dateStyle, $timeStyle);
            self::fail('Expected an InvalidDateTimeStyleException.');
        } catch (InvalidDateTimeStyleException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'The date-time formatter for locale "en-GB" has nothing to format: the date style and the time style '
                . 'are both None.',
                $exception->getMessage(),
            );
            self::assertSame(['formatter' => 'date-time', 'locale' => 'en-GB'], $exception->context);
        }
    }

    #[Test]
    public function it_rejects_a_date_or_time_on_its_own_without_a_style(): void
    {
        $formatter = $this->formatter('en-GB');

        foreach ([
            static fn(): string => $formatter->formatDate(new DateTimeImmutable(), DateStyle::None),
            static fn(): string => $formatter->formatTime(new DateTimeImmutable(), TimeStyle::None),
        ] as $format) {
            try {
                $format();
                self::fail('Expected an InvalidDateTimeStyleException.');
            } catch (InvalidDateTimeStyleException $exception) {
                self::assertSame('en-GB', $exception->context['locale']);
            }
        }
    }

    #[Test]
    public function it_reports_a_timezone_intl_does_not_know(): void
    {
        $formatter = new DateTimeFormatter(new Locale('en-GB'), new DateTimeZone('CEST'));

        try {
            $formatter->format($this->moment(), DateStyle::Short, TimeStyle::Short);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'Unable to create the short-short date-time formatter for locale "en-GB".',
                $exception->getMessage(),
            );
            self::assertSame(
                ['formatter' => 'date-time', 'locale' => 'en-GB', 'style' => 'short-short'],
                $exception->context,
            );
            self::assertInstanceOf(IntlException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_locale_intl_cannot_create_a_formatter_for(): void
    {
        $code = 'en';

        for ($variant = 0; $variant < 18; $variant++) {
            $code .= sprintf('-v%07d', $variant);
        }

        try {
            new DateTimeFormatter(new Locale($code), new DateTimeZone('UTC'))->formatDate($this->moment());
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame('medium-none', $exception->context['style']);
            self::assertInstanceOf(IntlException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_value_intl_cannot_format(): void
    {
        $failing = new FailingDateFormatter('en-GB', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        $formatters = new ReflectionProperty(DateTimeFormatter::class, 'formatters');
        /** @var array<string, IntlDateFormatter> $original */
        $original = $formatters->getValue();
        $formatters->setValue(null, ['en-GB|Europe/Amsterdam|medium-none' => $failing] + $original);

        try {
            $this->formatter('en-GB')->formatDate($this->moment());
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame(
                [
                    'formatter' => 'date-time',
                    'locale' => 'en-GB',
                    'style' => 'medium-none',
                    'intlCode' => $failing->getErrorCode(),
                    'intlMessage' => $failing->getErrorMessage(),
                ],
                $exception->context,
            );
        } finally {
            $formatters->setValue(null, $original);
        }
    }

    #[Test]
    public function it_reports_a_language_intl_has_no_data_for(): void
    {
        try {
            new DateTimeFormatter(new Locale('zz'), new DateTimeZone('UTC'))->formatDate($this->moment());
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame('date-time', $exception->context['formatter']);
            self::assertSame('zz', $exception->context['locale']);
            self::assertSame('medium-none', $exception->context['style']);
            self::assertInstanceOf(ValueError::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string, DateStyle, TimeStyle, string}>
     */
    public static function formattedDateTimes(): iterable
    {
        yield 'British medium date' => ['en-GB', DateStyle::Medium, TimeStyle::None, '5 Oct 2026'];
        yield 'British short' => ['en-GB', DateStyle::Short, TimeStyle::Short, '05/10/2026, 16:30'];
        yield 'British long date' => ['en-GB', DateStyle::Long, TimeStyle::None, '5 October 2026'];
        yield 'British full' => [
            'en-GB',
            DateStyle::Full,
            TimeStyle::Full,
            'Monday, 5 October 2026 at 16:30:15 Central European Summer Time',
        ];
        yield 'British medium time' => ['en-GB', DateStyle::None, TimeStyle::Medium, '16:30:15'];
        yield 'British long time' => ['en-GB', DateStyle::None, TimeStyle::Long, '16:30:15 CEST'];
        yield 'American short' => ['en-US', DateStyle::Short, TimeStyle::Short, "10/5/26, 4:30\u{202F}PM"];
        yield 'American medium date' => ['en-US', DateStyle::Medium, TimeStyle::None, 'Oct 5, 2026'];
        yield 'Dutch short' => ['nl-NL', DateStyle::Short, TimeStyle::Short, '05-10-2026, 16:30'];
        yield 'Dutch full' => [
            'nl-NL',
            DateStyle::Full,
            TimeStyle::Full,
            'maandag 5 oktober 2026 om 16:30:15 Midden-Europese zomertijd',
        ];
        yield 'Japanese medium date' => ['ja-JP', DateStyle::Medium, TimeStyle::None, '2026/10/05'];
    }

    /**
     * @return iterable<string, array{DateStyle, TimeStyle}>
     */
    public static function emptyStyles(): iterable
    {
        yield 'none and none' => [DateStyle::None, TimeStyle::None];
    }

    private function formatter(string $locale): DateTimeFormatter
    {
        return new DateTimeFormatter(new Locale($locale), new DateTimeZone('Europe/Amsterdam'));
    }

    private function moment(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-05 14:30:15', new DateTimeZone('UTC'));
    }
}
