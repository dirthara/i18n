<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter;

use ResourceBundle;
use Dirthara\I18n\Locale;

use function is_string;
use function array_filter;

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
        $candidates = array_filter([
            $locale->script !== null && $locale->region !== null
                ? $locale->language . '_' . $locale->script . '_' . $locale->region
                : null,
            $locale->script !== null ? $locale->language . '_' . $locale->script : null,
            $locale->region !== null ? $locale->language . '_' . $locale->region : null,
            $locale->language,
            'root',
        ]);

        foreach ($candidates as $candidate) {
            $value = $this->lookup($candidate, $bundle, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, string> $path
     */
    private function lookup(string $name, string $bundle, array $path): ?string
    {
        $value = ResourceBundle::create($name, $bundle, fallback: false);

        foreach ($path as $key) {
            $value = $value instanceof ResourceBundle ? $value->get($key) : null;
        }

        return is_string($value) ? $value : null;
    }
}
