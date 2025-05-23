<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () { return view('home'); })->name('home');

Route::get('/docs/map', function () { return view('docs.map'); })->name('docs.map');
Route::get('/docs/wad', function () { return view('docs.wad'); })->name('docs.wad');

Route::get('/wad', [\App\Http\Controllers\WadController::class, 'index'])->name('wad.index');
Route::get('/wad/{id}', [\App\Http\Controllers\WadController::class, 'show'])->name('wad.show');
Route::get('/wad/{id}/update-demos', [\App\Http\Controllers\WadController::class, 'updateDemos'])->name('wad.update-demos');
