<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use Dirthara\I18n\Locale;
use InvalidArgumentException;

use function sprintf;

final class InvalidListItemException extends InvalidArgumentException implements I18nException
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

    public static function notString(Locale $locale, int|string $position, string $type): self
    {
        return new self(
            message: sprintf(
                'The list formatter for locale "%s" cannot format item %s: it is %s, not a string.',
                $locale->code,
                self::printable((string) $position),
                $type,
            ),
            context: ['formatter' => 'list', 'locale' => $locale->code, 'position' => $position, 'type' => $type],
        );
    }
}
