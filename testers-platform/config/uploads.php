<?php

/*
 * Upload defaults. Used until Dev 2's `settings` table is merged; after that,
 * UploadSettings reads the admin's values first and falls back to these.
 */
return [

    // Disk defined in config/filesystems.php. Never served from the web root.
    'disk' => 'private',

    // Folders inside that disk.
    'files_dir' => 'images',
    'chunks_dir' => 'chunks',

    // Maximum size per image, in MB.
    // The admin can set 1 to 20 (FR-X1).
    'max_mb' => (int) env('UPLOAD_MAX_MB', 10),

    'min_allowed_mb' => 1,
    'max_allowed_mb' => 20,

    // Images only. Keys are the allowed MIME types, values the stored extension.
    'allowed_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

    // Files above this size must use the chunked upload (FR-T12).
    'chunk_threshold_mb' => 10,

    // Size of one chunk. Keep it well under PHP's post_max_size.
    'chunk_size_mb' => 2,

    // Cleanup (CleanupFilesJob).
    'session_keep_days' => 7,           // finished or cancelled upload_sessions rows are removed after this

    'unattached_ttl_hours' => 24,       // files never attached to a response or issue
    'session_ttl_hours' => 24,          // open chunked uploads left unfinished
];
