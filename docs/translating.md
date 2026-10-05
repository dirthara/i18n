---
id: translating
title: Translating
sidebar_position: 5
description: Translating keys from a translation catalogue, and filling placeholders with parameters.
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
JSON files, and a database table, and [cache](caching-translations.md) the result, so that a request includes one file
and nothing is loaded, combined, or checked for conflicts again:

```php
$loader = new CachedTranslationLoader(
    loader: new CombinedTranslationLoader($phpLoader, $jsonLoader, $databaseLoader),
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
exists. A translation that is the empty string exists, and `translate()` returns the empty string for it.

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

There is no plural or select syntax: a message is text with placeholders, nothing more.
