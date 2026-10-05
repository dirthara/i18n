---
id: intro
title: Dirthara I18n
sidebar_position: 1
description: What Dirthara I18n does, the concepts it works with, and where each part is documented.
---

Dirthara I18n provides internationalisation for the Dirthara framework: locales, currencies, and loading, caching, and
translating translations.

:::note
The package is in early development. Translations can be loaded, cached, and translated from several loaders for one
locale; overriding one loader's translations with another's, and locale fallback, are not implemented yet.
:::

## Concepts

| Concept     | Meaning                                                                                                   |
|-------------|-----------------------------------------------------------------------------------------------------------|
| Locale      | A well-formed BCP 47 language tag, such as `en-GB`, in canonical form.                                     |
| Catalogue   | The translations of one locale from one source, by key: a `TranslationCatalogue`.                         |
| Loader      | Reads one configured source, such as a directory of PHP files or a database table, into a catalogue.     |
| Prefix      | An optional namespace a loader puts in front of every key it loads, such as `dirthara.validation`.      |
| Combined loader | Combines the catalogues of several loaders into one, rejecting a key two of them produce.          |
| Cache       | Stores catalogues as compiled PHP files, so a request does not read the source again.                    |
| Translator  | Translates keys from one catalogue, picking plural forms and filling in placeholders.                    |

Each of these is a separate responsibility. A loader reads a source and nothing else, caching wraps any loader, and the
translator looks keys up in one catalogue and fills in placeholders. Falling back from one locale to
another belongs to a layer that does not exist yet. No loader falls back from `nl-NL` to `nl` or to any other locale.

## Pages

- [Installation](installation.md): requirements and installing the package.
- [Loading translations](loading-translations.md): catalogues, the PHP, JSON, and database loaders, prefixes, and custom
  loaders.
- [Caching translations](caching-translations.md): the compiled PHP cache, its file format, and invalidating it.
- [Translating](translating.md): the translator, missing translations, placeholders, and plurals.
- [Exceptions](exceptions.md): what each exception means and the context it carries.
