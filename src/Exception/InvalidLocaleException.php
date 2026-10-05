<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use InvalidArgumentException;

use function sprintf;

final class InvalidLocaleException extends InvalidArgumentException implements I18nException
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

    public static function forLocale(string $locale, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('%s is not a valid locale.', self::printable($locale)),
            previous: $previous,
            context: [
                'locale' => $locale,
            ],
        );
    }
}
