<?php

declare(strict_types=1);

namespace Dirthara\I18n;

use Dirthara\I18n\Exception\InvalidCurrencyException;

use function preg_match;
use function strtoupper;

final readonly class Currency
{
    private const string PATTERN = '/^[a-z]{3}\z/i';

    public string $code;

    /**
     * @throws InvalidCurrencyException
     */
    public function __construct(string $code)
    {
        if (preg_match(self::PATTERN, $code) !== 1) {
            throw InvalidCurrencyException::forCurrency($code);
        }

        $this->code = strtoupper($code);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
