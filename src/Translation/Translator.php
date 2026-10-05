<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use Dirthara\I18n\PluralRules;
use Dirthara\I18n\Enum\PluralCategory;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Contract\Translator as TranslatorContract;

use function strtr;
use function is_int;
use function is_finite;

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
     *
     * @throws I18nException
     */
    public function translatePlural(string $key, int|float $count, array $parameters = []): string
    {
        return $this->replace($this->pluralMessage($key, $count) ?? $key, $parameters + ['count' => $count]);
    }

    public function has(string $key): bool
    {
        return $this->catalogue->has($key);
    }

    /**
     * @throws I18nException
     */
    public function hasPlural(string $key, int|float $count): bool
    {
        return $this->pluralMessage($key, $count) !== null;
    }

    /**
     * @throws I18nException
     */
    private function pluralMessage(string $key, int|float $count): ?string
    {
        if (is_int($count) || is_finite($count)) {
            $exact = $this->catalogue->get($key . '.' . $count);

            if ($exact !== null) {
                return $exact;
            }
        }

        return (
            $this->catalogue->get($key . '.' . $this->pluralRules->category($count)->value) ?? $this->catalogue->get(
                $key . '.' . PluralCategory::Other->value,
            )
        );
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
