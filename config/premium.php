<?php

return [
    'boost_duration_hours' => (int) ($_ENV['BOOST_DURATION_HOURS'] ?? 24),
    'premium_badge_price' => (float) ($_ENV['PREMIUM_BADGE_PRICE'] ?? 0),
    'identity_verification_price' => (float) ($_ENV['IDENTITY_VERIFICATION_PRICE'] ?? 0),
];
