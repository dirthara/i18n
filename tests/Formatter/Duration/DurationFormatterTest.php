<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Duration;

use IntlException;
use NumberFormatter;
use MessageFormatter;
use ReflectionProperty;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Enum\DurationStyle;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidNumberException;
use Dirthara\I18n\Formatter\Duration\DurationFormatter;
use Dirthara\I18n\Tests\Fixtures\FailingNumberFormatter;
use Dirthara\I18n\Tests\Fixtures\FailingMessageFormatter;
use Dirthara\I18n\Contract\DurationFormatter as DurationFormatterContract;

use function sprintf;

use const INF;
use const NAN;

final class DurationFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_duration_formatter_contract(): void
    {
        self::assertInstanceOf(DurationFormatterContract::class, new DurationFormatter(new Locale('en-GB')));
    }

    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new DurationFormatter($locale)->locale);
    }

    #[Test]
    #[DataProvider('durations')]
    public function it_formats_a_duration_for_its_locale(
        string $locale,
        int|float $seconds,
        DurationStyle $style,
        string $expected,
    ): void {
        self::assertSame($expected, new DurationFormatter(new Locale($locale))->format($seconds, $style));
    }

    #[Test]
    public function it_formats_a_long_duration_by_default(): void
    {
        self::assertSame('1 hour, 2 minutes, 5 seconds', new DurationFormatter(new Locale('en-GB'))->format(3725));
    }

    #[Test]
    public function it_rejects_a_negative_duration(): void
    {
        try {
            new DurationFormatter(new Locale('en-GB'))->format(-5);
            self::fail('Expected an InvalidNumberException.');
        } catch (InvalidNumberException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'The duration formatter for locale "en-GB" cannot format -5: it cannot be negative.',
                $exception->getMessage(),
            );
            self::assertSame(['formatter' => 'duration', 'locale' => 'en-GB', 'number' => -5], $exception->context);
        }
    }

    #[Test]
    #[DataProvider('nonFiniteDurations')]
    public function it_rejects_a_duration_that_is_not_finite(float $seconds, string $reported): void
    {
        try {
            new DurationFormatter(new Locale('en-GB'))->format($seconds, DurationStyle::Digital);
            self::fail('Expected an InvalidNumberException.');
        } catch (InvalidNumberException $exception) {
            self::assertSame(
                ['formatter' => 'duration', 'locale' => 'en-GB', 'number' => $reported],
                $exception->context,
            );
        }
    }

    #[Test]
    #[DataProvider('styles')]
    public function it_reports_a_formatter_intl_cannot_create(DurationStyle $style, string $reported): void
    {
        $code = 'en';

        for ($variant = 0; $variant < 18; $variant++) {
            $code .= sprintf('-v%07d', $variant);
        }

        try {
            new DurationFormatter(new Locale($code))->format(65, $style);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame('duration', $exception->context['formatter']);
            self::assertSame($reported, $exception->context['style']);
            self::assertInstanceOf(IntlException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_unit_intl_cannot_format(): void
    {
        $failing = new FailingMessageFormatter('en-GB', '{0}');
        $units = new ReflectionProperty(DurationFormatter::class, 'units');
        /** @var array<string, MessageFormatter> $original */
        $original = $units->getValue();
        $units->setValue(null, ['en-GB|second-unit-width-full-name' => $failing] + $original);

        try {
            new DurationFormatter(new Locale('en-GB'))->format(5);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame(
                [
                    'formatter' => 'duration',
                    'locale' => 'en-GB',
                    'style' => 'second-unit-width-full-name',
                    'intlCode' => $failing->getErrorCode(),
                    'intlMessage' => $failing->getErrorMessage(),
                ],
                $exception->context,
            );
        } finally {
            $units->setValue(null, $original);
        }
    }

    #[Test]
    public function it_reports_digits_intl_cannot_format(): void
    {
        $failing = new FailingNumberFormatter('en-GB', NumberFormatter::DECIMAL);
        $digits = new ReflectionProperty(DurationFormatter::class, 'digits');
        /** @var array<string, NumberFormatter> $original */
        $original = $digits->getValue();
        $digits->setValue(null, ['en-GB|digital-1' => $failing] + $original);

        try {
            new DurationFormatter(new Locale('en-GB'))->format(65, DurationStyle::Digital);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame('digital-1', $exception->context['style']);
            self::assertSame($failing->getErrorCode(), $exception->context['intlCode']);
        } finally {
            $digits->setValue(null, $original);
        }
    }

    /**
     * @return iterable<string, array{string, int|float, DurationStyle, string}>
     */
    public static function durations(): iterable
    {
        yield 'nothing' => ['en-GB', 0, DurationStyle::Long, '0 seconds'];
        yield 'one second' => ['en-GB', 1, DurationStyle::Long, '1 second'];
        yield 'minutes and seconds' => ['en-GB', 125, DurationStyle::Long, '2 minutes, 5 seconds'];
        yield 'a day of every unit' => ['en-GB', 90_061, DurationStyle::Long, '1 day, 1 hour, 1 minute, 1 second'];
        yield 'whole hour' => ['en-GB', 3600, DurationStyle::Long, '1 hour'];
        yield 'fraction of a second' => ['en-GB', 1.5, DurationStyle::Long, '1.5 seconds'];
        yield 'fraction after minutes' => ['en-GB', 61.25, DurationStyle::Long, '1 minute, 1.25 seconds'];
        yield 'short' => ['en-GB', 3725, DurationStyle::Short, '1 hr, 2 mins, 5 secs'];
        yield 'narrow' => ['en-GB', 3725, DurationStyle::Narrow, '1h 2m 5s'];
        yield 'digital' => ['en-GB', 3725, DurationStyle::Digital, '1:02:05'];
        yield 'digital without hours' => ['en-GB', 125, DurationStyle::Digital, '2:05'];
        yield 'digital past a day' => ['en-GB', 90_061, DurationStyle::Digital, '25:01:01'];
        yield 'digital nothing' => ['en-GB', 0, DurationStyle::Digital, '0:00'];
        yield 'digital rounds half up' => ['en-GB', 59.5, DurationStyle::Digital, '1:00'];
        yield 'Dutch long' => ['nl-NL', 3725, DurationStyle::Long, '1 uur, 2 minuten en 5 seconden'];
        yield 'German long' => ['de-DE', 3725, DurationStyle::Long, '1 Stunde, 2 Minuten und 5 Sekunden'];
        yield 'Danish digital' => ['da-DK', 3725, DurationStyle::Digital, '1.02.05'];
        yield 'Arabic digital' => ['ar-EG', 3725, DurationStyle::Digital, "\u{0661}:\u{0660}\u{0662}:\u{0660}\u{0665}"];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function nonFiniteDurations(): iterable
    {
        yield 'not a number' => [NAN, 'NAN'];
        yield 'infinity' => [INF, 'INF'];
        yield 'negative infinity' => [-INF, '-INF'];
    }

    /**
     * @return iterable<string, array{DurationStyle, string}>
     */
    public static function styles(): iterable
    {
        yield 'long' => [DurationStyle::Long, 'minute-unit-width-full-name'];
        yield 'digital' => [DurationStyle::Digital, 'digital-1'];
    }
}
