---
id: formatting-currencies
title: Formatting currencies
sidebar_position: 6
description: Formatting amounts of money for a locale, the currency styles, rounding, and the formatter factory.
---

`Dirthara\I18n\Formatter\Currency\CurrencyFormatter` implements `Dirthara\I18n\Contract\CurrencyFormatter`. It formats
an amount in a [currency](https://www.iso.org/iso-4217-currency-codes.html) the way its locale writes money: the symbol,
its position, the separators, and the number of decimals all come from the locale and the currency, through ICU and the
`intl` extension.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use Dirthara\I18n\Formatter\Currency\CurrencyFormatter;

$formatter = new CurrencyFormatter(new Locale('nl-NL'));

$formatter->format(1234.5, new Currency('EUR'));   // '€ 1.234,50'
$formatter->locale;                                 // Locale nl-NL
```

| Argument   | Type                              | Default                  | Meaning                                |
|------------|-----------------------------------|--------------------------|----------------------------------------|
| `amount`   | `int\|float`                      |                          | The amount, in the currency's major unit, such as euros rather than cents. |
| `currency` | `Dirthara\I18n\Currency`          |                          | The currency of the amount.            |
| `style`    | `Dirthara\I18n\Enum\CurrencyStyle` | `CurrencyStyle::Standard` | How the currency is written.           |

## Styles

| Style        | `en-GB`, -1234.567 EUR | `nl-NL`, 1234.5 EUR | Meaning                                                          |
|--------------|------------------------|---------------------|------------------------------------------------------------------|
| `Standard`   | `-€1,234.57`           | `€ 1.234,50`        | The currency's symbol.                                           |
| `Accounting` | `(€1,234.57)`          | `€ 1.234,50`        | The symbol, with a negative amount in the locale's accounting notation, such as parentheses. |
| `Iso`        | `-EUR 1,234.57`        | `EUR 1.234,50`      | The three-letter currency code.                                  |
| `Name`       | `-1,234.57 euros`      | `1.234,50 euro`     | The currency's name, in the plural form that suits the amount.   |
| `Cash`       | `-€1,234.57`           | `€ 1.234,50`        | The symbol, rounded to the smallest coin in use, such as 0.05 for Swiss francs. |

The output contains the characters the locale uses, which are not always plain spaces: Dutch puts a no-break space
(U+00A0) after the symbol, French separates thousands with a narrow no-break space (U+202F), and Swiss German with a
right single quotation mark (U+2019). Compare formatted amounts with that in mind.

## Rounding

An amount is rounded to the number of decimals of its currency: two for euros, none for yen, three for Kuwaiti dinars.
A half is rounded away from zero, so ¥2.5 is `JP¥3` and ¥-2.5 is `-JP¥3`, rather than ICU's default of rounding a half
to the even neighbour. The decimal an amount is written as decides: `1.005` euros is `€1.01`.

The formatter only formats. It does not convert currencies, and an amount is always in the currency's major unit.
`NAN`, `INF`, and `-INF` are not amounts: `format()` throws an `InvalidCurrencyAmountException` for them.

## Formatter factory

`Dirthara\I18n\Formatter\Currency\CurrencyFormatterFactory` implements
`Dirthara\I18n\Contract\Factory\CurrencyFormatterFactory`, and creates a formatter for a locale. Depend on the factory
contract where the locale is only known later, such as per request or per user:

```php
use Dirthara\I18n\Contract\Factory\CurrencyFormatterFactory;

final readonly class InvoiceRenderer
{
    public function __construct(
        private CurrencyFormatterFactory $currencyFormatters,
    ) {}

    public function total(Invoice $invoice): string
    {
        return $this->currencyFormatters
            ->create($invoice->customer->locale)
            ->format($invoice->total, $invoice->currency);
    }
}
```

Creating a formatter is cheap. The ICU formatter behind each locale and style is created once, the first time an amount
is formatted with it, and shared by every `CurrencyFormatter` for that locale.
