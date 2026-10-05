<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;

use function array_key_exists;

final readonly class CombinedTranslationLoader implements TranslationLoader
{
    /**
     * @var array<int|string, TranslationLoader>
     */
    private array $loaders;

    public function __construct(TranslationLoader ...$loaders)
    {
        $this->loaders = $loaders;
    }

    /**
     * @throws I18nException
     */
    public function load(Locale $locale): TranslationCatalogue
    {
        $messages = [];
        $origins = [];

        foreach ($this->loaders as $position => $loader) {
            foreach ($this->loadFrom($loader, $position, $locale)->messages as $key => $message) {
                if (array_key_exists($key, $origins)) {
                    throw TranslationLoaderException::conflictingKey($locale, $key, $origins[$key], $position);
                }

                $origins[$key] = $position;
                $messages[$key] = $message;
            }
        }

        return new TranslationCatalogue($locale, $messages);
    }

    /**
     * @throws I18nException
     */
    private function loadFrom(TranslationLoader $loader, int|string $position, Locale $locale): TranslationCatalogue
    {
        $catalogue = $loader->load($locale);

        if (!$catalogue->locale->equals($locale)) {
            throw TranslationLoaderException::localeMismatch($locale, $catalogue->locale)->addContext([
                'loader' => $position,
            ]);
        }

        return $catalogue;
    }
}
