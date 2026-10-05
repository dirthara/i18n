---
id: translating
title: Translating
sidebar_position: 5
description: Translating keys from a translation catalogue, plural forms, and filling placeholders with parameters.
---

`Dirthara\I18n\Translation\Translator` implements `Dirthara\I18n\Contract\Translator`. It translates keys from one
[translation catalogue](loading-translations.md#catalogues), and its locale is the catalogue's locale.

```php
use Dirthara\I18n\Locale;
use Dirthara\I18n\Translation\Translator;

$translator = new Translator($loader->load(new Locale('en-GB')));

$translator->locale;                                             // Locale en-GB
$translator->has('validation.required');                         // true
$translator->translate('validation.required', ['attribute' => 'email']);
// 'The email field is required.'
```

| Argument    | Type                                          | Meaning                                    |
|-------------|-----------------------------------------------|--------------------------------------------|
| `catalogue` | `Dirthara\I18n\Translation\TranslationCatalogue` | The translations to translate from.   |

The translator does not load, combine, or cache anything, and constructing it cannot fail. Everything before it is up
to the loaders: [combine](loading-translations.md#combining-loaders) the sources a catalogue needs, such as PHP files,
JSON files, and custom loaders, and [cache](caching-translations.md) the result, so that a request includes one file
and nothing is loaded, combined, or checked for conflicts again:

```php
$loader = new CachedTranslationLoader(
    loader: new CombinedTranslationLoader($phpLoader, $jsonLoader, $packageLoader),
    cache: $cache,
    cacheKey: 'application-translations-v1',
);

$translator = new Translator($loader->load(new Locale('en-GB')));
```

A translator does not fall back to another locale: a key the catalogue does not have is missing, even when `en` or
another locale has it.

## Missing translations

`translate()` returns the key itself when the catalogue has no translation for it, so a missing translation shows up as
the key, such as `validation.required`, rather than breaking the page. Use `has()` to find out whether a translation
exists: it checks the exact key `translate()` looks up, and nothing else, so it is `false` for a key that only has plural
forms, such as `inbox.messages`. A translation that is the empty string exists, and `translate()` returns the empty
string for it.

## Parameters

A message can hold placeholders, written as a parameter name in braces. `translate()` replaces each placeholder that
has a parameter with the parameter's value:

```php
// 'Hello {name}, you have {count} messages.'
$translator->translate('inbox.summary', ['name' => 'Ada', 'count' => 3]);
// 'Hello Ada, you have 3 messages.'
```

- **Every occurrence** of a placeholder is replaced.
- **A placeholder without a parameter** is left as it is, and a parameter without a placeholder is ignored.
- **Only the exact name in braces** is a placeholder: `{ name }` and `{Name}` are not `{name}`.
- **Values are inserted once.** A value that contains a placeholder, such as `{site}`, is not replaced again.
- **There are no escaping rules.** Apostrophes and other braces are plain text.
- **Numbers are converted as PHP converts them,** such as `1.5`, without locale-aware formatting. Format a number,
  date, or amount for the locale before passing it as a parameter.

Parameters are also applied to the key that is returned when a translation is missing.

A message is text with placeholders and nothing more; there is no syntax inside a message. Plural forms are separate
keys; see [plurals](#plurals).

## Plurals

`translatePlural()` translates a message whose wording depends on a number. Each plural form is a key of its own, under
the message's key, so plural forms are written like any other translation:

```php
// translations/en-GB/inbox.php
return [
    'messages' => [
        '0' => 'You have no messages.',
        'one' => 'You have {count} message.',
        'other' => 'You have {count} messages.',
    ],
];
```

```json
{
    "cart.items.one": "{count} item in your cart",
    "cart.items.other": "{count} items in your cart"
}
```

```php
$translator->translatePlural('inbox.messages', 0);   // 'You have no messages.'
$translator->translatePlural('inbox.messages', 1);   // 'You have 1 message.'
$translator->translatePlural('inbox.messages', 7);   // 'You have 7 messages.'
$translator->translatePlural('cart.items', 3);       // '3 items in your cart'
```

The key is the message's key without a form, after any [prefix](loading-translations.md#prefixes): with the prefix
`app`, the PHP file above is `app.inbox.messages`. `translatePlural()` uses the first of these that exists:

1. **The exact count**, such as `inbox.messages.0`. A message for one particular number takes precedence over its plural
   form, which is how English gets a separate text for nothing: in English, 0 belongs to `other`.
2. **The plural category** of the count in the translator's locale, such as `inbox.messages.one`.
3. **The `other` form**, such as `inbox.messages.other`.
4. **The key itself**, as for a [missing translation](#missing-translations).

The plural categories are CLDR's: `zero`, `one`, `two`, `few`, `many`, and `other`. Which numbers belong to which
category depends on the language, and comes from ICU through the `intl` extension:

| Language | Categories                              | Examples                                     |
|----------|-----------------------------------------|----------------------------------------------|
| English  | `one`, `other`                          | 1 is `one`; 0, 2, and 1.5 are `other`        |
| French   | `one`, `many`, `other`                  | 0, 1, and 1.5 are `one`                      |
| Polish   | `one`, `few`, `many`, `other`           | 1 is `one`, 22 is `few`, 5 is `many`         |
| Arabic   | `zero`, `one`, `two`, `few`, `many`, `other` | 0, 1, 2, 3, 11, and 100 are each a different one |
| Japanese | `other`                                 | every number is `other`                      |

Write a form for each category the language uses, and always an `other` form, which every language has and which is
used when a category's form is missing.

`{count}` in a plural message is the count, converted as PHP converts numbers. Pass a `count` parameter to use another
text, such as a number already formatted for the locale:

```php
$translator->translatePlural('visitors', 1500, ['count' => '1,500']);   // '1,500 visitors'
```

The form is always chosen by the count argument, never by a `count` parameter.

A count is any finite integer or float, negative ones included: CLDR gives `-1` a category like any other number. `NAN`,
`INF`, and `-INF` are not counts: `translatePlural()`, `hasPlural()`, and `PluralRules::category()` throw an
`InvalidPluralCountException` for them, even when a translation exists under a key such as `inbox.messages.INF`.

`hasPlural()` finds out whether `translatePlural()` has a translation for a count, by the same steps: it is `true` when
the exact count, the count's category, or `other` exists, and `false` when `translatePlural()` would return the key.
The key on its own, without a form, is not a plural form:

```php
// 'inbox.messages.one' and 'inbox.messages.other' exist, 'inbox.messages' does not
$translator->has('inbox.messages');           // false
$translator->hasPlural('inbox.messages', 2);  // true
```

`Dirthara\I18n\PluralRules` gives the plural category of a count for a locale on its own, as a
`Dirthara\I18n\Enum\PluralCategory`:

```php
use Dirthara\I18n\PluralRules;

new PluralRules(new Locale('pl-PL'))->category(5);   // PluralCategory::Many
```
