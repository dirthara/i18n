<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Locale;

use Dirthara\I18n\Locale;
use Locale as IntlLocale;
use Dirthara\I18n\Formatter\IcuData;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Contract\LocaleFormatter as LocaleFormatterContract;

use function count;
use function strtr;
use function implode;
use function strtoupper;
use function array_shift;
use function str_replace;
use function intl_get_error_code;
use function intl_get_error_message;

final readonly class LocaleFormatter implements LocaleFormatterContract
{
    private const string FORMATTER = 'locale';

    public function __construct(
        public Locale $locale,
    ) {}

    /**
     * @throws FormatterException
     */
    public function format(Locale $locale): string
    {
        $name = $this->displayed(IntlLocale::getDisplayName($locale->code, $this->locale->code), 'name');

        if (count($locale->variants) < 2) {
            return $name;
        }

        return str_replace(strtoupper(implode('_', $locale->variants)), $this->joined($this->variants($locale)), $name);
    }

    /**
     * @throws FormatterException
     */
    public function language(Locale $locale): string
    {
        return $this->displayed(IntlLocale::getDisplayLanguage($locale->code, $this->locale->code), 'language');
    }

    /**
     * @throws FormatterException
     */
    public function region(Locale $locale): ?string
    {
        if ($locale->region === null) {
            return null;
        }

        return $this->displayed(IntlLocale::getDisplayRegion('und-' . $locale->region, $this->locale->code), 'region');
    }

    /**
     * @throws FormatterException
     */
    public function script(Locale $locale): ?string
    {
        if ($locale->script === null) {
            return null;
        }

        return $this->displayed(IntlLocale::getDisplayScript('und-' . $locale->script, $this->locale->code), 'script');
    }

    /**
     * @return list<string>
     *
     * @throws FormatterException
     */
    public function variants(Locale $locale): array
    {
        $names = [];

        foreach ($locale->variants as $variant) {
            $names[] = $this->displayed(
                IntlLocale::getDisplayVariant('und__' . strtoupper($variant), $this->locale->code),
                'variant',
            );
        }

        return $names;
    }

    /**
     * @throws FormatterException
     */
    private function displayed(string|false $name, string $style): string
    {
        if ($name === false) {
            throw FormatterException::formatFailed(
                self::FORMATTER,
                $this->locale,
                $style,
                intl_get_error_code(),
                intl_get_error_message(),
            );
        }

        return $name;
    }

    /**
     * @param list<string> $names
     */
    private function joined(array $names): string
    {
        $separator = $this->separator();
        $joined = (string) array_shift($names);

        foreach ($names as $name) {
            $joined = strtr($separator, ['{0}' => $joined, '{1}' => $name]);
        }

        return $joined;
    }

    private function separator(): string
    {
        return new IcuData()->string($this->locale, 'ICUDATA-lang', 'localeDisplayPattern', 'separator') ?? '{0}, {1}';
    }
}
