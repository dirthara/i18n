<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Dirthara\I18n\Locale;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemReader;
use League\Flysystem\FilesystemException;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function sort;
use function trim;
use function substr;
use function basename;
use function is_array;
use function is_string;
use function str_ends_with;
use function array_key_exists;

use const SORT_STRING;

final readonly class PhpTranslationLoader implements TranslationLoader
{
    private TranslationPrefix $prefix;

    private PhpSourceRunner $runner;

    /**
     * @throws InvalidTranslationPrefixException
     */
    public function __construct(
        private FilesystemReader $filesystem,
        private string $path,
        ?string $prefix = null,
    ) {
        $this->prefix = new TranslationPrefix($prefix);
        $this->runner = new PhpSourceRunner();
    }

    /**
     * @throws TranslationLoaderException
     */
    public function load(Locale $locale): TranslationCatalogue
    {
        $source = trim($this->path, characters: '/');
        $messages = [];

        foreach ($this->files($source, $source === '' ? $locale->code : $source . '/' . $locale->code) as $file) {
            // @mago-expect analysis:mixed-assignment
            $translations = $this->runner->run($file, $this->read($file));

            if (!is_array($translations)) {
                throw TranslationLoaderException::invalidPhpReturn($file);
            }

            $this->flatten($file, substr(basename($file), offset: 0, length: -4), $translations, $messages);
        }

        return new TranslationCatalogue($locale, $messages);
    }

    /**
     * @return list<string>
     *
     * @throws TranslationLoaderException
     */
    private function files(string $source, string $directory): array
    {
        $files = [];

        try {
            if ($source !== '' && !$this->filesystem->directoryExists($source)) {
                throw TranslationLoaderException::missingSource($source);
            }

            if (!$this->filesystem->directoryExists($directory)) {
                return [];
            }

            foreach ($this->filesystem->listContents($directory) as $item) {
                if (!$item instanceof FileAttributes || !str_ends_with($item->path(), '.php')) {
                    continue;
                }

                $files[] = $item->path();
            }
        } catch (FilesystemException $exception) {
            throw TranslationLoaderException::unreadableSource($directory, previous: $exception);
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @throws TranslationLoaderException
     */
    private function read(string $file): string
    {
        try {
            return $this->filesystem->read($file);
        } catch (FilesystemException $exception) {
            throw TranslationLoaderException::unreadableSource($file, previous: $exception);
        }
    }

    /**
     * @param array<array-key, mixed> $translations
     * @param array<string, string> $messages
     *
     * @throws TranslationLoaderException
     */
    private function flatten(string $file, string $group, array $translations, array &$messages): void
    {
        // @mago-expect analysis:mixed-assignment
        foreach ($translations as $segment => $translation) {
            $key = $group . '.' . $segment;

            if (is_array($translation)) {
                $this->flatten($file, $key, $translation, $messages);

                continue;
            }

            if (!is_string($translation)) {
                throw TranslationLoaderException::invalidTranslation($file, $key);
            }

            $prefixed = $this->prefix->apply($key);

            if (array_key_exists($prefixed, $messages)) {
                throw TranslationLoaderException::duplicateKey($file, $prefixed);
            }

            $messages[$prefixed] = $translation;
        }
    }
}
