<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use Dirthara\I18n\Locale;
use InvalidArgumentException;

use function sprintf;

final class InvalidDateTimeStyleException extends InvalidArgumentException implements I18nException
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

    public static function nothingToFormat(Locale $locale): self
    {
        return new self(
            message: sprintf(
                'The date-time formatter for locale "%s" has nothing to format: the date style and the time style are '
                . 'both None.',
                $locale->code,
            ),
            context: ['formatter' => 'date-time', 'locale' => $locale->code],
        );
    }
}
