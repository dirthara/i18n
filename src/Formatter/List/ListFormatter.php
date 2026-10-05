<?php

declare(strict_types=1);

namespace Dirthara\I18n\Formatter\List;

use IntlListFormatter;
use Dirthara\I18n\Locale;
use Dirthara\I18n\Enum\ListType;
use Dirthara\I18n\Enum\ListWidth;
use Dirthara\I18n\Exception\FormatterException;
use Dirthara\I18n\Exception\InvalidListItemException;
use Dirthara\I18n\Contract\ListFormatter as ListFormatterContract;

use function strlen;
use function is_string;
use function strtolower;
use function get_debug_type;

final class ListFormatter implements ListFormatterContract
{
    private const string FORMATTER = 'list';

    private const int MAXIMUM_LOCALE_LENGTH = 156;

    /**
     * @var array<string, IntlListFormatter>
     */
    private static array $formatters = [];

    public function __construct(
        public readonly Locale $locale,
    ) {}

    /**
     * @param array<array-key, mixed> $items
     *
     * @throws FormatterException
     * @throws InvalidListItemException
     */
    public function format(array $items, ListType $type = ListType::And, ListWidth $width = ListWidth::Wide): string
    {
        $strings = [];

        // @mago-expect analysis:mixed-assignment
        foreach ($items as $position => $item) {
            if (!is_string($item)) {
                throw InvalidListItemException::notString($this->locale, $position, get_debug_type($item));
            }

            $strings[] = $item;
        }

        $style = strtolower($type->name . '-' . $width->name);
        $formatter = $this->formatter($style, $type, $width);
        $formatted = $formatter->format($strings);

        if ($formatted === false) {
            throw FormatterException::formatFailed(
                self::FORMATTER,
                $this->locale,
                $style,
                $formatter->getErrorCode(),
                $formatter->getErrorMessage(),
            );
        }

        return $formatted;
    }

    /**
     * @throws FormatterException
     */
    private function formatter(string $style, ListType $type, ListWidth $width): IntlListFormatter
    {
        $key = $this->locale->code . '|' . $style;
        $formatter = self::$formatters[$key] ?? null;

        if ($formatter !== null) {
            return $formatter;
        }

        if (strlen($this->locale->code) > self::MAXIMUM_LOCALE_LENGTH) {
            throw FormatterException::creationFailed(self::FORMATTER, $this->locale, $style);
        }

        $formatter = new IntlListFormatter(
            $this->locale->code,
            match ($type) {
                ListType::And => IntlListFormatter::TYPE_AND,
                ListType::Or => IntlListFormatter::TYPE_OR,
                ListType::Units => IntlListFormatter::TYPE_UNITS,
            },
            match ($width) {
                ListWidth::Wide => IntlListFormatter::WIDTH_WIDE,
                ListWidth::Short => IntlListFormatter::WIDTH_SHORT,
                ListWidth::Narrow => IntlListFormatter::WIDTH_NARROW,
            },
        );
        self::$formatters[$key] = $formatter;

        return $formatter;
    }
}
