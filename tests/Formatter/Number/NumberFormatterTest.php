<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Number;

use IntlException;
use ReflectionProperty;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Enum\CompactNumberStyle;
use Dirthara\I18n\Exception\I18nException;
use NumberFormatter as IntlNumberFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidNumberException;
use Dirthara\I18n\Formatter\Number\NumberFormatter;
use Dirthara\I18n\Tests\Fixtures\FailingNumberFormatter;
use Dirthara\I18n\Contract\NumberFormatter as NumberFormatterContract;

use function sprintf;

use const INF;
use const NAN;

final class NumberFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_number_formatter_contract(): void
    {
        self::assertInstanceOf(NumberFormatterContract::class, new NumberFormatter(new Locale('en-GB')));
    }

    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new NumberFormatter($locale)->locale);
    }

    #[Test]
    #[DataProvider('formattedNumbers')]
    public function it_formats_a_number_for_its_locale(string $locale, int|float $number, string $expected): void
    {
        self::assertSame($expected, new NumberFormatter(new Locale($locale))->format($number));
    }

    #[Test]
    #[DataProvider('compactNumbers')]
    public function it_formats_a_compact_number(
        string $locale,
        int|float $number,
        CompactNumberStyle $style,
        string $expected,
    ): void {
        self::assertSame($expected, new NumberFormatter(new Locale($locale))->compact($number, $style));
    }

    #[Test]
    public function it_formats_a_short_compact_number_by_default(): void
    {
        self::assertSame('1.2M', new NumberFormatter(new Locale('en-GB'))->compact(1_234_567));
    }

    #[Test]
    public function it_formats_a_number_in_scientific_notation(): void
    {
        $formatter = new NumberFormatter(new Locale('nl-NL'));

        self::assertSame('1,234567891E6', $formatter->scientific(1_234_567.891));
        self::assertSame('1,25E-4', $formatter->scientific(0.000_125));
    }

    #[Test]
    #[DataProvider('spelledOutNumbers')]
    public function it_spells_out_a_number(string $locale, int|float $number, string $expected): void
    {
        self::assertSame($expected, new NumberFormatter(new Locale($locale))->spellOut($number));
    }

    #[Test]
    #[DataProvider('ordinals')]
    public function it_formats_an_ordinal(string $locale, int|float $number, string $expected): void
    {
        self::assertSame($expected, new NumberFormatter(new Locale($locale))->ordinal($number));
    }

    #[Test]
    #[DataProvider('fractions')]
    public function it_rejects_an_ordinal_that_is_not_a_whole_number(float $number, string $reported): void
    {
        try {
            new NumberFormatter(new Locale('en-GB'))->ordinal($number);
            self::fail('Expected an InvalidNumberException.');
        } catch (InvalidNumberException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                sprintf(
                    'The number formatter for locale "en-GB" cannot format %s as an ordinal: it has to be a whole '
                    . 'number.',
                    $reported,
                ),
                $exception->getMessage(),
            );
            self::assertSame(['formatter' => 'number', 'locale' => 'en-GB', 'number' => $number], $exception->context);
        }
    }

    #[Test]
    #[DataProvider('nonFiniteNumbers')]
    public function it_rejects_a_number_that_is_not_finite(float $number, string $reported): void
    {
        $formatter = new NumberFormatter(new Locale('en-GB'));

        foreach ([
            static fn(): string => $formatter->format($number),
            static fn(): string => $formatter->compact($number),
            static fn(): string => $formatter->compact($number, CompactNumberStyle::Long),
            static fn(): string => $formatter->scientific($number),
            static fn(): string => $formatter->spellOut($number),
            static fn(): string => $formatter->ordinal($number),
        ] as $format) {
            try {
                $format();
                self::fail('Expected an InvalidNumberException.');
            } catch (InvalidNumberException $exception) {
                self::assertSame(
                    'The number formatter for locale "en-GB" cannot format '
                    . $reported
                    . ': it has to be a finite number.',
                    $exception->getMessage(),
                );
                self::assertSame(
                    ['formatter' => 'number', 'locale' => 'en-GB', 'number' => $reported],
                    $exception->context,
                );
            }
        }
    }

    #[Test]
    public function it_reports_a_formatter_intl_cannot_create(): void
    {
        $code = 'en';

        for ($variant = 0; $variant < 18; $variant++) {
            $code .= sprintf('-v%07d', $variant);
        }

        $locale = new Locale($code);

        try {
            new NumberFormatter($locale)->spellOut(1);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'Unable to create the spell-out number formatter for locale "' . $locale->code . '".',
                $exception->getMessage(),
            );
            self::assertSame(
                ['formatter' => 'number', 'locale' => $locale->code, 'style' => 'spell-out'],
                $exception->context,
            );
            self::assertInstanceOf(IntlException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_number_intl_cannot_format(): void
    {
        $failing = new FailingNumberFormatter('en-GB', IntlNumberFormatter::DECIMAL);
        $this->useFormatter('en-GB|decimal', $failing);

        try {
            new NumberFormatter(new Locale('en-GB'))->format(12.5);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame(
                'The decimal number formatter for locale "en-GB" failed to format a value: '
                . $failing->getErrorMessage()
                . '.',
                $exception->getMessage(),
            );
            self::assertSame(
                [
                    'formatter' => 'number',
                    'locale' => 'en-GB',
                    'style' => 'decimal',
                    'intlCode' => $failing->getErrorCode(),
                    'intlMessage' => $failing->getErrorMessage(),
                ],
                $exception->context,
            );
        } finally {
            $this->forgetFormatter('en-GB|decimal');
        }
    }

    /**
     * @return iterable<string, array{string, int|float, string}>
     */
    public static function formattedNumbers(): iterable
    {
        yield 'British grouping' => ['en-GB', 1_234_567.891, '1,234,567.891'];
        yield 'British negative' => ['en-GB', -1.5, '-1.5'];
        yield 'British integer' => ['en-GB', 1500, '1,500'];
        yield 'half rounded up' => ['en-GB', 2.0005, '2.001'];
        yield 'Dutch' => ['nl-NL', 1_234_567.891, '1.234.567,891'];
        yield 'French' => ['fr-FR', 1_234_567.891, "1\u{202F}234\u{202F}567,891"];
    }

    /**
     * @return iterable<string, array{string, int|float, CompactNumberStyle, string}>
     */
    public static function compactNumbers(): iterable
    {
        yield 'British short' => ['en-GB', 1_234_567, CompactNumberStyle::Short, '1.2M'];
        yield 'British long' => ['en-GB', 1_234_567, CompactNumberStyle::Long, '1.2 million'];
        yield 'British thousands' => ['en-GB', 1500, CompactNumberStyle::Short, '1.5K'];
        yield 'British small' => ['en-GB', 999, CompactNumberStyle::Short, '999'];
        yield 'Dutch short' => ['nl-NL', 1_234_567, CompactNumberStyle::Short, "1,2\u{00A0}mln."];
        yield 'German long' => ['de-DE', 1_234_567, CompactNumberStyle::Long, '1,2 Millionen'];
    }

    /**
     * @return iterable<string, array{string, int|float, string}>
     */
    public static function spelledOutNumbers(): iterable
    {
        yield 'British' => ['en-GB', 42, 'forty-two'];
        yield 'British fraction' => ['en-GB', -1.5, 'minus one point five'];
        yield 'Dutch' => ['nl-NL', 42, "twee\u{00AD}\u{00EB}n\u{00AD}veertig"];
        yield 'French' => ['fr-FR', 42, 'quarante-deux'];
    }

    /**
     * @return iterable<string, array{string, int|float, string}>
     */
    public static function ordinals(): iterable
    {
        yield 'British first' => ['en-GB', 1, '1st'];
        yield 'British second' => ['en-GB', 2, '2nd'];
        yield 'British eleventh' => ['en-GB', 11, '11th'];
        yield 'British twenty-second' => ['en-GB', 22, '22nd'];
        yield 'whole float' => ['en-GB', 3.0, '3rd'];
        yield 'Dutch' => ['nl-NL', 3, '3e'];
        yield 'German' => ['de-DE', 3, '3.'];
        yield 'French first' => ['fr-FR', 1, '1er'];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function fractions(): iterable
    {
        yield 'half' => [1.5, '1.5'];
        yield 'negative fraction' => [-2.25, '-2.25'];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function nonFiniteNumbers(): iterable
    {
        yield 'not a number' => [NAN, 'NAN'];
        yield 'infinity' => [INF, 'INF'];
        yield 'negative infinity' => [-INF, '-INF'];
    }

    private function useFormatter(string $key, IntlNumberFormatter $formatter): void
    {
        $formatters = new ReflectionProperty(NumberFormatter::class, 'formatters');
        /** @var array<string, IntlNumberFormatter> $current */
        $current = $formatters->getValue();
        $current[$key] = $formatter;

        $formatters->setValue(null, $current);
    }

    private function forgetFormatter(string $key): void
    {
        $formatters = new ReflectionProperty(NumberFormatter::class, 'formatters');
        /** @var array<string, IntlNumberFormatter> $current */
        $current = $formatters->getValue();
        unset($current[$key]);

        $formatters->setValue(null, $current);
    }
}
