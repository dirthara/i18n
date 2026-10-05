<?php

declare(strict_types=1);

namespace Dirthara\I18n;

use Locale as IntlLocale;
use Dirthara\I18n\Exception\InvalidLocaleException;

use function implode;
use function preg_match;
use function strtolower;
use function str_contains;

final readonly class Locale
{
    private const string PATTERN = '/^[A-Za-z0-9]+(?:[-_][A-Za-z0-9]+)*$/';

    private const string LANGUAGE = '/^[a-z]{2,3}$/';

    private const string SCRIPT = '/^[A-Z][a-z]{3}$/';

    private const string REGION = '/^(?:[A-Z]{2}|[0-9]{3})$/';

    private const string VARIANT = '/^(?:[0-9A-Za-z]{5,8}|[0-9][0-9A-Za-z]{3})$/';

    public string $code;

    public string $language;

    public ?string $script;

    public ?string $region;

    /**
     * @var list<string>
     */
    public array $variants;

    /**
     * @throws InvalidLocaleException
     */
    public function __construct(string $code)
    {
        if ($code === '' || preg_match(self::PATTERN, $code) !== 1) {
            throw InvalidLocaleException::forLocale($code);
        }

        $canonical = IntlLocale::canonicalize($code);

        // ICU canonicalises "root" and "und" to the empty root locale, which it then reads as its own default locale,
        // and turns extensions and private use into keywords after an "@", which a Locale does not hold.
        if ($canonical === null || $canonical === '' || str_contains($canonical, '@')) {
            throw InvalidLocaleException::forLocale($code);
        }

        $language = IntlLocale::getPrimaryLanguage($canonical);
        $script = $this->subtag(IntlLocale::getScript($canonical));
        $region = $this->subtag(IntlLocale::getRegion($canonical));

        // ICU reads any alphanumeric subtag in any position, so each one is held to the BCP 47 syntax, and a language,
        // script, or region ICU has no name for is not a locale.
        if (
            $language === null
            || preg_match(self::LANGUAGE, $language) !== 1
            || IntlLocale::getDisplayLanguage($language, displayLocale: 'en') === $language
        ) {
            throw InvalidLocaleException::forLocale($code);
        }

        $subtags = [$language];

        if ($script !== null) {
            if (
                preg_match(self::SCRIPT, $script) !== 1
                || IntlLocale::getDisplayScript('und_' . $script, displayLocale: 'en') === $script
            ) {
                throw InvalidLocaleException::forLocale($code);
            }

            $subtags[] = $script;
        }

        if ($region !== null) {
            if (
                preg_match(self::REGION, $region) !== 1
                || IntlLocale::getDisplayRegion('und_' . $region, displayLocale: 'en') === $region
            ) {
                throw InvalidLocaleException::forLocale($code);
            }

            $subtags[] = $region;
        }

        /** @var list<string> $found */
        $found = IntlLocale::getAllVariants($canonical) ?? [];
        $variants = [];

        foreach ($found as $variant) {
            if (preg_match(self::VARIANT, $variant) !== 1) {
                throw InvalidLocaleException::forLocale($code);
            }

            $variants[] = strtolower($variant);
            $subtags[] = strtolower($variant);
        }

        $this->code = implode(separator: '-', array: $subtags);
        $this->language = $language;
        $this->script = $script;
        $this->region = $region;
        $this->variants = $variants;
    }

    public function isRightToLeft(): bool
    {
        return IntlLocale::isRightToLeft($this->code);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    private function subtag(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
