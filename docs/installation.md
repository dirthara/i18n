---
id: installation
title: Installation
sidebar_position: 2
description: Requirements and installation status for Dirthara I18n.
---

## Requirements

PHP 8.5 or later within the PHP 8 series is required, with the `intl` extension.
The package has no runtime Composer dependencies beyond PHP.

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
