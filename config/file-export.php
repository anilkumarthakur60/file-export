<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk to store exported files on.
    | Supports any disk configured in config/filesystems.php.
    |
    */
    'disk' => env('FILE_EXPORT_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Export Path Prefix
    |--------------------------------------------------------------------------
    |
    | The directory prefix for stored export files.
    |
    */
    'path' => env('FILE_EXPORT_PATH', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Temporary URL Expiry (in days)
    |--------------------------------------------------------------------------
    |
    | For cloud disks (S3, GCS, etc.), temporary signed URLs will expire
    | after this many days. For local disks, a regular URL is returned.
    |
    */
    'expire_days' => (int) env('FILE_EXPORT_EXPIRE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Default Export Format
    |--------------------------------------------------------------------------
    |
    | The default format when none is specified.
    | Supported: "xlsx", "csv", "pdf"
    | PDF requires mpdf/mpdf to be installed.
    |
    */
    'default_format' => env('FILE_EXPORT_FORMAT', 'xlsx'),

    /*
    |--------------------------------------------------------------------------
    | Max Export Rows
    |--------------------------------------------------------------------------
    |
    | The maximum number of rows allowed per export.
    | Set to null for unlimited.
    |
    */
    'max_rows' => env('FILE_EXPORT_MAX_ROWS', 1000000),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | For query-based exports, this is the number of rows processed per
    | chunk. Increase for speed, decrease for memory efficiency.
    |
    */
    'chunk_size' => (int) env('FILE_EXPORT_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Timestamp Format
    |--------------------------------------------------------------------------
    |
    | The date format appended to generated filenames.
    |
    */
    'timestamp_format' => env('FILE_EXPORT_TIMESTAMP_FORMAT', 'Y-m-d_His'),

    /*
    |--------------------------------------------------------------------------
    | Styling
    |--------------------------------------------------------------------------
    |
    | Default styling applied to all exports. Individual exports can
    | override these via withStyleConfig().
    |
    */
    'styling' => [
        'header_bold' => true,
        'header_background' => '4472C4',
        'header_font_color' => 'FFFFFF',
        'wrap_text' => true,
        'freeze_header' => true,
        'auto_filter' => true,
        'serial_number_header' => 'S.N.',
    ],
];
