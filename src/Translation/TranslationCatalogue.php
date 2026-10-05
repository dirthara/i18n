<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

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
        $validated = [];

        // @mago-expect analysis:mixed-assignment
        foreach ($messages as $key => $message) {
            if (!is_string($key) || !$keys->allows($key)) {
                throw InvalidTranslationCatalogueException::invalidKey($locale, (string) $key);
            }

            if (!is_string($message)) {
                throw InvalidTranslationCatalogueException::nonStringMessage($locale, $key);
            }

            $validated[$key] = $message;
        }

        $this->messages = $validated;
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
