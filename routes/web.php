<?php

// routes/web.php
use App\Http\Controllers\controllerTP;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\HandleUserQueryController;


Route::get('/', [DatabaseController::class, 'index'])->name('index');
Route::get('/tables/{db_id}', [DatabaseController::class, 'getTables']);

