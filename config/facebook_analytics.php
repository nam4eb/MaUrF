<?php

return [
    'privacy_mode_default' => env('FACEBOOK_ANALYTICS_PRIVACY_MODE_DEFAULT', env('ANALYTICS_PRIVACY_MODE_DEFAULT', true)),
    'conversation_gap_hours' => (int) env('FACEBOOK_ANALYTICS_CONVERSATION_GAP_HOURS', env('ANALYTICS_CONVERSATION_GAP_HOURS', 8)),
    'recency_decay_days' => (int) env('FACEBOOK_ANALYTICS_RECENCY_DECAY_DAYS', env('ANALYTICS_RECENCY_DECAY_DAYS', 90)),
    'delete_source_after_import' => env('DELETE_SOURCE_ARCHIVE_AFTER_IMPORT', true),
    'max_upload_kb' => (int) env('MAX_IMPORT_SIZE_KB', env('ANALYTICS_MAX_UPLOAD_KB', 2097152)),
    'max_extracted_bytes' => (int) env('MAX_EXTRACTED_SIZE', env('ANALYTICS_MAX_EXTRACTED_BYTES', 5368709120)),
    'max_files' => (int) env('MAX_ARCHIVE_FILES', env('ANALYTICS_MAX_FILES', 20000)),
    'max_compression_ratio' => (int) env('MAX_ARCHIVE_COMPRESSION_RATIO', 200),
    'facebook_weights' => ['reaction' => 1, 'comment' => 3, 'reply' => 4, 'mention' => 5, 'tag' => 5],
    'overall_weights' => ['frequency' => .25, 'active_days' => .20, 'recency' => .20, 'mutuality' => .15, 'initiation' => .10, 'facebook' => .10],
    'trend_thresholds' => ['rapid' => 50, 'change' => 15],
];
