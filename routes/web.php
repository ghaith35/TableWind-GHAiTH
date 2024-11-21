<?php

// routes/web.php
use App\Http\Controllers\controllerTP;
use App\Http\Controllers\DatabaseController;

Route::get('/', [controllerTP::class, 'index'])->name('home');
Route::get('/', [DatabaseController::class, 'index']);
Route::get('/tables/{db_id}', [DatabaseController::class, 'getTables']);
Route::post('/save-query', [DatabaseController::class, 'saveQuery'])->name('saveQuery');

// Route to get query history
Route::get('/query-history', [DatabaseController::class, 'getQueryHistory'])->name('queryHistory');



