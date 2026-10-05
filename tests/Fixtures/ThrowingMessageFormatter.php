<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use IntlException;
use MessageFormatter;

final class ThrowingMessageFormatter extends MessageFormatter
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function format(array $values): string|false
    {
        throw new IntlException('Message formatting failed.');
    }
}
