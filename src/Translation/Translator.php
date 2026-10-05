<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\I18n\PluralRules;
use Dirthara\I18n\Enum\PluralCategory;
use Dirthara\I18n\Contract\Translator as TranslatorContract;

use function strtr;

final readonly class Translator implements TranslatorContract
{
    public Locale $locale;

    private PluralRules $pluralRules;

    public function __construct(
        private TranslationCatalogue $catalogue,
    ) {
        $this->locale = $catalogue->locale;
        $this->pluralRules = new PluralRules($catalogue->locale);
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function translate(string $key, array $parameters = []): string
    {
        return $this->replace($this->catalogue->get($key) ?? $key, $parameters);
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function translatePlural(string $key, int|float $count, array $parameters = []): string
    {
        $message =
            $this->catalogue->get($key . '.' . $count) ?? $this->catalogue->get(
                $key . '.' . $this->pluralRules->category($count)->value,
            ) ?? $this->catalogue->get($key . '.' . PluralCategory::Other->value) ?? $key;

        return $this->replace($message, $parameters + ['count' => $count]);
    }

    public function has(string $key): bool
    {
        return $this->catalogue->has($key);
    }

    /**
     * @param array<string, string|int|float> $parameters
     */
    private function replace(string $message, array $parameters): string
    {
        if ($parameters === []) {
            return $message;
        }

        $replacements = [];

        foreach ($parameters as $name => $value) {
            $replacements['{' . $name . '}'] = (string) $value;
        }

        return strtr($message, $replacements);
    }
}
