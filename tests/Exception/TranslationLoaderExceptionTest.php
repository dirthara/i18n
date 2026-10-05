<?php

declare(strict_types=1);

namespace Dirthara\I18n\Tests\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\I18n\Exception\I18nException;
use Dirthara\I18n\Exception\TranslationLoaderException;

final class TranslationLoaderExceptionTest extends TestCase
{
    #[Test]
    public function it_reports_a_stream_wrapper_that_could_not_be_registered(): void
    {
        $exception = TranslationLoaderException::registrationFailed('dirthara-i18n');

        self::assertInstanceOf(I18nException::class, $exception);
        self::assertSame(
            'Unable to load PHP translations: the stream wrapper for the "dirthara-i18n://" scheme could not be '
            . 'registered.',
            $exception->getMessage(),
        );
        self::assertSame(['scheme' => 'dirthara-i18n'], $exception->context);
    }
}
