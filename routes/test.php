<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-professional-tax', function () {
    return response()->json([
        'status' => true,
        'message' => 'Professional Tax route is working',
        'timestamp' => now()
    ]);
});