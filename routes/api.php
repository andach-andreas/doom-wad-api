<?php

use App\Http\Controllers\Api\MapApiController;
use App\Http\Controllers\Api\WadApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/map/{filename}/{mapID}', [MapApiController::class, 'index']);
    Route::get('/wad/{filename}', [WadApiController::class, 'index']);
});
