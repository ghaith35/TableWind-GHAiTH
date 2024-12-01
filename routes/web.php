<?php

// routes/web.php
use App\Http\Controllers\controllerTP;
use App\Http\Controllers\DatabaseController;
use Illuminate\Support\Facades\Route;


Route::get('/', [DatabaseController::class, 'index'])->name('index');
Route::get('/tables/{db_id}', [DatabaseController::class, 'getTables']);
Route::post('/run-query', [DatabaseController::class, 'runQuery']);
Route::post('/highlight-db', [DatabaseController::class, 'highlightDatabase']);
Route::post('/select', [DatabaseController::class, 'select']);
