<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use stdClass;
use JsonException;
use Dirthara\I18n\Locale;
use League\Flysystem\FilesystemReader;
use League\Flysystem\FilesystemException;
use Dirthara\I18n\Contract\TranslationLoader;
use Dirthara\I18n\Exception\TranslationLoaderException;
use Dirthara\I18n\Exception\InvalidTranslationPrefixException;

use function ltrim;
use function is_string;
use function json_decode;
use function get_object_vars;

use const JSON_THROW_ON_ERROR;

final readonly class JsonTranslationLoader implements TranslationLoader
{
    private TranslationPrefix $prefix;

    /**
     * @throws InvalidTranslationPrefixException
     */
    public function __construct(
        private FilesystemReader $filesystem,
        private string $path,
        ?string $prefix = null,
    ) {
        $this->prefix = new TranslationPrefix($prefix);
    }

    /**
     * @throws TranslationLoaderException
     */
    public function load(Locale $locale): TranslationCatalogue
    {
        $file = ltrim($this->path . '/' . $locale->code . '.json', characters: '/');

        try {
            if (!$this->filesystem->fileExists($file)) {
                return new TranslationCatalogue($locale, []);
            }

            $json = $this->filesystem->read($file);
        } catch (FilesystemException $exception) {
            throw TranslationLoaderException::unreadableSource($file, previous: $exception);
        }

        try {
            // @mago-expect analysis:mixed-assignment
            $document = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TranslationLoaderException::malformedJson($file, previous: $exception);
        }

        if (!$document instanceof stdClass) {
            throw TranslationLoaderException::invalidJsonDocument($file);
        }

        $messages = [];

        // @mago-expect analysis:mixed-assignment
        foreach (get_object_vars($document) as $key => $translation) {
            if (!is_string($translation)) {
                throw TranslationLoaderException::invalidTranslation($file, (string) $key);
            }

            $messages[$this->prefix->apply((string) $key)] = $translation;
        }

        return new TranslationCatalogue($locale, $messages);
    }
}
