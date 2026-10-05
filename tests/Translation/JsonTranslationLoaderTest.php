<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use JsonException;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use League\Flysystem\UnableToCheckFileExistence;
use Dirthara\I18n\Translation\JsonTranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function sprintf;

final class JsonTranslationLoaderTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    #[Test]
    public function it_loads_nothing_when_the_locale_has_no_file(): void
    {
        $this->filesystem->write('translations/nl-NL.json', '{"Welcome": "Welkom"}');

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame('en-GB', $catalogue->locale->code);
        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_loads_the_keys_of_an_object_as_they_are(): void
    {
        $this->filesystem->write('translations/en-GB.json', <<<'JSON'
            {
                "Welcome": "Welcome",
                "Log out": "Log out",
                "validation.required": "The {attribute} field is required.",
                "": "Empty key",
                "Unicode": "Café"
            }
            JSON);

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame(
            [
                'Welcome' => 'Welcome',
                'Log out' => 'Log out',
                'validation.required' => 'The {attribute} field is required.',
                '' => 'Empty key',
                'Unicode' => 'Café',
            ],
            $catalogue->messages,
        );
    }

    #[Test]
    public function it_loads_an_empty_object(): void
    {
        $this->filesystem->write('translations/en-GB.json', '{}');

        self::assertSame([], $this->loader()->load(new Locale('en-GB'))->messages);
    }

    #[Test]
    public function it_loads_a_key_php_stores_as_an_integer(): void
    {
        $this->filesystem->write('translations/en-GB.json', '{"404": "Not found"}');

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame('Not found', $catalogue->get('404'));
    }

    #[Test]
    public function it_prefixes_every_key(): void
    {
        $this->filesystem->write('translations/en-GB.json', '{"Welcome": "Welcome", "Log out": "Log out", "404": "?"}');

        $catalogue = $this->loader(prefix: 'application')->load(new Locale('en-GB'));

        self::assertSame(
            [
                'application.Welcome' => 'Welcome',
                'application.Log out' => 'Log out',
                'application.404' => '?',
            ],
            $catalogue->messages,
        );
    }

    #[Test]
    public function it_rejects_an_invalid_prefix(): void
    {
        $this->expectException(InvalidTranslationPrefixException::class);

        $this->loader(prefix: '');
    }

    #[Test]
    public function it_reads_the_file_named_after_the_canonical_locale_code(): void
    {
        $this->filesystem->write('translations/en-GB.json', '{"Welcome": "Welcome"}');
        $this->filesystem->write('translations/en-gb.json', '{"Welcome": "Wrong"}');

        self::assertSame('Welcome', $this->loader()->load(new Locale('en_gb'))->get('Welcome'));
    }

    #[Test]
    public function it_loads_from_the_root_of_the_filesystem(): void
    {
        $this->filesystem->write('en-GB.json', '{"Welcome": "Welcome"}');

        $catalogue = new JsonTranslationLoader($this->filesystem, '')->load(new Locale('en-GB'));

        self::assertSame(['Welcome' => 'Welcome'], $catalogue->messages);
    }

    #[Test]
    #[DataProvider('malformedDocuments')]
    public function it_rejects_malformed_json(string $json): void
    {
        $this->filesystem->write('translations/en-GB.json', $json);

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(
            'The translation file "translations/en-GB.json" does not contain valid JSON.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => 'translations/en-GB.json'], $exception->context);
        self::assertInstanceOf(JsonException::class, $exception->getPrevious());
    }

    #[Test]
    #[DataProvider('nonObjectDocuments')]
    public function it_rejects_a_document_that_is_not_an_object(string $json): void
    {
        $this->filesystem->write('translations/en-GB.json', $json);

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(
            'The translation file "translations/en-GB.json" must contain a JSON object.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => 'translations/en-GB.json'], $exception->context);
    }

    #[Test]
    #[DataProvider('nonStringTranslations')]
    public function it_rejects_a_translation_that_is_not_a_string(string $translation): void
    {
        $this->filesystem->write('translations/en-GB.json', sprintf('{"Valid": "Valid", "Secret": %s}', $translation));

        $exception = $this->failure($this->loader(prefix: 'application'), new Locale('en-GB'));

        self::assertSame(
            'The translation "Secret" in "translations/en-GB.json" is not a string or a group of translations.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => 'translations/en-GB.json', 'key' => 'Secret'], $exception->context);
    }

    #[Test]
    public function it_reports_a_file_it_cannot_check(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem
            ->method('fileExists')
            ->willThrowException(UnableToCheckFileExistence::forLocation('translations/en-GB.json'));

        $exception = $this->failure(new JsonTranslationLoader($filesystem, 'translations'), new Locale('en-GB'));

        self::assertSame('Unable to read the translation source "translations/en-GB.json".', $exception->getMessage());
        self::assertSame(['path' => 'translations/en-GB.json'], $exception->context);
        self::assertInstanceOf(UnableToCheckFileExistence::class, $exception->getPrevious());
    }

    #[Test]
    public function it_reports_a_file_it_cannot_read(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('read')->willThrowException(UnableToReadFile::fromLocation('translations/en-GB.json'));

        $exception = $this->failure(new JsonTranslationLoader($filesystem, 'translations'), new Locale('en-GB'));

        self::assertSame(['path' => 'translations/en-GB.json'], $exception->context);
        self::assertInstanceOf(UnableToReadFile::class, $exception->getPrevious());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDocuments(): iterable
    {
        yield 'empty' => [''];
        yield 'truncated' => ['{"Welcome": "Welcome"'];
        yield 'trailing comma' => ['{"Welcome": "Welcome",}'];
        yield 'single quotes' => ["{'Welcome': 'Welcome'}"];
        yield 'comment' => ['{"Welcome": "Welcome" /* comment */}'];
        yield 'invalid UTF-8' => ["{\"Welcome\": \"\xC3\x28\"}"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonObjectDocuments(): iterable
    {
        yield 'array' => ['["Welcome"]'];
        yield 'empty array' => ['[]'];
        yield 'string' => ['"Welcome"'];
        yield 'number' => ['1'];
        yield 'boolean' => ['true'];
        yield 'null' => ['null'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonStringTranslations(): iterable
    {
        yield 'object' => ['{"Nested": "Value"}'];
        yield 'empty object' => ['{}'];
        yield 'array' => ['["Value"]'];
        yield 'null' => ['null'];
        yield 'boolean' => ['false'];
        yield 'integer' => ['1'];
        yield 'float' => ['1.5'];
    }

    private function loader(?string $prefix = null): JsonTranslationLoader
    {
        return new JsonTranslationLoader($this->filesystem, 'translations', $prefix);
    }

    private function failure(JsonTranslationLoader $loader, Locale $locale): TranslationLoaderException
    {
        try {
            $loader->load($locale);
        } catch (TranslationLoaderException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);

            return $exception;
        }

        self::fail('Expected a TranslationLoaderException.');
    }
}
