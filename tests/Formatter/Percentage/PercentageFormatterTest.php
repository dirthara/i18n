<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Percentage;

use ValueError;
use IntlException;
use NumberFormatter;
use ReflectionProperty;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidNumberException;
use Dirthara\I18n\Tests\Fixtures\FailingNumberFormatter;
use Dirthara\I18n\Formatter\Percentage\PercentageFormatter;
use Dirthara\I18n\Contract\PercentageFormatter as PercentageFormatterContract;

use function sprintf;

use const INF;
use const NAN;

final class PercentageFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_percentage_formatter_contract(): void
    {
        self::assertInstanceOf(PercentageFormatterContract::class, new PercentageFormatter(new Locale('en-GB')));
    }

    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new PercentageFormatter($locale)->locale);
    }

    #[Test]
    #[DataProvider('percentages')]
    public function it_formats_a_fraction_as_a_percentage(string $locale, int|float $fraction, string $expected): void
    {
        self::assertSame($expected, new PercentageFormatter(new Locale($locale))->format($fraction));
    }

    #[Test]
    #[DataProvider('nonFiniteFractions')]
    public function it_rejects_a_fraction_that_is_not_finite(float $fraction, string $reported): void
    {
        try {
            new PercentageFormatter(new Locale('en-GB'))->format($fraction);
            self::fail('Expected an InvalidNumberException.');
        } catch (InvalidNumberException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'The percentage formatter for locale "en-GB" cannot format '
                . $reported
                . ': it has to be a finite number.',
                $exception->getMessage(),
            );
            self::assertSame(
                ['formatter' => 'percentage', 'locale' => 'en-GB', 'number' => $reported],
                $exception->context,
            );
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
            new PercentageFormatter($locale)->format(0.5);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame(
                'Unable to create the percent percentage formatter for locale "' . $locale->code . '".',
                $exception->getMessage(),
            );
            self::assertSame(
                ['formatter' => 'percentage', 'locale' => $locale->code, 'style' => 'percent'],
                $exception->context,
            );
            self::assertInstanceOf(IntlException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_a_fraction_intl_cannot_format(): void
    {
        $failing = new FailingNumberFormatter('en-GB', NumberFormatter::PERCENT);
        $formatters = new ReflectionProperty(PercentageFormatter::class, 'formatters');
        /** @var array<string, NumberFormatter> $original */
        $original = $formatters->getValue();
        $formatters->setValue(null, ['en-GB' => $failing] + $original);

        try {
            new PercentageFormatter(new Locale('en-GB'))->format(0.5);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertSame(
                [
                    'formatter' => 'percentage',
                    'locale' => 'en-GB',
                    'style' => 'percent',
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
            new PercentageFormatter(new Locale('zz'))->format(0.5);
            self::fail('Expected a FormatterException.');
        } catch (FormatterException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame('percentage', $exception->context['formatter']);
            self::assertSame('zz', $exception->context['locale']);
            self::assertSame('percent', $exception->context['style']);
            self::assertInstanceOf(ValueError::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string, int|float, string}>
     */
    public static function percentages(): iterable
    {
        yield 'quarter' => ['en-GB', 0.25, '25%'];
        yield 'half rounded up' => ['en-GB', 0.125, '13%'];
        yield 'negative' => ['en-GB', -0.5, '-50%'];
        yield 'whole' => ['en-GB', 1, '100%'];
        yield 'zero' => ['en-GB', 0, '0%'];
        yield 'grouped' => ['en-GB', 12.345, '1,235%'];
        yield 'Dutch grouped' => ['nl-NL', 12.345, '1.235%'];
        yield 'German' => ['de-DE', 0.25, "25\u{00A0}%"];
        yield 'French' => ['fr-FR', 12.345, "1\u{202F}235\u{00A0}%"];
        yield 'Turkish sign first' => ['tr-TR', 0.25, '%25'];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function nonFiniteFractions(): iterable
    {
        yield 'not a number' => [NAN, 'NAN'];
        yield 'infinity' => [INF, 'INF'];
        yield 'negative infinity' => [-INF, '-INF'];
    }
}
