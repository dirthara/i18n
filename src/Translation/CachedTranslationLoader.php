<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Contract\TranslationCache;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;

final readonly class CachedTranslationLoader implements TranslationLoader
{
    public function __construct(
        private TranslationLoader $loader,
        private TranslationCache $cache,
        private string $key,
    ) {}

    /**
     * @throws I18nException
     */
    public function load(Locale $locale): TranslationCatalogue
    {
        $cached = $this->cache->get($this->key, $locale);

        if ($cached !== null) {
            return $cached;
        }

        $catalogue = $this->loader->load($locale);

        if (!$catalogue->locale->equals($locale)) {
            throw TranslationLoaderException::localeMismatch($locale, $catalogue->locale);
        }

        $this->cache->put($this->key, $catalogue);

        return $catalogue;
    }
}
