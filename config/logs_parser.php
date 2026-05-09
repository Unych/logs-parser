<?php

return [
    'batch_size' => (int) env('LOG_IMPORT_BATCH_SIZE', 1000),
    'max_upload_mb' => (int) env('LOG_IMPORT_MAX_FILE_MB', 512),
    'cache_ttl' => (int) env('STATS_CACHE_TTL', 300),
    'upload_throttle_per_min' => (int) env('UPLOAD_THROTTLE_PER_MIN', 10),
];
