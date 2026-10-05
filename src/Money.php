<?php

declare(strict_types=1);

namespace Dirthara\I18n;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {}

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency->equals($other->currency);
    }
}
