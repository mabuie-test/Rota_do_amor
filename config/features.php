<?php

return [
    'max_profile_photos' => (int) ($_ENV['MAX_PROFILE_PHOTOS'] ?? 6),
    'max_upload_mb' => (int) ($_ENV['MAX_UPLOAD_MB'] ?? 5),
    'swipe_daily_limit' => (int) ($_ENV['SWIPE_DAILY_LIMIT'] ?? 100),
    'subscription_duration_days' => (int) ($_ENV['SUBSCRIPTION_DURATION_DAYS'] ?? 30),
];
