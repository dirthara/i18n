---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation status for Dirthara I18n.
---

## Requirements

| Requirement                                                       | Version     | Used for                                           |
|-------------------------------------------------------------------|-------------|----------------------------------------------------|
| PHP                                                               | `^8.5`      | PHP 8.5 or a later PHP 8 release.                  |
| The `intl` extension (`ext-intl`)                                 | any         | Locales, plural rules, and every formatter.        |
| ICU, the library `intl` is built against (`lib-icu`)              | `>=67`      | List formatting and the unit formatting of durations, which ICU 67 introduced. |
| [`league/flysystem`](https://flysystem.thephpleague.com/)         | `^3.0`      | Reading PHP and JSON translation files.             |

`league/flysystem` is the only Composer package the package depends on. Composer checks the PHP, `intl`, and ICU
versions as platform requirements; `php -r 'echo INTL_ICU_VERSION;'` shows which ICU version your PHP uses.

There is no database loader. To load translations from a database, implement
[`TranslationLoader`](loading-translations.md#custom-loaders).

## Package installation

Once published, install the package using Composer:

```sh
composer require dirthara/i18n
```

:::caution
There is no published release yet. The command above describes the intended
installation after publication.
:::

For development, follow the Docker and Composer setup in the repository's
[README](https://github.com/dirthara/i18n#readme). Development tooling
includes PHPUnit, Mago, and Xdebug.
