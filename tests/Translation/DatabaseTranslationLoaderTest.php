<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Exception\DatabaseException;
use Dirthara\I18n\Translation\JsonTranslationLoader;
use Dirthara\I18n\Tests\Fixtures\TranslationDatabase;
use Dirthara\I18n\Exception\TranslationLoaderException;
use Dirthara\I18n\Translation\DatabaseTranslationLoader;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function sprintf;
use function json_encode;
use function str_contains;

use const JSON_THROW_ON_ERROR;

final class DatabaseTranslationLoaderTest extends TestCase
{
    private TranslationDatabase $database;

    protected function setUp(): void
    {
        $this->database = new TranslationDatabase();
        $this->database->createTable();
    }

    #[Test]
    public function it_loads_nothing_for_a_locale_without_rows(): void
    {
        $this->database->insert('nl-NL', 'welcome', 'Welkom');

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame('en-GB', $catalogue->locale->code);
        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_loads_the_rows_of_the_requested_locale_only(): void
    {
        $this->database->insert('en-GB', 'welcome', 'Welcome');
        $this->database->insert('nl-NL', 'welcome', 'Welkom');
        $this->database->insert('en-GB', 'validation.required', 'The {attribute} field is required.');
        $this->database->insert('en-GB', 'empty', '');

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertEqualsCanonicalizing(
            [
                'welcome' => 'Welcome',
                'validation.required' => 'The {attribute} field is required.',
                'empty' => '',
            ],
            $catalogue->messages,
        );
        self::assertSame('Welcome', $catalogue->get('welcome'));
    }

    #[Test]
    public function it_matches_the_canonical_locale_code_exactly(): void
    {
        $this->database->insert('nl', 'welcome', 'Language only');
        $this->database->insert('nl-nl', 'welcome', 'Lowercase region');
        $this->database->insert('nl_NL', 'welcome', 'Underscore');
        $this->database->insert('nl-NL', 'welcome', 'Welkom');

        $catalogue = $this->loader()->load(new Locale('nl_nl'));

        self::assertSame(['welcome' => 'Welkom'], $catalogue->messages);
    }

    #[Test]
    public function it_matches_exactly_when_the_column_collation_does_not(): void
    {
        $this->database->createCaseInsensitiveTable('nocase');
        $this->database->insert('nl-nl', 'lowercase', 'Lowercase region', table: 'nocase');
        $this->database->insert('NL-NL', 'uppercase', 'Uppercase language', table: 'nocase');
        $this->database->insert('nl-NL', 'welcome', 'Welkom', table: 'nocase');

        $catalogue = new DatabaseTranslationLoader($this->database->database, 'nocase')->load(new Locale('nl-NL'));

        self::assertSame(['welcome' => 'Welkom'], $catalogue->messages);
    }

    #[Test]
    public function it_does_not_fall_back_to_the_language(): void
    {
        $this->database->insert('nl', 'welcome', 'Welkom');

        self::assertSame([], $this->loader()->load(new Locale('nl-NL'))->messages);
    }

    #[Test]
    public function it_prefixes_every_key(): void
    {
        $this->database->insert('en-GB', 'required', 'Required.');
        $this->database->insert('en-GB', 'between.numeric', 'Between.');

        $catalogue = new DatabaseTranslationLoader($this->database->database, prefix: 'dirthara.validation')->load(
            new Locale('en-GB'),
        );

        self::assertEqualsCanonicalizing(
            [
                'dirthara.validation.required' => 'Required.',
                'dirthara.validation.between.numeric' => 'Between.',
            ],
            $catalogue->messages,
        );
    }

    #[Test]
    public function it_rejects_an_invalid_prefix(): void
    {
        $this->expectException(InvalidTranslationPrefixException::class);

        new DatabaseTranslationLoader($this->database->database, prefix: '.');
    }

    #[Test]
    public function it_reads_a_configured_table_without_an_id_column(): void
    {
        $this->database->createTable('package_translations');
        $this->database->insert('en-GB', 'welcome', 'From the package table', table: 'package_translations');
        $this->database->insert('en-GB', 'welcome', 'From the default table');

        $catalogue = new DatabaseTranslationLoader($this->database->database, 'package_translations')->load(
            new Locale('en-GB'),
        );

        self::assertSame(['welcome' => 'From the package table'], $catalogue->messages);
    }

    #[Test]
    public function it_produces_the_same_catalogue_as_the_json_loader(): void
    {
        $messages = ['Welcome' => 'Welcome', 'Log out' => 'Log out', 'Are you sure?' => 'Are you sure?'];
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('translations/en-GB.json', json_encode($messages, JSON_THROW_ON_ERROR));

        foreach ($messages as $key => $translation) {
            $this->database->insert('en-GB', $key, $translation);
        }

        $locale = new Locale('en-GB');

        self::assertEquals(
            new JsonTranslationLoader($filesystem, 'translations', 'app')->load($locale),
            new DatabaseTranslationLoader($this->database->database, prefix: 'app')->load($locale),
        );
        self::assertEquals(
            new JsonTranslationLoader($filesystem, 'translations')->load($locale),
            $this->loader()->load($locale),
        );
    }

    #[Test]
    public function it_rejects_a_row_whose_key_is_not_a_string(): void
    {
        $this->database->insert('en-GB', null, 'Secret translation');

        $exception = $this->failure(new Locale('en-GB'));

        self::assertSame(
            'A translation row for locale "en-GB" in table "translations" has a "key" that is not a string.',
            $exception->getMessage(),
        );
        self::assertSame(['table' => 'translations', 'locale' => 'en-GB', 'column' => 'key'], $exception->context);
        $this->assertDoesNotReveal('Secret translation', $exception);
    }

    #[Test]
    public function it_rejects_a_row_whose_key_is_an_integer(): void
    {
        $this->database->insert('en-GB', 404, 'Not found');

        self::assertSame('key', $this->failure(new Locale('en-GB'))->context['column']);
    }

    #[Test]
    public function it_rejects_a_row_whose_translation_is_not_a_string(): void
    {
        $this->database->insert('en-GB', 'answer', 42);

        $exception = $this->failure(new Locale('en-GB'));

        self::assertSame(
            ['table' => 'translations', 'locale' => 'en-GB', 'column' => 'translation'],
            $exception->context,
        );
        $this->assertDoesNotReveal('42', $exception);
    }

    #[Test]
    public function it_rejects_a_row_whose_translation_is_null(): void
    {
        $this->database->insert('en-GB', 'missing', null);

        self::assertSame('translation', $this->failure(new Locale('en-GB'))->context['column']);
    }

    #[Test]
    #[DataProvider('invalidKeys')]
    public function it_rejects_an_empty_or_integer_key(string $key): void
    {
        $this->database->insert('en-GB', $key, 'Secret translation');

        $exception = $this->failure(new Locale('en-GB'));

        self::assertSame(
            sprintf(
                'The translation key "%s" for locale "en-GB" in table "translations" is not valid: a key is a string '
                . 'that is not empty and not a decimal integer.',
                $key,
            ),
            $exception->getMessage(),
        );
        self::assertSame(['table' => 'translations', 'locale' => 'en-GB', 'key' => $key], $exception->context);
        $this->assertDoesNotReveal('Secret', $exception);
    }

    #[Test]
    public function it_accepts_an_integer_key_once_it_is_prefixed(): void
    {
        $this->database->insert('en-GB', '404', 'Not found');

        $catalogue = new DatabaseTranslationLoader($this->database->database, prefix: 'errors')->load(
            new Locale('en-GB'),
        );

        self::assertSame(['errors.404' => 'Not found'], $catalogue->messages);
    }

    #[Test]
    public function it_rejects_a_key_defined_twice_for_the_locale(): void
    {
        $this->database->insert('en-GB', 'welcome', 'First secret');
        $this->database->insert('nl-NL', 'welcome', 'Welkom');
        $this->database->insert('en-GB', 'welcome', 'Second secret');

        $exception = $this->failure(new Locale('en-GB'));

        self::assertSame(
            'The translation key "welcome" is defined more than once for locale "en-GB" in table "translations".',
            $exception->getMessage(),
        );
        self::assertSame(['table' => 'translations', 'locale' => 'en-GB', 'key' => 'welcome'], $exception->context);
        $this->assertDoesNotReveal('secret', $exception);
    }

    #[Test]
    public function it_wraps_a_database_failure(): void
    {
        $this->database->insert('en-GB', 'welcome', 'Secret welcome');
        $this->database->drop();

        $exception = $this->failure(new Locale('en-GB'));

        self::assertSame(
            'Unable to load the translations for locale "en-GB" from table "translations".',
            $exception->getMessage(),
        );
        self::assertSame(['table' => 'translations', 'locale' => 'en-GB'], $exception->context);
        self::assertInstanceOf(DatabaseException::class, $exception->getPrevious());
    }

    #[Test]
    public function it_escapes_the_table_name_in_its_messages(): void
    {
        $exception = $this->failure(
            new Locale('en-GB'),
            new DatabaseTranslationLoader($this->database->database, "missing\ntable"),
        );

        self::assertSame(
            'Unable to load the translations for locale "en-GB" from table "missing\\ntable".',
            $exception->getMessage(),
        );
        self::assertSame("missing\ntable", $exception->context['table']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'integer' => ['404'];
        yield 'negative integer' => ['-1'];
    }

    private function loader(): DatabaseTranslationLoader
    {
        return new DatabaseTranslationLoader($this->database->database);
    }

    private function failure(Locale $locale, ?DatabaseTranslationLoader $loader = null): TranslationLoaderException
    {
        try {
            ($loader ?? $this->loader())->load($locale);
        } catch (TranslationLoaderException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);
            self::assertNotInstanceOf(DatabaseException::class, $exception);

            return $exception;
        }

        self::fail('Expected a TranslationLoaderException.');
    }

    private function assertDoesNotReveal(string $value, TranslationLoaderException $exception): void
    {
        self::assertFalse(str_contains($exception->getMessage(), $value));
        self::assertFalse(str_contains(json_encode($exception->context, JSON_THROW_ON_ERROR), $value));
    }
}
