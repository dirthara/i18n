<?php

declare(strict_types=1);

namespace Dirthara\I18n\Exception;

use Throwable;

interface I18nException extends Throwable
{
    /**
     * @var array<string, mixed>
     */
    public array $context { get; }

    /**
     * @param array<string, mixed> $context
     */
    public function addContext(array $context): static;
}
