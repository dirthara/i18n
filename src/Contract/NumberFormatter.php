<?php

declare(strict_types=1);

namespace Dirthara\I18n\Contract;

use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\CompactNumberStyle;
use Dirthara\I18n\Exception\I18nException;

interface NumberFormatter
{
    public Locale $locale { get; }

    /**
     * @throws I18nException
     */
    public function format(int|float $number): string;

    /**
     * @throws I18nException
     */
    public function compact(int|float $number, CompactNumberStyle $style = CompactNumberStyle::Short): string;

    /**
     * @throws I18nException
     */
    public function scientific(int|float $number): string;

    /**
     * @throws I18nException
     */
    public function spellOut(int|float $number): string;

    /**
     * @throws I18nException
     */
    public function ordinal(int|float $number): string;
}
