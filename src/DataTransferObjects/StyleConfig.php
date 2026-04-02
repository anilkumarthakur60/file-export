<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

/**
 * Immutable value object holding all sheet styling configuration.
 *
 * Created from the config file by default. Pass a custom instance to
 * withStyling() on the exporter or sheet builder to override per-export.
 */
final readonly class StyleConfig
{
    /**
     * @param  bool  $headerBold  Whether the header row text is bold.
     * @param  int  $headerFontSize  Font size for the header row in points.
     * @param  string  $headerFontColor  ARGB hex color for header text (e.g., "FFFFFF").
     * @param  string  $headerBackground  ARGB hex color for header background (e.g., "4472C4"). Empty string to disable.
     * @param  bool  $wrapText  Whether cell text wraps to multiple lines.
     * @param  bool  $freezeHeader  Whether the header row is frozen (stays visible while scrolling).
     * @param  bool  $autoFilter  Whether filter dropdowns are added to the header row.
     * @param  string  $serialNumberHeader  Column label for the serial number column.
     */
    public function __construct(
        public bool $headerBold = true,
        public int $headerFontSize = 12,
        public string $headerFontColor = 'FFFFFF',
        public string $headerBackground = '4472C4',
        public bool $wrapText = true,
        public bool $freezeHeader = true,
        public bool $autoFilter = true,
        public string $serialNumberHeader = 'S.N.',
    ) {}

    /**
     * Create from a config array (typically from config/file-export.php → styling).
     *
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            headerBold: is_bool($values['header_bold'] ?? null) ? $values['header_bold'] : true,
            headerFontSize: is_int($values['header_font_size'] ?? null) ? $values['header_font_size'] : 12,
            headerFontColor: is_string($values['header_font_color'] ?? null) ? $values['header_font_color'] : 'FFFFFF',
            headerBackground: is_string($values['header_background'] ?? null) ? $values['header_background'] : '4472C4',
            wrapText: is_bool($values['wrap_text'] ?? null) ? $values['wrap_text'] : true,
            freezeHeader: is_bool($values['freeze_header'] ?? null) ? $values['freeze_header'] : true,
            autoFilter: is_bool($values['auto_filter'] ?? null) ? $values['auto_filter'] : true,
            serialNumberHeader: is_string($values['serial_number_header'] ?? null) ? $values['serial_number_header'] : 'S.N.',
        );
    }
}
