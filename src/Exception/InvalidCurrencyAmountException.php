<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Currency;
use InvalidArgumentException;

use function is_nan;
use function sprintf;

final class InvalidCurrencyAmountException extends InvalidArgumentException implements I18nException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    public static function notFinite(Locale $locale, Currency $currency, float $amount): self
    {
        $described = match (true) {
            is_nan($amount) => 'NAN',
            $amount > 0 => 'INF',
            default => '-INF',
        };

        return new self(
            message: sprintf(
                'Unable to format %s as an amount of %s for locale "%s": an amount has to be a finite number.',
                $described,
                $currency->code,
                $locale->code,
            ),
            context: ['locale' => $locale->code, 'currency' => $currency->code, 'amount' => $described],
        );
    }
}
