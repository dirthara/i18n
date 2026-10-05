<?php

declare(strict_types=1);

namespace Dirthara\I18n;

use MessageFormatter;
use Dirthara\I18n\Enum\PluralCategory;

final class PluralRules
{
    private const string PATTERN = '{0,plural,zero{zero}one{one}two{two}few{few}many{many}other{other}}';

    /**
     * @var array<string, MessageFormatter>
     */
    private static array $formatters = [];

    public function __construct(
        public readonly Locale $locale,
    ) {}

    public function category(int|float $count): PluralCategory
    {
        self::$formatters[$this->locale->code] ??= new MessageFormatter($this->locale->code, self::PATTERN);

        return PluralCategory::tryFrom((string) self::$formatters[$this->locale->code]->format([$count]))
        ?? PluralCategory::Other;
    }
}
