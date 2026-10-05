<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function preg_match;

final readonly class TranslationPrefix
{
    private const string PATTERN = '/^[^.]+(?:\.[^.]+)*\z/';

    /**
     * @throws InvalidTranslationPrefixException
     */
    public function __construct(
        public ?string $prefix,
    ) {
        if ($prefix !== null && preg_match(self::PATTERN, $prefix) !== 1) {
            throw InvalidTranslationPrefixException::forPrefix($prefix);
        }
    }

    public function apply(string $key): string
    {
        return $this->prefix === null ? $key : $this->prefix . '.' . $key;
    }
}
