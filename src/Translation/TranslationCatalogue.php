<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\InvalidTranslationCatalogueException;

use function is_string;
use function array_key_exists;

/**
 * The translations of one locale, by key.
 *
 * PHP stores an array key that is a decimal integer, such as "404", as an int, so a key can be an int in $messages.
 * Looking it up by its string works the same.
 */
final readonly class TranslationCatalogue
{
    /**
     * @var array<array-key, string>
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
        $validated = [];

        // @mago-expect analysis:mixed-assignment
        foreach ($messages as $key => $message) {
            if (!is_string($message)) {
                throw InvalidTranslationCatalogueException::nonStringMessage($locale, (string) $key);
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
