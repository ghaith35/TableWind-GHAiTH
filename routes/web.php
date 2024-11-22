<?php

// routes/web.php
use App\Http\Controllers\controllerTP;
use App\Http\Controllers\DatabaseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TableController;

Route::get('/', [DatabaseController::class, 'index'])->name('index');
Route::get('/tables/{db_id}', [DatabaseController::class, 'getTables']);
Route::post('/run-query', [DatabaseController::class, 'runQuery']);
Route::get('/query-history', [DatabaseController::class, 'getQueryHistory']);
Route::get('/tables', [TableController::class, 'showTables']); // Show all tables
Route::get('/table-content/{id}', [TableController::class, 'showTableContent']); // Show content of a specific table
