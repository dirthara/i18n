<?php

declare(strict_types=1);

namespace Dirthara\I18n\Translation;

use Closure;
use Throwable;
use Dirthara\I18n\Exception\TranslationLoaderException;

use function ltrim;
use function strlen;
use function substr;
use function in_array;
use function stream_get_wrappers;
use function stream_wrapper_register;

/**
 * @internal
 */
final class PhpSourceRunner
{
    public const string SCHEME = 'dirthara-i18n';

    /**
     * @var array<string, string>
     */
    private static array $sources = [];

    private static bool $registered = false;

    /**
     * @throws TranslationLoaderException
     */
    public function run(string $path, string $source): mixed
    {
        $this->registerWrapper();

        $uri = self::SCHEME . '://' . ltrim($path, characters: '/');

        self::$sources[$uri] = $source;

        try {
            return require $uri;
        } catch (Throwable $exception) {
            throw TranslationLoaderException::phpSourceFailed($path, previous: $exception);
        } finally {
            unset(self::$sources[$uri]);
        }
    }

    /**
     * @throws TranslationLoaderException
     */
    private function registerWrapper(): void
    {
        if (self::$registered) {
            return;
        }

        $wrapper = new class {
            /**
             * @var null|Closure(string): ?string
             */
            public static ?Closure $sources = null;

            public mixed $context = null;

            private string $source = '';

            private int $position = 0;

            // @mago-expect lint:method-name
            public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
            {
                $source = self::$sources === null ? null : (self::$sources)($path);

                if ($source === null) {
                    return false;
                }

                $this->source = $source;

                return true;
            }

            // @mago-expect lint:method-name
            public function stream_read(int $count): string
            {
                $chunk = substr($this->source, $this->position, $count);
                $this->position += strlen($chunk);

                return $chunk;
            }

            // @mago-expect lint:method-name
            public function stream_eof(): bool
            {
                return $this->position >= strlen($this->source);
            }

            /**
             * @return array{size: int}
             */
            // @mago-expect lint:method-name
            public function stream_stat(): array
            {
                return ['size' => strlen($this->source)];
            }

            // @mago-expect lint:method-name
            public function stream_set_option(int $option, int $value, ?int $parameter): bool
            {
                return false;
            }

            /**
             * @return array{mode: int, size: int}|false
             */
            // @mago-expect lint:method-name
            public function url_stat(string $path, int $flags): array|false
            {
                $source = self::$sources === null ? null : (self::$sources)($path);

                return $source === null ? false : ['mode' => 0o100_444, 'size' => strlen($source)];
            }
        };

        $wrapper::$sources = static fn(string $uri): ?string => self::$sources[$uri] ?? null;

        if (in_array(self::SCHEME, stream_get_wrappers(), strict: true)) {
            throw TranslationLoaderException::schemeAlreadyRegistered(self::SCHEME);
        }

        if (!stream_wrapper_register(self::SCHEME, $wrapper::class)) {
            throw TranslationLoaderException::registrationFailed(self::SCHEME); // @codeCoverageIgnore
        }

        self::$registered = true;
    }
}
