<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a request
$request = Illuminate\Http\Request::create(
    '/api/attendance-summary/bulk-insert',
    'POST',
    [
        'corpId' => 'maco',
        'companyName' => 'IMS MACO SERVICES INDIA PVT. LTD.',
        'month' => 'October',
        'year' => '2025'
    ]
);

// Handle the request
$response = $kernel->handle($request);

// Output the response
echo $response->getContent();
echo PHP_EOL;

// Clean up
$kernel->terminate($request, $response);
