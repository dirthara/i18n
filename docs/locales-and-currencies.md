---
id: locales-and-currencies
title: Locales and currencies
sidebar_position: 3
description: The Locale and Currency value objects, what they accept, and how they normalise their codes.
---

Every translation and formatter works with a `Dirthara\I18n\Locale`, and the currency formatter with a
`Dirthara\I18n\Currency`. Both are read-only value objects that check their code when they are created.

## Locale

A locale is a [BCP 47](https://www.rfc-editor.org/info/bcp47) language tag, such as `en-GB` or `zh-Hant-TW`.

```php
use Dirthara\I18n\Locale;

$locale = new Locale('sr_latn_rs');

$locale->code;       // 'sr-Latn-RS'
$locale->language;   // 'sr'
$locale->script;     // 'Latn'
$locale->region;     // 'RS'
$locale->variants;   // []

new Locale('he-IL')->isRightToLeft();                // true
new Locale('en-GB')->equals(new Locale('en_gb'));    // true
```

| Part       | Form                                                           | Written as     |
|------------|----------------------------------------------------------------|----------------|
| `language` | 2 or 3 letters, or 5 to 8 letters                              | lower case     |
| `script`   | 4 letters, optional                                            | title case     |
| `region`   | 2 letters or 3 digits, optional                                | upper case     |
| `variants` | each 5 to 8 letters and digits, or a digit and 3 more, any number | lower case  |

The parts can be separated by `-` or `_`, and in any case: `sr_latn_rs` is `sr-Latn-RS`. `code` is always the
canonical form, so two locales are equal when their codes are. A tag is checked for its form only, not against a list of
assigned codes, so a well-formed tag such as `zz` or `en-AA` is a locale. The formatters do need ICU to know the
language: a language it has no data for throws a `FormatterException` when a formatter is first used.

A tag that is not well-formed throws an `InvalidLocaleException`. So does one that repeats a variant, such as
`de-1996-1996`, and one with an extension or a private use part, such as `en-US-u-ca-gregory`, which a `Locale` has
nowhere to keep. Old tags such as `i-klingon` are not translated to their current form.

## Currency

A currency is an [ISO 4217](https://www.iso.org/iso-4217-currency-codes.html) code of three letters.

```php
use Dirthara\I18n\Currency;

$currency = new Currency('eur');

$currency->code;                          // 'EUR'
$currency->equals(new Currency('EUR'));   // true
```

The code is written in upper case. Any three ASCII letters are accepted, including codes for withdrawn currencies such as
`NLG` and codes no currency has; anything else throws an `InvalidCurrencyException`. See
[formatting currencies](formatting-currencies.md) for writing an amount in a currency.

## Plural rules

`Dirthara\I18n\PluralRules` gives the CLDR plural category of a number for a locale, as a
`Dirthara\I18n\Enum\PluralCategory`. The translator uses it to pick a plural form; see
[plurals](translating.md#plurals).
