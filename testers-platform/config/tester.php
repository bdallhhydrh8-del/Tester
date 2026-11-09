<?php

return [
    // Where the React app lives. Share links are built as {frontend_url}/join/{token}
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Fixed platform limits (SRS v4, FR-X3). Admins cannot change them.
    // Dev 3 reads the upload values, Dev 4 reads the idle cap.
    'upload_max_mb' => (int) env('UPLOAD_MAX_MB', 10),
    'image_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'idle_cap_minutes' => (int) env('IDLE_CAP_MINUTES', 30),
    'unattached_file_days' => 1, // FR-X4

    // Daily backups. backup_path must be outside the server in production.
    'backup_path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
    'backup_keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    // Private uploaded files (Dev 3 owns the storage). Change if Dev 3 uses another folder.
    'uploads_path' => env('UPLOADS_PATH') ?: storage_path('app/private'),

    // Disk alert: warn when free space falls below this percentage
    'disk_path' => env('DISK_PATH') ?: base_path(),
    'disk_alert_free_percent' => (float) env('DISK_ALERT_FREE_PERCENT', 20),
];