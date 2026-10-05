<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use ParseError;
use ErrorException;
use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\I18n\Translation\PhpTranslationCache;
use Dirthara\I18n\Translation\TranslationCatalogue;
use Dirthara\I18n\Tests\Fixtures\TemporaryDirectory;
use Dirthara\I18n\Exception\TranslationCacheException;

use function hash;
use function chmod;
use function mkdir;
use function touch;
use function is_dir;
use function is_file;
use function str_repeat;
use function file_get_contents;
use function file_put_contents;

final class PhpTranslationCacheTest extends TestCase
{
    private TemporaryDirectory $directory;

    private PhpTranslationCache $cache;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->cache = new PhpTranslationCache($this->directory->path);
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    #[Test]
    public function it_misses_an_entry_it_does_not_have(): void
    {
        self::assertNull($this->cache->get('application', new Locale('en-GB')));
    }

    #[Test]
    public function it_returns_the_catalogue_it_stored(): void
    {
        $locale = new Locale('en-GB');
        $this->cache->put('application', new TranslationCatalogue($locale, [
            'validation.required' => 'The {attribute} field is required.',
            'quotes' => "It's \"quoted\" \\ and\nmultiline ?> <?php",
            '404' => 'Not found',
        ]));

        $catalogue = $this->cache->get('application', new Locale('en_gb'));

        self::assertNotNull($catalogue);
        self::assertSame('en-GB', $catalogue->locale->code);
        self::assertSame('The {attribute} field is required.', $catalogue->get('validation.required'));
        self::assertSame("It's \"quoted\" \\ and\nmultiline ?> <?php", $catalogue->get('quotes'));
        self::assertSame('Not found', $catalogue->get('404'));
        self::assertCount(3, $catalogue->messages);
    }

    #[Test]
    public function it_writes_a_php_file_that_returns_only_the_sorted_messages(): void
    {
        $this->cache->put('application', new TranslationCatalogue(new Locale('en-GB'), [
            'validation.required' => 'Required.',
            'auth.failed' => 'Failed.',
        ]));

        $file = $this->file('application', 'en-GB');

        self::assertSame(<<<'PHP'
            <?php

            declare(strict_types=1);

            return array (
              'auth.failed' => 'Failed.',
              'validation.required' => 'Required.',
            );

            PHP, file_get_contents($file));
        self::assertSame(['auth.failed' => 'Failed.', 'validation.required' => 'Required.'], require $file);
    }

    #[Test]
    public function it_writes_the_same_file_for_the_same_messages(): void
    {
        $locale = new Locale('en-GB');

        $this->cache->put('first', new TranslationCatalogue($locale, [
            'b' => 'B',
            'a' => 'A',
            '10' => 'Ten',
            '9' => 'Nine',
        ]));
        $this->cache->put('second', new TranslationCatalogue($locale, [
            '9' => 'Nine',
            'a' => 'A',
            '10' => 'Ten',
            'b' => 'B',
        ]));

        self::assertSame(
            file_get_contents($this->file('first', 'en-GB')),
            file_get_contents($this->file('second', 'en-GB')),
        );
    }

    #[Test]
    public function it_caches_an_empty_catalogue(): void
    {
        $this->cache->put('application', new TranslationCatalogue(new Locale('en-GB'), []));

        $catalogue = $this->cache->get('application', new Locale('en-GB'));

        self::assertNotNull($catalogue);
        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_keeps_each_locale_in_its_own_entry(): void
    {
        $this->cache->put('application', new TranslationCatalogue(new Locale('en-GB'), ['welcome' => 'Welcome']));
        $this->cache->put('application', new TranslationCatalogue(new Locale('nl-NL'), ['welcome' => 'Welkom']));

        self::assertSame('Welcome', $this->cache->get('application', new Locale('en-GB'))?->get('welcome'));
        self::assertSame('Welkom', $this->cache->get('application', new Locale('nl-NL'))?->get('welcome'));
        self::assertNull($this->cache->get('application', new Locale('de-DE')));
    }

    #[Test]
    public function it_keeps_each_key_in_its_own_entry(): void
    {
        $locale = new Locale('en-GB');
        $this->cache->put('application', new TranslationCatalogue($locale, ['welcome' => 'Application']));
        $this->cache->put('validation', new TranslationCatalogue($locale, ['welcome' => 'Validation']));

        self::assertSame('Application', $this->cache->get('application', $locale)?->get('welcome'));
        self::assertSame('Validation', $this->cache->get('validation', $locale)?->get('welcome'));
        self::assertNull($this->cache->get('Application', $locale));
    }

    #[Test]
    #[DataProvider('unsafeKeys')]
    public function it_keeps_an_unsafe_key_inside_its_directory(string $key): void
    {
        $nested = $this->directory->path . '/cache/translations';
        $cache = new PhpTranslationCache($nested);
        $locale = new Locale('en-GB');

        $cache->put($key, new TranslationCatalogue($locale, ['welcome' => 'Welcome']));

        self::assertSame(['cache'], $this->directory->files());
        self::assertSame(['translations'], $this->directory->files($this->directory->path . '/cache'));
        self::assertSame([hash('sha256', $key) . '.en-GB.php'], $this->directory->files($nested));
        self::assertSame('Welcome', $cache->get($key, $locale)?->get('welcome'));
    }

    #[Test]
    public function it_replaces_an_entry_completely(): void
    {
        $locale = new Locale('en-GB');
        $this->cache->put('application', new TranslationCatalogue($locale, ['old' => 'Old', 'kept' => 'Before']));
        $this->cache->put('application', new TranslationCatalogue($locale, ['kept' => 'After']));

        self::assertSame(['kept' => 'After'], $this->cache->get('application', $locale)?->messages);
        self::assertSame([$this->fileName('application', 'en-GB')], $this->directory->files());
    }

    #[Test]
    public function it_forgets_one_entry(): void
    {
        $english = new Locale('en-GB');
        $dutch = new Locale('nl-NL');
        $this->cache->put('application', new TranslationCatalogue($english, ['welcome' => 'Welcome']));
        $this->cache->put('application', new TranslationCatalogue($dutch, ['welcome' => 'Welkom']));
        $this->cache->put('validation', new TranslationCatalogue($english, ['welcome' => 'Validation']));

        $this->cache->forget('application', $english);

        self::assertNull($this->cache->get('application', $english));
        self::assertNotNull($this->cache->get('application', $dutch));
        self::assertNotNull($this->cache->get('validation', $english));
    }

    #[Test]
    public function it_forgets_an_entry_it_does_not_have(): void
    {
        $this->cache->forget('application', new Locale('en-GB'));

        self::assertSame([], $this->directory->files());
    }

    #[Test]
    public function it_creates_its_directory_when_it_first_writes(): void
    {
        $path = $this->directory->path . '/var/cache/translations';
        $cache = new PhpTranslationCache($path);

        self::assertNull($cache->get('application', new Locale('en-GB')));
        self::assertFalse(is_dir($path));

        $cache->put('application', new TranslationCatalogue(new Locale('en-GB'), []));

        self::assertTrue(is_dir($path));
    }

    #[Test]
    public function it_refuses_an_empty_directory_path(): void
    {
        $exception = $this->failure(static fn(): PhpTranslationCache => new PhpTranslationCache(''));

        self::assertSame(['path' => ''], $exception->context);
    }

    #[Test]
    public function it_reports_a_directory_it_cannot_create(): void
    {
        $file = $this->directory->path . '/file';
        touch($file);
        $cache = new PhpTranslationCache($file . '/translations');

        $exception = $this->failure(static fn() => $cache->put(
            'application',
            new TranslationCatalogue(new Locale('en-GB'), []),
        ));

        self::assertSame(
            'The translation cache directory "' . $file . '/translations" does not exist and cannot be created.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => $file . '/translations'], $exception->context);
        self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
    }

    #[Test]
    public function it_reports_an_entry_it_cannot_write_and_leaves_no_file_behind(): void
    {
        chmod($this->directory->path, permissions: 0o555);

        $exception = $this->failure(fn() => $this->cache->put("application\n", new TranslationCatalogue(
            new Locale('en-GB'),
            ['welcome' => 'Secret welcome'],
        )));

        chmod($this->directory->path, permissions: 0o755);

        $file = $this->file("application\n", 'en-GB');
        self::assertSame(
            'Unable to write the translation cache entry "application\\n" for locale "en-GB" to "' . $file . '".',
            $exception->getMessage(),
        );
        self::assertSame(['path' => $file, 'key' => "application\n", 'locale' => 'en-GB'], $exception->context);
        self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
        self::assertSame([], $this->directory->files());
    }

    #[Test]
    public function it_reports_an_entry_it_cannot_move_into_place_and_removes_the_temporary_file(): void
    {
        $file = $this->file('application', 'en-GB');
        mkdir($file);
        touch($file . '/occupied');

        $exception = $this->failure(fn() => $this->cache->put('application', new TranslationCatalogue(
            new Locale('en-GB'),
            ['welcome' => 'Welcome'],
        )));

        self::assertSame(
            'Unable to move the translation cache entry "application" for locale "en-GB" into place at "'
            . $file
            . '".',
            $exception->getMessage(),
        );
        self::assertSame(['path' => $file, 'key' => 'application', 'locale' => 'en-GB'], $exception->context);
        self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
        self::assertSame([$this->fileName('application', 'en-GB')], $this->directory->files());
        self::assertSame(['occupied'], $this->directory->files($file));
    }

    #[Test]
    public function it_leaves_no_temporary_file_after_writing(): void
    {
        $locale = new Locale('en-GB');
        $this->cache->put('application', new TranslationCatalogue($locale, ['welcome' => 'Welcome']));
        $this->cache->put('application', new TranslationCatalogue($locale, ['welcome' => 'Welcome back']));
        $this->cache->put('validation', new TranslationCatalogue($locale, []));

        self::assertEqualsCanonicalizing(
            [$this->fileName('application', 'en-GB'), $this->fileName('validation', 'en-GB')],
            $this->directory->files(),
        );
    }

    #[Test]
    #[DataProvider('malformedEntries')]
    public function it_rejects_an_entry_that_does_not_return_an_array_of_strings(string $contents): void
    {
        $file = $this->file('application', 'en-GB');
        file_put_contents($file, $contents);

        $exception = $this->failure(fn() => $this->cache->get('application', new Locale('en-GB')));

        self::assertSame(
            'The translation cache entry "application" for locale "en-GB" at "'
            . $file
            . '" does not return an array of '
            . 'strings.',
            $exception->getMessage(),
        );
        self::assertSame(['path' => $file, 'key' => 'application', 'locale' => 'en-GB'], $exception->context);
        self::assertTrue(is_file($file));
    }

    #[Test]
    public function it_reports_an_entry_that_does_not_parse(): void
    {
        $file = $this->file('application', 'en-GB');
        file_put_contents($file, data: "<?php\n\nreturn ['welcome' => ;\n");

        $exception = $this->failure(fn() => $this->cache->get('application', new Locale('en-GB')));

        self::assertSame(
            'Unable to load the translation cache entry "application" for locale "en-GB" from "' . $file . '".',
            $exception->getMessage(),
        );
        self::assertSame(['path' => $file, 'key' => 'application', 'locale' => 'en-GB'], $exception->context);
        self::assertInstanceOf(ParseError::class, $exception->getPrevious());
    }

    #[Test]
    public function it_reports_an_entry_it_cannot_read(): void
    {
        $file = $this->file('application', 'en-GB');
        file_put_contents($file, data: "<?php\n\nreturn [];\n");
        chmod($file, permissions: 0o000);

        $exception = $this->failure(fn() => $this->cache->get('application', new Locale('en-GB')));

        self::assertSame(['path' => $file, 'key' => 'application', 'locale' => 'en-GB'], $exception->context);
        self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
    }

    #[Test]
    public function it_reports_an_entry_it_cannot_remove(): void
    {
        $locale = new Locale('en-GB');
        $this->cache->put('application', new TranslationCatalogue($locale, ['welcome' => 'Welcome']));
        chmod($this->directory->path, permissions: 0o555);

        $exception = $this->failure(fn() => $this->cache->forget('application', $locale));

        chmod($this->directory->path, permissions: 0o755);

        $file = $this->file('application', 'en-GB');
        self::assertSame(
            'Unable to remove the translation cache entry "application" for locale "en-GB" at "' . $file . '".',
            $exception->getMessage(),
        );
        self::assertSame(['path' => $file, 'key' => 'application', 'locale' => 'en-GB'], $exception->context);
        self::assertInstanceOf(ErrorException::class, $exception->getPrevious());
        self::assertTrue(is_file($file));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeKeys(): iterable
    {
        yield 'parent directory' => ['../../outside'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'backslashes' => ['..\\..\\outside'];
        yield 'dots only' => ['..'];
        yield 'null byte' => ["application\0.php"];
        yield 'empty' => [''];
        yield 'very long' => [str_repeat('application', times: 100)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedEntries(): iterable
    {
        yield 'string' => ["<?php\n\nreturn 'Welcome';\n"];
        yield 'nothing' => ["<?php\n\n\$messages = [];\n"];
        yield 'integer translation' => ["<?php\n\nreturn ['welcome' => 1];\n"];
        yield 'null translation' => ["<?php\n\nreturn ['welcome' => null];\n"];
        yield 'nested translations' => ["<?php\n\nreturn ['welcome' => ['nested' => 'Welcome']];\n"];
        yield 'object' => ["<?php\n\nreturn new ArrayObject(['welcome' => 'Welcome']);\n"];
    }

    private function fileName(string $key, string $locale): string
    {
        return hash('sha256', $key) . '.' . $locale . '.php';
    }

    private function file(string $key, string $locale): string
    {
        return $this->directory->path . '/' . $this->fileName($key, $locale);
    }

    private function failure(callable $operation): TranslationCacheException
    {
        try {
            $operation();
        } catch (TranslationCacheException $exception) {
            self::assertInstanceOf(I18nException::class, $exception);

            return $exception;
        }

        self::fail('Expected a TranslationCacheException.');
    }
}
