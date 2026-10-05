<?php

declare(strict_types=1);

namespace Dirthara\I18n\Enum;

enum PluralCategory: string
{
    case Zero = 'zero';
    case One = 'one';
    case Two = 'two';
    case Few = 'few';
    case Many = 'many';
    case Other = 'other';
}
