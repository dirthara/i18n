<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter;

use ResourceBundle;
use Dirthara\I18n\Locale;

use function is_string;
use function array_filter;
use function array_values;

/**
 * Reading nested ICU data through ResourceBundle does not apply ICU's locale fallback, so a value is looked up in the
 * locale's own bundle first, then in the bundles of its less specific forms, and last in the root bundle.
 *
 * @internal
 */
final readonly class IcuData
{
    public function string(Locale $locale, string $bundle, string ...$path): ?string
    {
        foreach ($this->candidates($locale) as $candidate) {
            $value = $this->stringFrom($candidate, $bundle, ...$path);

            if ($value !== null) {
                return $value;
            }
        }

        return $this->stringFrom('root', $bundle, ...$path);
    }

    /**
     * The bundles of a locale and its less specific forms, most specific first, without the root bundle.
     *
     * @return list<string>
     */
    public function candidates(Locale $locale): array
    {
        return array_values(array_filter([
            $locale->script !== null && $locale->region !== null
                ? $locale->language . '_' . $locale->script . '_' . $locale->region
                : null,
            $locale->script !== null ? $locale->language . '_' . $locale->script : null,
            $locale->region !== null ? $locale->language . '_' . $locale->region : null,
            $locale->language,
        ]));
    }

    public function stringFrom(string $name, string $bundle, string ...$path): ?string
    {
        $value = ResourceBundle::create($name, $bundle, fallback: false);

        foreach ($path as $key) {
            $value = $value instanceof ResourceBundle ? $value->get($key) : null;
        }

        return is_string($value) ? $value : null;
    }
}
