<?php

declare(strict_types=1);

namespace Dirthara\I18n;

use MessageFormatter;
use Dirthara\I18n\Enum\PluralCategory;
use Dirthara\I18n\Exception\PluralRulesException;

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

    /**
     * @throws PluralRulesException
     */
    public function category(int|float $count): PluralCategory
    {
        self::$formatters[$this->locale->code] ??= new MessageFormatter($this->locale->code, self::PATTERN);

        $formatter = self::$formatters[$this->locale->code];
        $category = $formatter->format([$count]);

        if ($category === false) {
            throw PluralRulesException::formatFailed(
                $this->locale,
                $count,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
            );
        }

        return (
            PluralCategory::tryFrom($category) ?? throw PluralRulesException::unknownCategory(
                $this->locale,
                $count,
                $category,
            )
        );
    }
}
