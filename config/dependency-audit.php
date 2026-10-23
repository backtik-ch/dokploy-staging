<?php

return [
    'notifications_url' => env('NOTIFICATIONS_URL', 'https://notifications.backtik.ch'),
    'token' => env('DEPENDENCY_AUDIT_WEBHOOK_TOKEN'),
    'timeout' => 120,
];
