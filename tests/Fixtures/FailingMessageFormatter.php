<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Fixtures;

use MessageFormatter;

final class FailingMessageFormatter extends MessageFormatter
{
    /**
     * @param array<array-key, mixed> $values
     */
    public function format(array $values): string|false
    {
        return false;
    }
}
