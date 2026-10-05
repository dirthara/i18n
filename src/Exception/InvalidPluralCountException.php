<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;
use Dirthara\I18n\Locale;
use InvalidArgumentException;

use function is_nan;
use function sprintf;

final class InvalidPluralCountException extends InvalidArgumentException implements I18nException
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

    public static function notFinite(Locale $locale, float $count): self
    {
        $described = match (true) {
            is_nan($count) => 'NAN',
            $count > 0 => 'INF',
            default => '-INF',
        };

        return new self(
            message: sprintf(
                'The plural category of %s for locale "%s" does not exist: a count has to be a finite number.',
                $described,
                $locale->code,
            ),
            context: ['locale' => $locale->code, 'count' => $described],
        );
    }
}
