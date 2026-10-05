<?php

declare(strict_types=1);

namespace Dirthara\I18n;

use IntlException;
use MessageFormatter;
use Dirthara\I18n\Enum\PluralCategory;
use Dirthara\I18n\Exception\PluralRulesException;
use Dirthara\I18n\Exception\InvalidPluralCountException;

use function is_float;
use function is_finite;

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
     * @throws InvalidPluralCountException
     */
    public function category(int|float $count): PluralCategory
    {
        if (is_float($count) && !is_finite($count)) {
            throw InvalidPluralCountException::notFinite($this->locale, $count);
        }

        $formatter = $this->formatter();
        $failure = null;

        try {
            $category = $formatter->format([$count]);
        } catch (IntlException $failure) {
            $category = false;
        }

        if ($category === false) {
            throw PluralRulesException::formatFailed(
                $this->locale,
                $count,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
                previous: $failure,
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

    /**
     * @throws PluralRulesException
     */
    private function formatter(): MessageFormatter
    {
        $formatter = self::$formatters[$this->locale->code] ?? null;

        if ($formatter !== null) {
            return $formatter;
        }

        try {
            $formatter = new MessageFormatter($this->locale->code, self::PATTERN);
        } catch (IntlException $exception) {
            throw PluralRulesException::creationFailed($this->locale, previous: $exception);
        }

        self::$formatters[$this->locale->code] = $formatter;

        return $formatter;
    }
}
