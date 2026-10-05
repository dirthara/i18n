<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Contract\Translator as TranslatorContract;

use function strtr;

final readonly class Translator implements TranslatorContract
{
    public Locale $locale;

    public function __construct(
        private TranslationCatalogue $catalogue,
    ) {
        $this->locale = $catalogue->locale;
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function translate(string $key, array $parameters = []): string
    {
        $message = $this->catalogue->get($key) ?? $key;

        if ($parameters === []) {
            return $message;
        }

        $replacements = [];

        foreach ($parameters as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($message, $replacements);
    }

    public function has(string $key): bool
    {
        return $this->catalogue->has($key);
    }
}
