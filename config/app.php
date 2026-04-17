<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'Rota do Amor',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Maputo',
    'minimum_age' => (int) ($_ENV['MINIMUM_AGE'] ?? 18),
    'email_verification_required' => filter_var($_ENV['EMAIL_VERIFICATION_REQUIRED'] ?? true, FILTER_VALIDATE_BOOLEAN),
];
