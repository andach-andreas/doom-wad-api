<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () { return view('home'); })->name('home');

Route::get('/docs/map', function () { return view('docs.map'); })->name('docs.map');
Route::get('/docs/wad', function () { return view('docs.wad'); })->name('docs.wad');

Route::get('/wad', [\App\Http\Controllers\WadController::class, 'index'])->name('wad.index');
Route::get('/wad/{id}', [\App\Http\Controllers\WadController::class, 'show'])->name('wad.show');
Route::get('/wad/{id}/update-demos', [\App\Http\Controllers\WadController::class, 'updateDemos'])->name('wad.update-demos');

Route::prefix('admin/commands')->name('admin.commands.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CommandRunController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\CommandRunController::class, 'store'])->name('store');
    Route::get('/{run}', [\App\Http\Controllers\CommandRunController::class, 'show'])->name('show');
    Route::get('/{run}/output', [\App\Http\Controllers\CommandRunController::class, 'output'])->name('output');
    Route::post('/{run}/cancel', [\App\Http\Controllers\CommandRunController::class, 'cancel'])->name('cancel');
    Route::post('/{run}/kill', [\App\Http\Controllers\CommandRunController::class, 'kill'])->name('kill');
});
