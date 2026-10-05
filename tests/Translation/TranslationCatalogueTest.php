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
    public function it_finds_a_key_php_stores_as_an_integer(): void
    {
        $catalogue = new TranslationCatalogue(new Locale('en-GB'), ['404' => 'Not found']);

        self::assertTrue($catalogue->has('404'));
        self::assertSame('Not found', $catalogue->get('404'));
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
