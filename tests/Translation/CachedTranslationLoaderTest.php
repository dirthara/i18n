<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Translation;

use Dirthara\I18n\Locale;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Translation\PhpTranslationCache;
use Dirthara\I18n\Translation\PhpTranslationLoader;
use Dirthara\I18n\Translation\TranslationCatalogue;
use Dirthara\I18n\Tests\Fixtures\TemporaryDirectory;
use Dirthara\I18n\Translation\JsonTranslationLoader;
use Dirthara\I18n\Tests\Fixtures\TranslationDatabase;
use Dirthara\I18n\Exception\TranslationCacheException;
use Dirthara\I18n\Translation\CachedTranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;
use Dirthara\I18n\Translation\DatabaseTranslationLoader;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Dirthara\I18n\Tests\Fixtures\CountingTranslationLoader;

use function hash;
use function file_put_contents;

final class CachedTranslationLoaderTest extends TestCase
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
    public function it_loads_and_stores_a_catalogue_it_has_not_cached(): void
    {
        $source = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]);
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        $catalogue = $loader->load(new Locale('en-GB'));

        self::assertSame(1, $source->loads);
        self::assertSame(['welcome' => 'Welcome'], $catalogue->messages);
        self::assertSame(['welcome' => 'Welcome'], $this->cache->get('application', new Locale('en-GB'))?->messages);
    }

    #[Test]
    public function it_serves_a_cached_catalogue_without_the_wrapped_loader(): void
    {
        $source = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]);
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        $loader->load(new Locale('en-GB'));
        $catalogue = $loader->load(new Locale('en-GB'));
        $fresh = new CachedTranslationLoader($source, new PhpTranslationCache($this->directory->path), 'application');

        self::assertSame('Welcome', $fresh->load(new Locale('en-GB'))->get('welcome'));
        self::assertSame(1, $source->loads);
        self::assertSame(['welcome' => 'Welcome'], $catalogue->messages);
    }

    #[Test]
    public function it_caches_an_empty_catalogue(): void
    {
        $source = new CountingTranslationLoader();
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        $loader->load(new Locale('en-GB'));
        $catalogue = $loader->load(new Locale('en-GB'));

        self::assertSame(1, $source->loads);
        self::assertSame([], $catalogue->messages);
    }

    #[Test]
    public function it_caches_each_locale_separately(): void
    {
        $source = new CountingTranslationLoader([
            'en-GB' => ['welcome' => 'Welcome'],
            'nl-NL' => ['welcome' => 'Welkom'],
        ]);
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        self::assertSame('Welcome', $loader->load(new Locale('en-GB'))->get('welcome'));
        self::assertSame('Welkom', $loader->load(new Locale('nl-NL'))->get('welcome'));
        self::assertSame('Welcome', $loader->load(new Locale('en-GB'))->get('welcome'));
        self::assertSame(2, $source->loads);
    }

    #[Test]
    public function it_caches_each_key_separately(): void
    {
        $application = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Application']]);
        $validation = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Validation']]);
        $locale = new Locale('en-GB');

        new CachedTranslationLoader($application, $this->cache, 'application')->load($locale);
        new CachedTranslationLoader($validation, $this->cache, 'validation')->load($locale);

        self::assertSame(
            'Application',
            new CachedTranslationLoader($validation, $this->cache, 'application')->load($locale)->get('welcome'),
        );
        self::assertSame(
            'Validation',
            new CachedTranslationLoader($application, $this->cache, 'validation')->load($locale)->get('welcome'),
        );
        self::assertSame(1, $application->loads);
        self::assertSame(1, $validation->loads);
    }

    #[Test]
    public function it_loads_again_after_the_entry_is_forgotten(): void
    {
        $source = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]);
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        $loader->load(new Locale('en-GB'));
        $this->cache->forget('application', new Locale('en-GB'));
        $loader->load(new Locale('en-GB'));
        $loader->load(new Locale('en-GB'));

        self::assertSame(2, $source->loads);
    }

    #[Test]
    public function it_fails_on_a_malformed_entry_instead_of_loading_again(): void
    {
        file_put_contents(
            $this->directory->path . '/' . hash('sha256', data: 'application') . '.en-GB.php',
            data: "<?php\n\nreturn ['welcome' => false];\n",
        );
        $source = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Welcome']]);
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        try {
            $loader->load(new Locale('en-GB'));
            self::fail('Expected a TranslationCacheException.');
        } catch (TranslationCacheException $exception) {
            self::assertSame('application', $exception->context['key']);
        }

        self::assertSame(0, $source->loads);
    }

    #[Test]
    public function it_rejects_a_catalogue_for_another_locale_than_requested(): void
    {
        $source = new CountingTranslationLoader(answerWith: new Locale('nl-NL'));
        $loader = new CachedTranslationLoader($source, $this->cache, 'application');

        try {
            $loader->load(new Locale('en-GB'));
            self::fail('Expected a TranslationLoaderException.');
        } catch (TranslationLoaderException $exception) {
            self::assertSame(
                'A translation loader returned a catalogue for locale "nl-NL" when "en-GB" was requested.',
                $exception->getMessage(),
            );
            self::assertSame(['locale' => 'en-GB', 'loadedLocale' => 'nl-NL'], $exception->context);
        }

        self::assertNull($this->cache->get('application', new Locale('nl-NL')));
        self::assertNull($this->cache->get('application', new Locale('en-GB')));
    }

    #[Test]
    public function it_does_not_query_the_database_again_until_the_entry_is_forgotten(): void
    {
        $database = new TranslationDatabase();
        $database->createTable();
        $database->insert('en-GB', 'welcome', 'Welcome');
        $loader = new CachedTranslationLoader(
            new DatabaseTranslationLoader($database->database, prefix: 'app'),
            $this->cache,
            'database',
        );

        self::assertSame(['app.welcome' => 'Welcome'], $loader->load(new Locale('en-GB'))->messages);

        $database->drop();

        self::assertSame(['app.welcome' => 'Welcome'], $loader->load(new Locale('en-GB'))->messages);

        $this->cache->forget('database', new Locale('en-GB'));

        $this->expectException(TranslationLoaderException::class);

        $loader->load(new Locale('en-GB'));
    }

    #[Test]
    public function it_caches_php_and_json_translations_without_reading_them_again(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write(
            'translations/en-GB/validation.php',
            "<?php\n\nreturn ['between' => ['numeric' => 'Between']];\n",
        );
        $filesystem->write('translations/en-GB.json', '{"Log out": "Log out"}');
        $php = new CachedTranslationLoader(new PhpTranslationLoader($filesystem, 'translations'), $this->cache, 'php');
        $json = new CachedTranslationLoader(
            new JsonTranslationLoader($filesystem, 'translations'),
            $this->cache,
            'json',
        );
        $locale = new Locale('en-GB');

        $php->load($locale);
        $json->load($locale);
        $filesystem->deleteDirectory('translations');

        self::assertSame(['validation.between.numeric' => 'Between'], $php->load($locale)->messages);
        self::assertSame(['Log out' => 'Log out'], $json->load($locale)->messages);
    }

    #[Test]
    public function it_returns_the_catalogue_the_cache_holds(): void
    {
        $locale = new Locale('en-GB');
        $this->cache->put('application', new TranslationCatalogue($locale, ['welcome' => 'Cached']));
        $source = new CountingTranslationLoader(['en-GB' => ['welcome' => 'Source']]);

        $catalogue = new CachedTranslationLoader($source, $this->cache, 'application')->load($locale);

        self::assertSame('Cached', $catalogue->get('welcome'));
        self::assertSame(0, $source->loads);
    }
}
