<?php

declare(strict_types=1);

namespace Anil\FileExport\DataTransferObjects;

final readonly class StyleConfig
{
    public function __construct(
        public bool $headerBold = true,
        public string $headerBackground = '4472C4',
        public string $headerFontColor = 'FFFFFF',
        public bool $wrapText = true,
        public bool $freezeHeader = true,
        public bool $autoFilter = true,
        public string $serialNumberHeader = 'S.N.',
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $headerBold = $config['header_bold'] ?? true;
        $headerBackground = $config['header_background'] ?? '4472C4';
        $headerFontColor = $config['header_font_color'] ?? 'FFFFFF';
        $wrapText = $config['wrap_text'] ?? true;
        $freezeHeader = $config['freeze_header'] ?? true;
        $autoFilter = $config['auto_filter'] ?? true;
        $serialNumberHeader = $config['serial_number_header'] ?? 'S.N.';

        return new self(
            headerBold: is_bool($headerBold) ? $headerBold : true,
            headerBackground: is_string($headerBackground) ? $headerBackground : '4472C4',
            headerFontColor: is_string($headerFontColor) ? $headerFontColor : 'FFFFFF',
            wrapText: is_bool($wrapText) ? $wrapText : true,
            freezeHeader: is_bool($freezeHeader) ? $freezeHeader : true,
            autoFilter: is_bool($autoFilter) ? $autoFilter : true,
            serialNumberHeader: is_string($serialNumberHeader) ? $serialNumberHeader : 'S.N.',
        );
    }
}
