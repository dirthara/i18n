<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests;

use Closure;
use Throwable;
use ValueError;
use DateTimeZone;
use IntlException;
use NumberFormatter;
use MessageFormatter;
use DateTimeImmutable;
use IntlDateFormatter;
use ReflectionProperty;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use Dirthara\I18n\PluralRules;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Enum\DurationStyle;
use Dirthara\I18n\Enum\PluralCategory;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Formatter\List\ListFormatter;
use Dirthara\I18n\Exception\PluralRulesException;
use Dirthara\I18n\Formatter\Locale\LocaleFormatter;
use Dirthara\I18n\Exception\CurrencyFormatterException;
use Dirthara\I18n\Formatter\Currency\CurrencyFormatter;
use Dirthara\I18n\Formatter\DateTime\DateTimeFormatter;
use Dirthara\I18n\Formatter\Duration\DurationFormatter;
use Dirthara\I18n\Tests\Fixtures\ThrowingDateFormatter;
use Dirthara\I18n\Tests\Fixtures\ThrowingNumberFormatter;
use Dirthara\I18n\Tests\Fixtures\ThrowingMessageFormatter;
use Dirthara\I18n\Formatter\Percentage\PercentageFormatter;
use Dirthara\I18n\Formatter\RelativeDateTime\RelativeDateTimeFormatter;
use Dirthara\I18n\Formatter\Number\NumberFormatter as DirtharaNumberFormatter;

use function ini_get;
use function ini_set;
use function sprintf;

final class IntlExceptionsTest extends TestCase
{
    private string|false $useExceptions = false;

    protected function setUp(): void
    {
        // @mago-expect lint:no-ini-set
        $this->useExceptions = ini_set(option: 'intl.use_exceptions', value: '1');
    }

    protected function tearDown(): void
    {
        // @mago-expect lint:no-ini-set
        ini_set(option: 'intl.use_exceptions', value: (string) $this->useExceptions);
    }

    #[Test]
    public function it_formats_normally_with_intl_exceptions_enabled(): void
    {
        $locale = new Locale('en-GB');
        $now = new DateTimeImmutable('2026-10-05 14:30:00', new DateTimeZone('UTC'));

        self::assertSame('1', ini_get('intl.use_exceptions'));
        self::assertSame(PluralCategory::One, new PluralRules($locale)->category(1));
        self::assertSame('1,500', new DirtharaNumberFormatter($locale)->format(1500));
        self::assertSame("\u{20AC}12.50", new CurrencyFormatter($locale)->format(12.5, new Currency('EUR')));
        self::assertSame('25%', new PercentageFormatter($locale)->format(0.25));
        self::assertSame('a, b and c', new ListFormatter($locale)->format(['a', 'b', 'c']));
        self::assertSame('5 Oct 2026', new DateTimeFormatter($locale, new DateTimeZone('UTC'))->formatDate($now));
        self::assertSame('1:02:05', new DurationFormatter($locale)->format(3725, DurationStyle::Digital));
        self::assertSame('1.02.05', new DurationFormatter(new Locale('da-DK'))->format(3725, DurationStyle::Digital));
        self::assertSame('1 hour, 2 minutes, 5 seconds', new DurationFormatter($locale)->format(3725));
        self::assertSame(
            'German (Switzerland, Traditional German orthography, German orthography of 1996)',
            new LocaleFormatter($locale)->format(new Locale('de-CH-1901-1996')),
        );
        self::assertSame('3 days ago', new RelativeDateTimeFormatter($locale, new DateTimeZone('UTC'))->format(
            $now->modify('-3 days'),
            $now,
        ));
        self::assertSame('+3 d', new RelativeDateTimeFormatter(new Locale('tlh'), new DateTimeZone('UTC'))->format(
            $now->modify('+3 days'),
            $now,
        ));
    }

    /**
     * @param class-string<Throwable> $previous
     */
    #[Test]
    #[DataProvider('failingOperations')]
    public function it_wraps_an_intl_failure_in_a_package_exception(Closure $operation, string $previous): void
    {
        try {
            $operation();
            self::fail('Expected an I18nException.');
        } catch (I18nException $exception) {
            self::assertInstanceOf($previous, $exception->getPrevious());
        }
    }

    /**
     * @param class-string $class
     * @param class-string<Throwable> $exception
     */
    #[Test]
    #[DataProvider('throwingFormatters')]
    public function it_wraps_an_intl_exception_thrown_while_formatting(
        string $class,
        string $property,
        string $key,
        Closure $formatter,
        Closure $operation,
        string $exception,
    ): void {
        $formatters = new ReflectionProperty($class, $property);
        /** @var array<string, object> $original */
        $original = $formatters->getValue();
        $formatters->setValue(null, [$key => $formatter()] + $original);

        try {
            $operation();
            self::fail('Expected an I18nException.');
        } catch (I18nException $thrown) {
            self::assertInstanceOf($exception, $thrown);
            self::assertInstanceOf(IntlException::class, $thrown->getPrevious());
        } finally {
            $formatters->setValue(null, $original);
        }
    }

    /**
     * @return iterable<string, array{Closure(): mixed, class-string}>
     */
    public static function failingOperations(): iterable
    {
        $code = 'en';

        for ($variant = 0; $variant < 18; $variant++) {
            $code .= sprintf('-v%07d', $variant);
        }

        $overlong = new Locale($code);
        $unknown = new Locale('zz');
        $british = new Locale('en-GB');

        yield 'plural rules for an overlong locale' => [
            static fn(): PluralCategory => new PluralRules($overlong)->category(1),
            IntlException::class,
        ];
        yield 'number for an unknown language' => [
            static fn(): string => new DirtharaNumberFormatter($unknown)->format(1),
            ValueError::class,
        ];
        yield 'currency for an overlong locale' => [
            static fn(): string => new CurrencyFormatter($overlong)->format(1, new Currency('EUR')),
            IntlException::class,
        ];
        yield 'percentage for an unknown language' => [
            static fn(): string => new PercentageFormatter($unknown)->format(0.5),
            ValueError::class,
        ];
        yield 'list with invalid UTF-8' => [
            static fn(): string => new ListFormatter($british)->format(['a', "\xFF"]),
            IntlException::class,
        ];
        yield 'list for an unknown language' => [
            static fn(): string => new ListFormatter($unknown)->format(['a', 'b']),
            ValueError::class,
        ];
        yield 'locale name of an overlong locale' => [
            static fn(): string => new LocaleFormatter($british)->format($overlong),
            IntlException::class,
        ];
        yield 'language of an overlong locale' => [
            static fn(): string => new LocaleFormatter($british)->language($overlong),
            IntlException::class,
        ];
        yield 'date-time in an unknown timezone' => [
            static fn(): string => new DateTimeFormatter($british, new DateTimeZone('CEST'))->formatDate(
                new DateTimeImmutable(),
            ),
            IntlException::class,
        ];
        yield 'duration for an overlong locale' => [
            static fn(): string => new DurationFormatter($overlong)->format(65),
            IntlException::class,
        ];
        yield 'relative date-time for an unknown language' => [
            static fn(): string => new RelativeDateTimeFormatter($unknown, new DateTimeZone('UTC'))->format(
                new DateTimeImmutable('+3 days'),
                new DateTimeImmutable(),
            ),
            ValueError::class,
        ];
    }

    /**
     * @return iterable<string, array{class-string, string, string, Closure(): object, Closure(): mixed, class-string}>
     */
    public static function throwingFormatters(): iterable
    {
        $british = new Locale('en-GB');

        yield 'plural rules' => [
            PluralRules::class,
            'formatters',
            'en-GB',
            static fn(): MessageFormatter => new ThrowingMessageFormatter('en-GB', '{0}'),
            static fn(): PluralCategory => new PluralRules($british)->category(1),
            PluralRulesException::class,
        ];
        yield 'number' => [
            DirtharaNumberFormatter::class,
            'formatters',
            'en-GB|decimal',
            static fn(): NumberFormatter => new ThrowingNumberFormatter('en-GB', NumberFormatter::DECIMAL),
            static fn(): string => new DirtharaNumberFormatter($british)->format(1),
            FormatterException::class,
        ];
        yield 'currency' => [
            CurrencyFormatter::class,
            'formatters',
            'en-GB|Standard',
            static fn(): NumberFormatter => new ThrowingNumberFormatter('en-GB', NumberFormatter::CURRENCY),
            static fn(): string => new CurrencyFormatter($british)->format(1, new Currency('EUR')),
            CurrencyFormatterException::class,
        ];
        yield 'percentage' => [
            PercentageFormatter::class,
            'formatters',
            'en-GB',
            static fn(): NumberFormatter => new ThrowingNumberFormatter('en-GB', NumberFormatter::PERCENT),
            static fn(): string => new PercentageFormatter($british)->format(0.5),
            FormatterException::class,
        ];
        yield 'date-time' => [
            DateTimeFormatter::class,
            'formatters',
            'en-GB|UTC|medium-none',
            static fn(): IntlDateFormatter => new ThrowingDateFormatter(
                'en-GB',
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE,
            ),
            static fn(): string => new DateTimeFormatter($british, new DateTimeZone('UTC'))->formatDate(
                new DateTimeImmutable(),
            ),
            FormatterException::class,
        ];
        yield 'duration unit' => [
            DurationFormatter::class,
            'units',
            'en-GB|second-unit-width-full-name',
            static fn(): MessageFormatter => new ThrowingMessageFormatter('en-GB', '{0}'),
            static fn(): string => new DurationFormatter($british)->format(5),
            FormatterException::class,
        ];
        yield 'duration digits' => [
            DurationFormatter::class,
            'digits',
            'en-GB|digital-1',
            static fn(): NumberFormatter => new ThrowingNumberFormatter('en-GB', NumberFormatter::DECIMAL),
            static fn(): string => new DurationFormatter($british)->format(65, DurationStyle::Digital),
            FormatterException::class,
        ];
    }
}
