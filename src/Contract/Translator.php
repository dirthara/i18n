<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Exception\I18nException;

interface Translator
{
    public Locale $locale { get; }

    /**
     * @param array<string, string|int|float> $parameters
     */
    public function translate(string $key, array $parameters = []): string;

    /**
     * @param array<string, string|int|float> $parameters
     *
     * @throws I18nException
     */
    public function translatePlural(string $key, int|float $count, array $parameters = []): string;

    public function has(string $key): bool;

    /**
     * @throws I18nException
     */
    public function hasPlural(string $key, int|float $count): bool;
}
