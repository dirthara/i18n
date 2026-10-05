<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use stdClass;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Translation\TranslationCatalogue;
use Dirthara\I18n\Exception\InvalidTranslationCatalogueException;

use function sprintf;

final class TranslationCatalogueTest extends TestCase
{
    #[Test]
    public function it_exposes_its_locale_and_messages(): void
    {
        $locale = new Locale('en-GB');
        $catalogue = new TranslationCatalogue($locale, ['greeting' => 'Hello']);

        self::assertSame($locale, $catalogue->locale);
        self::assertSame(['greeting' => 'Hello'], $catalogue->messages);
    }

    #[Test]
    public function it_finds_a_translation_by_its_exact_key(): void
    {
        $catalogue = new TranslationCatalogue(new Locale('en-GB'), [
            'validation.required' => 'The {attribute} field is required.',
            'empty' => '',
        ]);

        self::assertTrue($catalogue->has('validation.required'));
        self::assertSame('The {attribute} field is required.', $catalogue->get('validation.required'));
        self::assertTrue($catalogue->has('empty'));
        self::assertSame('', $catalogue->get('empty'));
    }

    #[Test]
    public function it_does_not_find_a_key_it_does_not_hold(): void
    {
        $catalogue = new TranslationCatalogue(new Locale('en-GB'), ['validation.required' => 'Required.']);

        self::assertFalse($catalogue->has('validation'));
        self::assertNull($catalogue->get('validation'));
        self::assertFalse($catalogue->has('Validation.required'));
        self::assertNull($catalogue->get('validation.required.numeric'));
    }

    #[Test]
    public function it_holds_no_translations(): void
    {
        $catalogue = new TranslationCatalogue(new Locale('en-GB'), []);

        self::assertSame([], $catalogue->messages);
        self::assertFalse($catalogue->has(''));
        self::assertNull($catalogue->get(''));
    }

    #[Test]
    #[DataProvider('validKeys')]
    public function it_accepts_a_key_php_keeps_as_a_string(string $key): void
    {
        $catalogue = new TranslationCatalogue(new Locale('en-GB'), [$key => 'Translation']);

        self::assertSame([$key => 'Translation'], $catalogue->messages);
        self::assertTrue($catalogue->has($key));
        self::assertSame('Translation', $catalogue->get($key));
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_rejects_an_empty_or_integer_key(int|string $key, string $reported): void
    {
        try {
            new TranslationCatalogue(new Locale('en-GB'), ['valid' => 'Valid', $key => 'Secret translation']);
            self::fail('Expected an InvalidTranslationCatalogueException.');
        } catch (InvalidTranslationCatalogueException $exception) {
            self::assertSame(
                sprintf(
                    'The translation key "%s" for locale "en-GB" is not valid: a key is a string that is not empty and '
                    . 'not a decimal integer.',
                    $reported,
                ),
                $exception->getMessage(),
            );
            self::assertSame(['locale' => 'en-GB', 'key' => $reported], $exception->context);
        }
    }

    #[Test]
    #[DataProvider('nonStringMessages')]
    public function it_rejects_a_message_that_is_not_a_string(mixed $message): void
    {
        try {
            new TranslationCatalogue(new Locale('en-GB'), ['valid' => 'Valid', "bad\nkey" => $message]);
            self::fail('Expected an InvalidTranslationCatalogueException.');
        } catch (InvalidTranslationCatalogueException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertSame(
                'The translation "bad\\nkey" for locale "en-GB" is not a string.',
                $exception->getMessage(),
            );
            self::assertSame(['locale' => 'en-GB', 'key' => "bad\nkey"], $exception->context);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validKeys(): iterable
    {
        yield 'word' => ['Welcome'];
        yield 'dotted' => ['validation.required'];
        yield 'spaces' => ['Log out'];
        yield 'punctuation' => ['Are you sure?'];
        yield 'number after a segment' => ['errors.404'];
        yield 'number before a segment' => ['404.message'];
        yield 'leading zero' => ['007'];
        yield 'plus sign' => ['+1'];
        yield 'negative zero' => ['-0'];
        yield 'decimal fraction' => ['1.5'];
        yield 'exponent' => ['1e3'];
        yield 'surrounding space' => [' 1'];
        yield 'beyond the integer range' => ['99999999999999999999'];
    }

    /**
     * @return iterable<string, array{int|string, string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'decimal integer string' => ['404', '404'];
        yield 'zero' => ['0', '0'];
        yield 'negative integer string' => ['-1', '-1'];
        yield 'integer' => [500, '500'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringMessages(): iterable
    {
        yield 'integer' => [1];
        yield 'float' => [1.5];
        yield 'boolean' => [true];
        yield 'null' => [null];
        yield 'array' => [['nested' => 'value']];
        yield 'object' => [new stdClass()];
    }
}
