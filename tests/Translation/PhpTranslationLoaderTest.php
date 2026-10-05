<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use Error;
use ParseError;
use RuntimeException;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\Test;
use League\Flysystem\DirectoryAttributes;
use Dirthara\I18n\Exception\I18nException;
use League\Flysystem\UnableToListContents;
use Dirthara\I18n\Translation\PhpSourceRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Translation\PhpTranslationLoader;
use League\Flysystem\UnableToCheckDirectoryExistence;
use Dirthara\I18n\Tests\Fixtures\ForeignStreamWrapper;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Dirthara\I18n\Exception\TranslationLoaderException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function fopen;
use function sprintf;
use function array_keys;
use function file_exists;
use function set_error_handler;
use function stream_get_wrappers;
use function restore_error_handler;
use function stream_wrapper_register;

final class PhpTranslationLoaderTest extends TestCase
{
    private const string VALIDATION = <<<'PHP'
        <?php

        return [
            'required' => 'The {attribute} field is required.',
            'between' => [
                'numeric' => 'The {attribute} must be between {min} and {max}.',
            ],
        ];
        PHP;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    #[Test]
    public function it_loads_nothing_when_the_locale_has_no_directory(): void
    {
        $this->filesystem->write('translations/nl-NL/validation.php', self::VALIDATION);

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame('en-GB', $catalogue->locale->code);
        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_rejects_a_source_directory_that_does_not_exist(): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', self::VALIDATION);

        $exception = $this->failure(new PhpTranslationLoader($this->filesystem, '/translatons/'), new Locale('fr-FR'));

        self::assertSame('The translation source "translatons" does not exist.', $exception->getMessage());
        self::assertSame(['path' => 'translatons'], $exception->context);
    }

    #[Test]
    public function it_reports_a_source_directory_it_cannot_check(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem
            ->method('directoryExists')
            ->willThrowException(UnableToCheckDirectoryExistence::forLocation('translations'));

        $exception = $this->failure(new PhpTranslationLoader($filesystem, 'translations'), new Locale('en-GB'));

        self::assertSame(['path' => 'translations/en-GB'], $exception->context);
        self::assertInstanceOf(UnableToCheckDirectoryExistence::class, $exception->getPrevious());
    }

    #[Test]
    public function it_loads_from_a_root_the_adapter_does_not_report_as_a_directory(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem
            ->method('directoryExists')
            ->willReturnCallback(static fn(string $location): bool => $location === 'en-GB');
        $filesystem
            ->method('listContents')
            ->willReturn(new DirectoryListing([new FileAttributes('en-GB/messages.php')]));
        $filesystem->method('read')->willReturn($this->source("['hello' => 'Hello']"));

        $catalogue = new PhpTranslationLoader($filesystem, '/')->load(new Locale('en-GB'));

        self::assertSame(['messages.hello' => 'Hello'], $catalogue->messages);
    }

    #[Test]
    public function it_loads_a_file_with_its_name_as_the_first_key_segment(): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', self::VALIDATION);

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame(
            [
                'validation.required' => 'The {attribute} field is required.',
                'validation.between.numeric' => 'The {attribute} must be between {min} and {max}.',
            ],
            $catalogue->messages,
        );
    }

    #[Test]
    public function it_loads_several_files_in_the_order_of_their_paths(): void
    {
        $this->filesystem->write('translations/en-GB/navigation.php', $this->source("['home' => 'Home']"));
        $this->filesystem->write('translations/en-GB/auth.php', $this->source("['failed' => 'Failed.']"));
        $this->filesystem->write('translations/en-GB/messages.php', $this->source("['welcome' => 'Welcome']"));

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame(['auth.failed', 'messages.welcome', 'navigation.home'], array_keys($catalogue->messages));
        self::assertSame('Home', $catalogue->get('navigation.home'));
    }

    #[Test]
    public function it_sorts_the_files_whatever_order_the_filesystem_lists_them_in(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem->method('directoryExists')->willReturn(true);
        $filesystem
            ->method('listContents')
            ->willReturn(new DirectoryListing([
                new FileAttributes('translations/en-GB/b.php'),
                new FileAttributes('translations/en-GB/a.php'),
            ]));
        $filesystem->method('read')->willReturn($this->source("['key' => 'Value']"));

        $catalogue = new PhpTranslationLoader($filesystem, 'translations')->load(new Locale('en-GB'));

        self::assertSame(['a.key', 'b.key'], array_keys($catalogue->messages));
    }

    #[Test]
    public function it_flattens_nested_groups_into_dot_separated_keys(): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', $this->source(<<<'PHP'
            [
                'between' => [
                    'numeric' => 'Numeric',
                    'string' => ['short' => 'Short', 'long' => 'Long'],
                ],
                'empty' => [],
                'list' => ['First', 'Second'],
            ]
            PHP));

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame(
            [
                'validation.between.numeric' => 'Numeric',
                'validation.between.string.short' => 'Short',
                'validation.between.string.long' => 'Long',
                'validation.list.0' => 'First',
                'validation.list.1' => 'Second',
            ],
            $catalogue->messages,
        );
    }

    #[Test]
    #[DataProvider('prefixes')]
    public function it_prefixes_every_key(string $prefix, string $expected): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', self::VALIDATION);

        $catalogue = $this->loader(prefix: $prefix)->load(new Locale('en-GB'));

        self::assertSame([$expected . '.required', $expected . '.between.numeric'], array_keys($catalogue->messages));
    }

    #[Test]
    public function it_rejects_an_invalid_prefix(): void
    {
        $this->expectException(InvalidTranslationPrefixException::class);

        $this->loader(prefix: 'dirthara.');
    }

    #[Test]
    public function it_reads_the_directory_named_after_the_canonical_locale_code(): void
    {
        $this->filesystem->write('translations/zh-Hant-TW/messages.php', $this->source("['hello' => '你好']"));
        $this->filesystem->write('translations/zh-hant-tw/messages.php', $this->source("['hello' => 'Wrong']"));

        $catalogue = $this->loader()->load(new Locale('ZH_hant_tw'));

        self::assertSame(['messages.hello' => '你好'], $catalogue->messages);
    }

    #[Test]
    public function it_loads_from_the_root_of_the_filesystem(): void
    {
        $this->filesystem->write('en-GB/messages.php', $this->source("['hello' => 'Hello']"));

        $catalogue = new PhpTranslationLoader($this->filesystem, '')->load(new Locale('en-GB'));

        self::assertSame(['messages.hello' => 'Hello'], $catalogue->messages);
    }

    #[Test]
    public function it_ignores_everything_but_php_files_directly_in_the_locale_directory(): void
    {
        $this->filesystem->write('translations/en-GB/messages.php', $this->source("['hello' => 'Hello']"));
        $this->filesystem->write('translations/en-GB/messages.json', '{"hello": "Wrong"}');
        $this->filesystem->write('translations/en-GB/README.md', '# Translations');
        $this->filesystem->write('translations/en-GB/messages.php.bak', '<?php return 1;');
        $this->filesystem->write('translations/en-GB/nested/deeper.php', $this->source("['hello' => 'Wrong']"));
        $this->filesystem->createDirectory('translations/en-GB/directory.php');
        $this->filesystem->write('translations/en-GB.php', $this->source("['hello' => 'Wrong']"));

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame(['messages.hello' => 'Hello'], $catalogue->messages);
    }

    #[Test]
    public function it_ignores_a_directory_listed_with_a_php_name(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem->method('directoryExists')->willReturn(true);
        $filesystem
            ->method('listContents')
            ->willReturn(new DirectoryListing([
                new DirectoryAttributes('translations/en-GB/directory.php'),
            ]));

        $catalogue = new PhpTranslationLoader($filesystem, 'translations')->load(new Locale('en-GB'));

        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_runs_the_source_without_a_local_file(): void
    {
        $this->filesystem->write(
            'translations/en-GB/meta.php',
            $this->source("['file' => __FILE__, 'dir' => __DIR__]"),
        );

        $catalogue = $this->loader()->load(new Locale('en-GB'));

        self::assertSame('dirthara-i18n://translations/en-GB/meta.php', $catalogue->get('meta.file'));
        self::assertSame('dirthara-i18n://translations/en-GB', $catalogue->get('meta.dir'));
    }

    #[Test]
    public function it_removes_the_source_from_the_stream_wrapper_after_loading(): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', self::VALIDATION);

        $this->loader()->load(new Locale('en-GB'));

        self::assertContains(PhpSourceRunner::SCHEME, stream_get_wrappers());
        self::assertFalse(file_exists('dirthara-i18n://translations/en-GB/validation.php'));
    }

    #[Test]
    public function it_serves_a_source_only_while_it_loads(): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', self::VALIDATION);
        $this->loader()->load(new Locale('en-GB'));

        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $stream = fopen('dirthara-i18n://translations/en-GB/validation.php', mode: 'r');
        } finally {
            restore_error_handler();
        }

        self::assertFalse($stream);
        self::assertNotSame([], $warnings);
    }

    #[Test]
    public function it_removes_the_source_from_the_stream_wrapper_after_a_failure(): void
    {
        $this->filesystem->write('translations/en-GB/broken.php', "<?php\n\nthrow new RuntimeException('Broken');");

        $this->failure($this->loader(), new Locale('en-GB'));

        self::assertFalse(file_exists('dirthara-i18n://translations/en-GB/broken.php'));
    }

    #[Test]
    #[DataProvider('invalidReturns')]
    public function it_rejects_a_file_that_does_not_return_an_array(string $return): void
    {
        $this->filesystem->write('translations/en-GB/messages.php', $this->source($return));

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(
            'The translation file "translations/en-GB/messages.php" must return an array.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => 'translations/en-GB/messages.php'], $exception->context);
    }

    #[Test]
    public function it_rejects_a_file_that_returns_nothing(): void
    {
        $this->filesystem->write('translations/en-GB/messages.php', "<?php\n\n\$messages = ['hello' => 'Hello'];\n");

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(['path' => 'translations/en-GB/messages.php'], $exception->context);
    }

    #[Test]
    #[DataProvider('invalidTranslations')]
    public function it_rejects_a_translation_that_is_not_a_string(string $translation): void
    {
        $this->filesystem->write(
            'translations/en-GB/messages.php',
            $this->source(sprintf("['valid' => 'Valid', 'group' => ['secret' => %s]]", $translation)),
        );

        $exception = $this->failure($this->loader(prefix: 'app'), new Locale('en-GB'));

        self::assertSame(
            'The translation "messages.group.secret" in "translations/en-GB/messages.php" is not a string or a group of '
            . 'translations.',
            $exception->getMessage(),
        );
        self::assertSame(
            ['path' => 'translations/en-GB/messages.php', 'key' => 'messages.group.secret'],
            $exception->context,
        );
    }

    #[Test]
    public function it_reports_a_parse_error_in_the_source(): void
    {
        $this->filesystem->write('translations/en-GB/messages.php', "<?php\n\nreturn ['hello' => ;\n");

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(
            'The translation file "translations/en-GB/messages.php" failed while loading.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => 'translations/en-GB/messages.php'], $exception->context);
        self::assertInstanceOf(ParseError::class, $exception->getPrevious());
        self::assertSame('dirthara-i18n://translations/en-GB/messages.php', $exception->getPrevious()?->getFile());
    }

    #[Test]
    public function it_reports_an_exception_thrown_by_the_source(): void
    {
        $this->filesystem->write('translations/en-GB/messages.php', "<?php\n\nthrow new RuntimeException('Broken');");

        $previous = $this->failure($this->loader(), new Locale('en-GB'))->getPrevious();

        self::assertInstanceOf(RuntimeException::class, $previous);
        self::assertSame('Broken', $previous->getMessage());
    }

    #[Test]
    public function it_reports_an_error_raised_by_the_source(): void
    {
        $this->filesystem->write('translations/en-GB/messages.php', "<?php\n\nreturn undefined_function();");

        self::assertInstanceOf(Error::class, $this->failure($this->loader(), new Locale('en-GB'))->getPrevious());
    }

    #[Test]
    public function it_rejects_a_key_a_nested_group_already_produced(): void
    {
        $this->filesystem->write(
            'translations/en-GB/validation.php',
            $this->source("['between' => ['numeric' => 'Nested'], 'between.numeric' => 'Flat']"),
        );

        $exception = $this->failure($this->loader(prefix: 'app'), new Locale('en-GB'));

        self::assertSame(
            'The translation key "app.validation.between.numeric" from "translations/en-GB/validation.php" is already '
            . 'defined.',
            $exception->getMessage(),
        );
        self::assertSame(
            ['path' => 'translations/en-GB/validation.php', 'key' => 'app.validation.between.numeric'],
            $exception->context,
        );
    }

    #[Test]
    public function it_rejects_a_key_another_file_already_produced(): void
    {
        $this->filesystem->write('translations/en-GB/validation.php', $this->source("['between.numeric' => 'One']"));
        $this->filesystem->write('translations/en-GB/validation.between.php', $this->source("['numeric' => 'Two']"));

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(
            ['path' => 'translations/en-GB/validation.php', 'key' => 'validation.between.numeric'],
            $exception->context,
        );
    }

    #[Test]
    public function it_reports_a_directory_it_cannot_list(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem->method('directoryExists')->willReturn(true);
        $filesystem
            ->method('listContents')
            ->willThrowException(UnableToListContents::atLocation(
                'translations/en-GB',
                deep: false,
                previous: new RuntimeException('Offline'),
            ));

        $exception = $this->failure(new PhpTranslationLoader($filesystem, 'translations'), new Locale('en-GB'));

        self::assertSame('Unable to read the translation source "translations/en-GB".', $exception->getMessage());
        self::assertSame(['path' => 'translations/en-GB'], $exception->context);
        self::assertInstanceOf(UnableToListContents::class, $exception->getPrevious());
    }

    #[Test]
    public function it_reports_a_file_it_cannot_read(): void
    {
        $filesystem = $this->createStub(FilesystemReader::class);
        $filesystem->method('directoryExists')->willReturn(true);
        $filesystem
            ->method('listContents')
            ->willReturn(new DirectoryListing([
                new FileAttributes('translations/en-GB/messages.php'),
            ]));
        $filesystem
            ->method('read')
            ->willThrowException(UnableToReadFile::fromLocation('translations/en-GB/messages.php'));

        $exception = $this->failure(new PhpTranslationLoader($filesystem, 'translations'), new Locale('en-GB'));

        self::assertSame(['path' => 'translations/en-GB/messages.php'], $exception->context);
        self::assertInstanceOf(UnableToReadFile::class, $exception->getPrevious());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function it_registers_its_stream_wrapper_once(): void
    {
        self::assertNotContains(PhpSourceRunner::SCHEME, stream_get_wrappers());

        $this->filesystem->write('translations/en-GB/messages.php', $this->source("['hello' => 'Hello']"));
        $loader = $this->loader();

        self::assertSame('Hello', $loader->load(new Locale('en-GB'))->get('messages.hello'));
        self::assertContains(PhpSourceRunner::SCHEME, stream_get_wrappers());
        self::assertSame('Hello', $loader->load(new Locale('en-GB'))->get('messages.hello'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function it_refuses_a_stream_wrapper_another_component_registered_for_its_scheme(): void
    {
        self::assertTrue(stream_wrapper_register(PhpSourceRunner::SCHEME, ForeignStreamWrapper::class));

        $this->filesystem->write('translations/en-GB/messages.php', $this->source("['hello' => 'Hello']"));

        $exception = $this->failure($this->loader(), new Locale('en-GB'));

        self::assertSame(
            'Unable to load PHP translations: another stream wrapper is already registered for the "dirthara-i18n://" '
            . 'scheme.',
            $exception->getMessage(),
        );
        self::assertSame(['scheme' => 'dirthara-i18n'], $exception->context);
        self::assertSame([], ForeignStreamWrapper::$opened);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prefixes(): iterable
    {
        yield 'one segment' => ['dirthara', 'dirthara.validation'];
        yield 'two segments' => ['dirthara.package', 'dirthara.package.validation'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReturns(): iterable
    {
        yield 'string' => ["'Hello'"];
        yield 'integer' => ['1'];
        yield 'null' => ['null'];
        yield 'object' => ['new stdClass()'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTranslations(): iterable
    {
        yield 'integer' => ['1'];
        yield 'float' => ['1.5'];
        yield 'boolean' => ['true'];
        yield 'null' => ['null'];
        yield 'object' => ['new stdClass()'];
        yield 'closure' => ['static fn(): string => "Hello"'];
    }

    private function loader(?string $prefix = null): PhpTranslationLoader
    {
        return new PhpTranslationLoader($this->filesystem, 'translations', $prefix);
    }

    private function source(string $array): string
    {
        return "<?php\n\nreturn " . $array . ";\n";
    }

    private function failure(PhpTranslationLoader $loader, Locale $locale): TranslationLoaderException
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
