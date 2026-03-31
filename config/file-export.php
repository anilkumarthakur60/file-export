<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk where exported files are stored.
    | Must match a disk defined in config/filesystems.php.
    |
    */
    'disk' => env('FILE_EXPORT_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The directory path (relative to the disk root) where export files
    | are saved.
    |
    */
    'path' => env('FILE_EXPORT_PATH', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Temporary URL Expiration (Days)
    |--------------------------------------------------------------------------
    |
    | When using cloud disks (S3, GCS, etc.), generated download URLs
    | are signed and expire after this many days. Ignored for local disks.
    |
    */
    'expire_days' => (int) env('FILE_EXPORT_EXPIRE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Default Export Format
    |--------------------------------------------------------------------------
    |
    | The format used when none is explicitly specified.
    |
    | Supported: "xlsx", "csv", "tsv", "ods", "html", "pdf"
    |
    | Note: PDF requires the mpdf/mpdf package to be installed.
    |
    */
    'default_format' => env('FILE_EXPORT_FORMAT', 'xlsx'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Rows
    |--------------------------------------------------------------------------
    |
    | The maximum number of rows allowed per export. Set to null for
    | unlimited. Helps prevent accidental memory exhaustion on large tables.
    |
    */
    'max_rows' => env('FILE_EXPORT_MAX_ROWS', 1000000),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | For query-based exports, rows are processed in chunks of this size.
    | Higher values are faster but use more memory.
    |
    */
    'chunk_size' => (int) env('FILE_EXPORT_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Filename
    |--------------------------------------------------------------------------
    |
    | Controls how generated filenames are constructed when no custom
    | filename is provided.
    |
    | Generated format: {prefix}-{random}_{timestamp}.{ext}
    | Example: export-a1b2c3d4_2024-01-15_143022.xlsx
    |
    */
    'filename' => [
        'prefix' => env('FILE_EXPORT_FILENAME_PREFIX', 'export'),
        'timestamp_format' => env('FILE_EXPORT_TIMESTAMP_FORMAT', 'Y-m-d_His'),
        'random_length' => (int) env('FILE_EXPORT_RANDOM_LENGTH', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Document Properties
    |--------------------------------------------------------------------------
    |
    | Metadata embedded in every exported file. Individual exports can
    | override these at runtime via withProperties().
    |
    */
    'properties' => [
        'creator' => env('FILE_EXPORT_CREATOR', ''),
        'company' => env('FILE_EXPORT_COMPANY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Styling
    |--------------------------------------------------------------------------
    |
    | Default visual styling applied to every export. Individual exports
    | can override any of these at runtime via withStyling().
    |
    | Colors use ARGB hex format (e.g., "4472C4" for blue, "FFFFFF" for
    | white). Set to empty string "" to disable.
    |
    */
    'styling' => [

        // Header row font
        'header_bold' => true,
        'header_font_size' => 12,
        'header_font_color' => 'FFFFFF',

        // Header row background
        'header_background' => '4472C4',

        // Cell formatting
        'wrap_text' => true,

        // Freeze the header row so it stays visible while scrolling
        'freeze_header' => true,

        // Add dropdown filter arrows to the header row
        'auto_filter' => true,

        // Label used for the serial number column when withSerialNumber() is called
        'serial_number_header' => 'S.N.',
    ],
];
