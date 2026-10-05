<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use ReflectionClass;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\InvalidTranslationCatalogueException;

use function is_string;
use function array_key_exists;

final readonly class TranslationCatalogue
{
    /**
     * @var array<string, string>
     */
    public array $messages;

    /**
     * @param array<array-key, mixed> $messages
     *
     * @throws InvalidTranslationCatalogueException
     */
    public function __construct(
        public Locale $locale,
        array $messages,
    ) {
        $keys = new TranslationKeyRule();

        // @mago-expect analysis:mixed-assignment
        foreach ($messages as $key => $message) {
            if (!is_string($key) || !$keys->allows($key)) {
                throw InvalidTranslationCatalogueException::invalidKey($locale, (string) $key);
            }

            if (!is_string($message)) {
                throw InvalidTranslationCatalogueException::nonStringMessage($locale, $key);
            }
        }

        /** @var array<string, string> $messages */
        $this->messages = $messages;
    }

    /**
     * @param array<string, string> $messages
     */
    public static function trusted(Locale $locale, array $messages): self
    {
        $catalogue = new ReflectionClass(self::class)->newInstanceWithoutConstructor();
        // @mago-expect analysis:invalid-property-write
        $catalogue->locale = $locale;
        // @mago-expect analysis:invalid-property-write
        $catalogue->messages = $messages;

        return $catalogue;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->messages);
    }

    public function get(string $key): ?string
    {
        return $this->messages[$key] ?? null;
    }
}
