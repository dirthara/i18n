<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidCurrencyException extends InvalidArgumentException implements I18nException
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

    public static function forCurrency(string $currency): self
    {
        return new self(message: sprintf('%s is not a valid currency.', self::printable($currency)), context: [
            'currency' => $currency,
        ]);
    }
}
