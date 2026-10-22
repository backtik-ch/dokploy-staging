<?php

return [
    'url' => env('LOKI_URL'),
    'username' => env('LOKI_USERNAME'),
    'password' => env('LOKI_PASSWORD'),
    'token' => env('LOKI_TOKEN'),
    'tenant_id' => env('LOKI_TENANT_ID'),
    'project_label' => env('LOKI_PROJECT_LABEL', 'project'),
    'selector' => env('LOKI_SELECTOR', '{project=~".+"}'),
    'timeout' => 15,
    'max_entries' => 5000,
];
