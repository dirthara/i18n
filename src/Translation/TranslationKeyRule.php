<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

final readonly class TranslationKeyRule
{
    public function allows(string $key): bool
    {
        return $key !== '' && (string) (int) $key !== $key;
    }
}
