<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests;

use Dirthara\I18n\Currency;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Exception\InvalidCurrencyException;

final class CurrencyTest extends TestCase
{
    #[Test]
    #[DataProvider('validCurrencies')]
    public function it_accepts_a_valid_currency(string $input, string $code): void
    {
        self::assertSame($code, new Currency($input)->code);
    }

    #[Test]
    #[DataProvider('invalidCurrencies')]
    public function it_rejects_an_invalid_currency(string $input): void
    {
        $this->expectException(InvalidCurrencyException::class);

        new Currency($input);
    }

    #[Test]
    public function it_reports_the_rejected_currency(): void
    {
        try {
            new Currency('EURO');
            self::fail('Expected an InvalidCurrencyException.');
        } catch (InvalidCurrencyException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame('EURO is not a valid currency.', $exception->getMessage());
            self::assertSame(['currency' => 'EURO'], $exception->context);
            self::assertNull($exception->getPrevious());
        }
    }

    #[Test]
    public function it_escapes_control_characters_in_the_message(): void
    {
        try {
            new Currency("EU\nR");
            self::fail('Expected an InvalidCurrencyException.');
        } catch (InvalidCurrencyException $exception) {
            self::assertSame('EU\\nR is not a valid currency.', $exception->getMessage());
            self::assertSame(['currency' => "EU\nR"], $exception->context);
        }
    }

    #[Test]
    public function it_equals_a_currency_with_the_same_code(): void
    {
        $currency = new Currency('EUR');

        self::assertTrue($currency->equals($currency));
        self::assertTrue($currency->equals(new Currency('eur')));
    }

    #[Test]
    public function it_does_not_equal_a_currency_with_another_code(): void
    {
        self::assertFalse(new Currency('EUR')->equals(new Currency('USD')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validCurrencies(): iterable
    {
        yield 'uppercase' => ['EUR', 'EUR'];
        yield 'lowercase' => ['usd', 'USD'];
        yield 'mixed case' => ['Jpy', 'JPY'];
        yield 'withdrawn' => ['NLG', 'NLG'];
        yield 'precious metal' => ['XAU', 'XAU'];
        yield 'no currency' => ['XXX', 'XXX'];
        yield 'unassigned' => ['QQQ', 'QQQ'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCurrencies(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['EU'];
        yield 'too long' => ['EURO'];
        yield 'digit' => ['12A'];
        yield 'numeric code' => ['978'];
        yield 'symbol' => ['€'];
        yield 'non-ASCII letter' => ['ÉUR'];
        yield 'space' => ['EU R'];
        yield 'surrounding space' => [' EUR'];
        yield 'trailing newline' => ["EUR\n"];
    }
}
