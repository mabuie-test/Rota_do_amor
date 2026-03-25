<?php

return [
    'debito_base_url' => $_ENV['DEBITO_BASE_URL'] ?? 'https://my.debito.co.mz/api/v1',
    'debito_wallet_id' => $_ENV['DEBITO_WALLET_ID'] ?? '',
    'activation_price' => (float) ($_ENV['ACTIVATION_PRICE'] ?? 100),
    'monthly_subscription_price' => (float) ($_ENV['MONTHLY_SUBSCRIPTION_PRICE'] ?? 40),
    'boost_price' => (float) ($_ENV['BOOST_PRICE'] ?? 25),
];
