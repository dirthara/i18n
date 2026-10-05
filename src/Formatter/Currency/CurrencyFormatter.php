<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\Currency;

use ValueError;
use IntlException;
use NumberFormatter;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use Dirthara\I18n\Enum\CurrencyStyle;
use Dirthara\I18n\Exception\CurrencyFormatterException;
use Dirthara\I18n\Exception\InvalidCurrencyAmountException;
use Dirthara\I18n\Contract\CurrencyFormatter as CurrencyFormatterContract;

use function is_float;
use function is_finite;

final class CurrencyFormatter implements CurrencyFormatterContract
{
    /**
     * @var array<string, NumberFormatter>
     */
    private static array $formatters = [];

    public function __construct(
        public readonly Locale $locale,
    ) {}

    /**
     * @throws CurrencyFormatterException
     * @throws InvalidCurrencyAmountException
     */
    public function format(
        int|float $amount,
        Currency $currency,
        CurrencyStyle $style = CurrencyStyle::Standard,
    ): string {
        if (is_float($amount) && !is_finite($amount)) {
            throw InvalidCurrencyAmountException::notFinite($this->locale, $currency, $amount);
        }

        $formatter = $this->formatter($style);
        $failure = null;

        try {
            $formatted = $formatter->formatCurrency($amount, $currency->code);
        } catch (IntlException $failure) {
            $formatted = false;
        }

        if ($formatted === false) {
            throw CurrencyFormatterException::formatFailed(
                $this->locale,
                $currency,
                $style,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
                previous: $failure,
            );
        }

        return $formatted;
    }

    /**
     * @throws CurrencyFormatterException
     */
    private function formatter(CurrencyStyle $style): NumberFormatter
    {
        $key = $this->locale->code . '|' . $style->name;
        $formatter = self::$formatters[$key] ?? null;

        if ($formatter !== null) {
            return $formatter;
        }

        try {
            $formatter = new NumberFormatter($this->locale->code, match ($style) {
                CurrencyStyle::Standard => NumberFormatter::CURRENCY,
                CurrencyStyle::Accounting => NumberFormatter::CURRENCY_ACCOUNTING,
                CurrencyStyle::Iso => NumberFormatter::CURRENCY_ISO,
                CurrencyStyle::Name => NumberFormatter::CURRENCY_PLURAL,
                CurrencyStyle::Cash => NumberFormatter::CASH_CURRENCY,
            });

            // @mago-expect analysis:avoid-catching-error
        } catch (IntlException|ValueError $exception) {
            throw CurrencyFormatterException::creationFailed($this->locale, $style, previous: $exception);
        }

        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);
        self::$formatters[$key] = $formatter;

        return $formatter;
    }
}
