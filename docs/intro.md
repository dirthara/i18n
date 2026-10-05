---
id: intro
title: Dirthara I18n
sidebar_position: 1
description: What Dirthara I18n does, the concepts it works with, and where each part is documented.
---

Dirthara I18n provides internationalisation for the Dirthara framework: locales, currencies, loading, caching, and
translating translations, and formatting values for a locale.

:::note
The package has no release yet; 0.1.0 will be its first. See [the scope of 0.1](#scope-of-01) for what it contains and
what is left for later.
:::

## Concepts

| Concept     | Meaning                                                                                                   |
|-------------|-----------------------------------------------------------------------------------------------------------|
| Locale      | A well-formed BCP 47 language tag, such as `en-GB`, in canonical form.                                     |
| Catalogue   | The translations of one locale from one source, by key: a `TranslationCatalogue`.                         |
| Loader      | Reads one configured source, such as a directory of PHP files or JSON files, into a catalogue.           |
| Prefix      | An optional namespace a loader puts in front of every key it loads, such as `dirthara.validation`.      |
| Combined loader | Combines the catalogues of several loaders into one, rejecting a key two of them produce.          |
| Cache       | Stores catalogues as compiled PHP files, so a request does not read the source again.                    |
| Translator  | Translates keys from one catalogue, picking plural forms and filling in placeholders.                    |
| Formatter   | Writes a value, such as an amount of money, the way a locale writes it.                                  |
| Factory     | Creates a formatter for a locale, for code that only learns the locale later.                            |

Each of these is a separate responsibility. A loader reads a source and nothing else, caching wraps any loader, and the
translator looks keys up in one catalogue and fills in placeholders. No loader falls back from `nl-NL` to `nl` or to any
other locale.

## Scope of 0.1

Version 0.1 contains:

| Area         | What it contains                                                                                   |
|--------------|----------------------------------------------------------------------------------------------------|
| Values       | `Locale`, `Currency`, and `PluralRules` with the `PluralCategory` enum.                            |
| Translations | Translation catalogues; the PHP, JSON, and combined loaders; the compiled translation cache; the translator, with plural translations. |
| Formatters   | Number, percentage, currency, list, locale, date-time, duration, and relative date-time formatters, each with a factory. |

These are not part of 0.1, and may come in a later version:

| Not included                                   | Instead, for now                                                          |
|------------------------------------------------|---------------------------------------------------------------------------|
| Formatting measurements and units in general, such as `12 km` or `3 kg` | Only lengths of time, through the [duration formatter](formatting-durations.md). |
| Naming timezones, such as `Central European Time` | The full and long styles of the [date-time formatter](formatting-dates-and-times.md) name the formatter's timezone. |
| Falling back from one locale to another, such as from `nl-BE` to `nl` | Load and combine the catalogues of each locale you need yourself.         |
| Letting one loader override another's translations | Give each source its own keys, with a [prefix](loading-translations.md#prefixes). |
| A database translation loader                  | Implement [`TranslationLoader`](loading-translations.md#custom-loaders) for a database or a remote source; a loader of your own can be combined and cached like any other. |

## Pages

- [Installation](installation.md): requirements and installing the package.
- [Loading translations](loading-translations.md): catalogues, the PHP and JSON loaders, prefixes, combining, and custom
  loaders.
- [Caching translations](caching-translations.md): the compiled PHP cache, its file format, and invalidating it.
- [Translating](translating.md): the translator, missing translations, placeholders, and plurals.
- [Formatting currencies](formatting-currencies.md): the currency formatter, its styles, rounding, and its factory.
- [Formatting numbers](formatting-numbers.md): plain, compact, scientific, spelled-out, and ordinal numbers.
- [Formatting percentages](formatting-percentages.md): fractions as percentages.
- [Formatting lists](formatting-lists.md): joining strings with the locale's separators and conjunctions.
- [Formatting locales](formatting-locales.md): naming locales and their language, region, script, and variants.
- [Formatting dates and times](formatting-dates-and-times.md): dates and times in a locale and timezone.
- [Formatting durations](formatting-durations.md): lengths of time, written out or as on a clock.
- [Formatting relative dates and times](formatting-relative-dates-and-times.md): how far one moment is from another.
- [Exceptions](exceptions.md): what each exception means and the context it carries.
