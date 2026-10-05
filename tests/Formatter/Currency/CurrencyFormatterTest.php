<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Formatter\Currency;

use IntlException;
use NumberFormatter;
use ReflectionProperty;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use PHPUnit\Framework\TestCase;
use Dirthara\I18n\Enum\CurrencyStyle;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\CurrencyFormatterException;
use Dirthara\I18n\Formatter\Currency\CurrencyFormatter;
use Dirthara\I18n\Tests\Fixtures\FailingNumberFormatter;
use Dirthara\I18n\Exception\InvalidCurrencyAmountException;
use Dirthara\I18n\Contract\CurrencyFormatter as CurrencyFormatterContract;

use function sprintf;

use const INF;
use const NAN;

final class CurrencyFormatterTest extends TestCase
{
    #[Test]
    public function it_implements_the_currency_formatter_contract(): void
    {
        self::assertInstanceOf(CurrencyFormatterContract::class, new CurrencyFormatter(new Locale('en-GB')));
    }

    #[Test]
    public function it_exposes_its_locale(): void
    {
        $locale = new Locale('nl-NL');

        self::assertSame($locale, new CurrencyFormatter($locale)->locale);
    }

    #[Test]
    #[DataProvider('formattedAmounts')]
    public function it_formats_an_amount_in_a_currency_for_its_locale(
        string $locale,
        int|float $amount,
        string $currency,
        CurrencyStyle $style,
        string $expected,
    ): void {
        self::assertSame($expected, new CurrencyFormatter(new Locale($locale))->format(
            $amount,
            new Currency($currency),
            $style,
        ));
    }

    #[Test]
    public function it_formats_in_the_standard_style_by_default(): void
    {
        self::assertSame("\u{20AC}\u{00A0}1.234,50", new CurrencyFormatter(new Locale('nl-NL'))->format(
            1234.5,
            new Currency('EUR'),
        ));
    }

    #[Test]
    #[DataProvider('roundedAmounts')]
    public function it_rounds_half_away_from_zero_to_the_digits_of_the_currency(
        float $amount,
        string $currency,
        string $expected,
    ): void {
        self::assertSame($expected, new CurrencyFormatter(new Locale('en-GB'))->format(
            $amount,
            new Currency($currency),
        ));
    }

    #[Test]
    public function it_formats_with_several_locales_and_styles_side_by_side(): void
    {
        $british = new CurrencyFormatter(new Locale('en-GB'));
        $dutch = new CurrencyFormatter(new Locale('nl-NL'));
        $euro = new Currency('EUR');

        self::assertSame("\u{20AC}12.50", $british->format(12.5, $euro));
        self::assertSame("\u{20AC}\u{00A0}12,50", $dutch->format(12.5, $euro));
        self::assertSame("EUR\u{00A0}12.50", $british->format(12.5, $euro, CurrencyStyle::Iso));
        self::assertSame("\u{20AC}12.50", $british->format(12.5, $euro));
        self::assertSame('12,50 euro', $dutch->format(12.5, $euro, CurrencyStyle::Name));
    }

    #[Test]
    #[DataProvider('nonFiniteAmounts')]
    public function it_rejects_an_amount_that_is_not_finite(float $amount, string $reported): void
    {
        try {
            new CurrencyFormatter(new Locale('en-GB'))->format($amount, new Currency('EUR'));
            self::fail('Expected an InvalidCurrencyAmountException.');
        } catch (InvalidCurrencyAmountException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'Unable to format '
                . $reported
                . ' as an amount of EUR for locale "en-GB": an amount has to be a finite number.',
                $exception->getMessage(),
            );
            self::assertSame(['locale' => 'en-GB', 'currency' => 'EUR', 'amount' => $reported], $exception->context);
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
            new CurrencyFormatter($locale)->format(1, new Currency('EUR'), CurrencyStyle::Iso);
            self::fail('Expected a CurrencyFormatterException.');
        } catch (CurrencyFormatterException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'Unable to create the Iso currency formatter for locale "' . $locale->code . '".',
                $exception->getMessage(),
            );
            self::assertSame(['locale' => $locale->code, 'style' => 'Iso'], $exception->context);
            self::assertInstanceOf(IntlException::class, $exception->getPrevious());
        }
    }

    #[Test]
    public function it_reports_an_amount_intl_cannot_format(): void
    {
        $failing = new FailingNumberFormatter('en-GB', NumberFormatter::CURRENCY);
        $this->useFormatter('en-GB|Standard', $failing);

        try {
            new CurrencyFormatter(new Locale('en-GB'))->format(12.5, new Currency('EUR'));
            self::fail('Expected a CurrencyFormatterException.');
        } catch (CurrencyFormatterException $exception) {
            self::assertSame(
                'Unable to format an amount of EUR in the Standard style for locale "en-GB": '
                . $failing->getErrorMessage()
                . '.',
                $exception->getMessage(),
            );
            self::assertSame(
                [
                    'locale' => 'en-GB',
                    'currency' => 'EUR',
                    'style' => 'Standard',
                    'intlCode' => $failing->getErrorCode(),
                    'intlMessage' => $failing->getErrorMessage(),
                ],
                $exception->context,
            );
        } finally {
            $this->forgetFormatter('en-GB|Standard');
        }
    }

    /**
     * @return iterable<string, array{string, int|float, string, CurrencyStyle, string}>
     */
    public static function formattedAmounts(): iterable
    {
        yield 'British standard' => ['en-GB', -1234.567, 'EUR', CurrencyStyle::Standard, "-\u{20AC}1,234.57"];
        yield 'British accounting' => ['en-GB', -1234.567, 'EUR', CurrencyStyle::Accounting, "(\u{20AC}1,234.57)"];
        yield 'British ISO' => ['en-GB', -1234.567, 'EUR', CurrencyStyle::Iso, "-EUR\u{00A0}1,234.57"];
        yield 'British name' => ['en-GB', -1234.567, 'EUR', CurrencyStyle::Name, '-1,234.57 euros'];
        yield 'British cash' => ['en-GB', -1234.567, 'EUR', CurrencyStyle::Cash, "-\u{20AC}1,234.57"];
        yield 'Dutch standard' => ['nl-NL', -1234.5, 'EUR', CurrencyStyle::Standard, "\u{20AC}\u{00A0}-1.234,50"];
        yield 'Dutch accounting' => ['nl-NL', -1234.5, 'EUR', CurrencyStyle::Accounting, "(\u{20AC}\u{00A0}1.234,50)"];
        yield 'Dutch name' => ['nl-NL', 1234.5, 'EUR', CurrencyStyle::Name, '1.234,50 euro'];
        yield 'French standard' => ['fr-FR', 1234.5, 'EUR', CurrencyStyle::Standard, "1\u{202F}234,50\u{00A0}\u{20AC}"];
        yield 'French name' => ['fr-FR', 1234.5, 'EUR', CurrencyStyle::Name, "1\u{202F}234,50 euros"];
        yield 'Japanese yen without decimals' => ['ja-JP', 1234.5, 'JPY', CurrencyStyle::Standard, "\u{FFE5}1,235"];
        yield 'Japanese name' => ['ja-JP', 1234.5, 'JPY', CurrencyStyle::Name, "1,235\u{00A0}\u{5186}"];
        yield 'Swiss standard' => ['de-CH', 1234.57, 'CHF', CurrencyStyle::Standard, "CHF\u{00A0}1\u{2019}234.57"];
        yield 'Swiss cash rounding' => ['de-CH', 1234.57, 'CHF', CurrencyStyle::Cash, "CHF\u{00A0}1\u{2019}234.55"];
        yield 'American integer' => ['en-US', 1500, 'USD', CurrencyStyle::Standard, '$1,500.00'];
        yield 'American name' => ['en-US', 1500, 'USD', CurrencyStyle::Name, '1,500.00 US dollars'];
        yield 'unassigned currency' => ['en-US', 2, 'QQQ', CurrencyStyle::Standard, "QQQ\u{00A0}2.00"];
    }

    /**
     * @return iterable<string, array{float, string, string}>
     */
    public static function roundedAmounts(): iterable
    {
        yield 'half up' => [2.5, 'JPY', "JP\u{00A5}3"];
        yield 'half away from zero' => [-2.5, 'JPY', "-JP\u{00A5}3"];
        yield 'below half' => [0.125, 'JPY', "JP\u{00A5}0"];
        yield 'half a cent' => [0.125, 'EUR', "\u{20AC}0.13"];
        yield 'decimal half a cent' => [1.005, 'EUR', "\u{20AC}1.01"];
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function nonFiniteAmounts(): iterable
    {
        yield 'not a number' => [NAN, 'NAN'];
        yield 'infinity' => [INF, 'INF'];
        yield 'negative infinity' => [-INF, '-INF'];
    }

    private function useFormatter(string $key, NumberFormatter $formatter): void
    {
        $formatters = new ReflectionProperty(CurrencyFormatter::class, 'formatters');
        /** @var array<string, NumberFormatter> $current */
        $current = $formatters->getValue();
        $current[$key] = $formatter;

        $formatters->setValue(null, $current);
    }

    private function forgetFormatter(string $key): void
    {
        $formatters = new ReflectionProperty(CurrencyFormatter::class, 'formatters');
        /** @var array<string, NumberFormatter> $current */
        $current = $formatters->getValue();
        unset($current[$key]);

        $formatters->setValue(null, $current);
    }
}
