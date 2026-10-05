---
id: installation
title: Installation
sidebar_position: 2
description: Requirements, ICU data, and installing Dirthara I18n.
---

## Requirements

| Requirement                                                       | Version     | Used for                                           |
|-------------------------------------------------------------------|-------------|----------------------------------------------------|
| PHP                                                               | `^8.5`      | PHP 8.5 or a later PHP 8 release.                  |
| The `intl` extension (`ext-intl`)                                 | any         | Locales, plural rules, and every formatter.        |
| ICU, the library `intl` is built against (`lib-icu`)              | `>=67`      | `IntlListFormatter`, which needs ICU 67; see [ICU data](#icu-data). |
| [`league/flysystem`](https://flysystem.thephpleague.com/)         | `^3.0`      | Reading PHP and JSON translation files.             |

`league/flysystem` is the only Composer package the package depends on. Composer checks the PHP, `intl`, and ICU
versions as platform requirements; `php -r 'echo INTL_ICU_VERSION;'` shows which ICU version your PHP uses.

There is no database loader. To load translations from a database, implement
[`TranslationLoader`](loading-translations.md#custom-loaders).

## ICU data

Every formatter and the plural rules get their locale data from ICU, the library the `intl` extension is built against,
and from the CLDR data that comes with it. Most of that goes through `intl` classes such as `NumberFormatter` and
`IntlDateFormatter`. PHP has no class for relative times, durations in units, or a few parts of locale names, so for
those the package reads ICU's data bundles directly through `ResourceBundle`:

| Bundle         | Data                                      | Used by                                        |
|----------------|-------------------------------------------|------------------------------------------------|
| `ICUDATA`      | Relative time patterns and names          | The relative date-time formatter               |
| `ICUDATA-unit` | The digital duration patterns             | The duration formatter                         |
| `ICUDATA-lang` | The separator between parts of a locale name | The locale formatter                        |

The minimum of ICU 67 comes from `IntlListFormatter`, which the list formatter and the duration formatter use; every
other ICU feature and data bundle the package relies on is older.

:::caution
CLDR changes between ICU releases, so the exact output of a formatter can change when the ICU version of a server does:
an abbreviation, a space that becomes a no-break space, or a newly translated name. Do not compare formatted output
with hard-coded strings across servers or upgrades. The package's tests check what a formatter does, such as which
form, unit, or plural category it picks, on the ICU version they run with, and leave the locale data itself to ICU.
:::

## Package installation

Install the package using Composer:

```sh
composer require dirthara/i18n
```

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/i18n#readme). Development tooling
includes PHPUnit, Mago, and Xdebug.
