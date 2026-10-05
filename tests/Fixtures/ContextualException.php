<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use RuntimeException;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements I18nException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
