<?php

declare(strict_types=1);

namespace Dirthara\I18n;

use Locale as IntlLocale;
use Dirthara\I18n\Exception\InvalidLocaleException;

use function count;
use function substr;
use function explode;
use function implode;
use function ucfirst;
use function preg_match;
use function strtolower;
use function strtoupper;
use function str_replace;
use function array_unique;

final readonly class Locale
{
    private const string PATTERN =
        '/^(?<language>[a-z]{2,3}|[a-z]{5,8})'
            . '(?:[-_](?<script>[a-z]{4}))?'
            . '(?:[-_](?<region>[a-z]{2}|[0-9]{3}))?'
            . '(?<variants>(?:[-_](?:[0-9a-z]{5,8}|[0-9][0-9a-z]{3}))*)\z/i';

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
        $matches = [];

        if (preg_match(self::PATTERN, $code, $matches) !== 1) {
            throw InvalidLocaleException::forLocale($code);
        }

        $variants = $this->splitVariants($matches['variants']);

        if (count(array_unique($variants)) !== count($variants)) {
            throw InvalidLocaleException::forLocale($code);
        }

        $this->language = strtolower($matches['language']);
        $this->script = $matches['script'] === '' ? null : ucfirst(strtolower($matches['script']));
        $this->region = $matches['region'] === '' ? null : strtoupper($matches['region']);
        $this->variants = $variants;
        $this->code = $this->compose();
    }

    public function isRightToLeft(): bool
    {
        return IntlLocale::isRightToLeft($this->code);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * @return list<string>
     */
    private function splitVariants(string $variants): array
    {
        if ($variants === '') {
            return [];
        }

        $separated = str_replace(search: '_', replace: '-', subject: strtolower($variants));

        return explode(separator: '-', string: substr($separated, offset: 1));
    }

    private function compose(): string
    {
        $subtags = [$this->language];

        if ($this->script !== null) {
            $subtags[] = $this->script;
        }

        if ($this->region !== null) {
            $subtags[] = $this->region;
        }

        return implode(separator: '-', array: [...$subtags, ...$this->variants]);
    }
}
