<?php

return [
    'temporary_directory' => env('REPORT_TEMPORARY_DIRECTORY')
        ?: storage_path('app/private/report-temporary'),
    'temporary_file_grace_minutes' => (int) env('REPORT_TEMPORARY_FILE_GRACE_MINUTES', 60),
    'export_rate_limit_per_minute' => (int) env('REPORT_EXPORT_RATE_LIMIT_PER_MINUTE', 10),
];
