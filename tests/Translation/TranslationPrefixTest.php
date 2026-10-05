<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Translation\TranslationPrefix;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

final class TranslationPrefixTest extends TestCase
{
    #[Test]
    #[DataProvider('prefixedKeys')]
    public function it_prefixes_a_key(?string $prefix, string $key, string $expected): void
    {
        self::assertSame($expected, new TranslationPrefix($prefix)->apply($key));
    }

    #[Test]
    #[DataProvider('invalidPrefixes')]
    public function it_rejects_an_invalid_prefix(string $prefix): void
    {
        $this->expectException(InvalidTranslationPrefixException::class);

        new TranslationPrefix($prefix);
    }

    #[Test]
    public function it_reports_the_rejected_prefix(): void
    {
        try {
            new TranslationPrefix("app\n.");
            self::fail('Expected an InvalidTranslationPrefixException.');
        } catch (InvalidTranslationPrefixException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                '"app\\n." is not a valid translation prefix: it must be dot-separated segments that are not empty.',
                $exception->getMessage(),
            );
            self::assertSame(['prefix' => "app\n."], $exception->context);
        }
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function prefixedKeys(): iterable
    {
        yield 'no prefix' => [null, 'validation.required', 'validation.required'];
        yield 'one segment' => ['dirthara', 'validation.required', 'dirthara.validation.required'];
        yield 'two segments' => ['dirthara.validation', 'required', 'dirthara.validation.required'];
        yield 'key with spaces' => ['application', 'Log out', 'application.Log out'];
        yield 'empty key' => ['application', '', 'application.'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPrefixes(): iterable
    {
        yield 'empty' => [''];
        yield 'only a dot' => ['.'];
        yield 'leading dot' => ['.dirthara'];
        yield 'trailing dot' => ['dirthara.'];
        yield 'double dot' => ['dirthara..validation'];
    }
}
