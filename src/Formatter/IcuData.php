<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter;

use IntlException;
use ResourceBundle;
use Dirthara\I18n\Locale;

use function is_string;
use function array_filter;
use function array_values;

/**
 * ResourceBundle does not apply ICU's locale fallback to nested data, so the fallback is done here.
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
        try {
            $value = ResourceBundle::create($name, $bundle, fallback: false);

            foreach ($path as $key) {
                $value = $value instanceof ResourceBundle ? $value->get($key) : null;
            }
        } catch (IntlException) {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
