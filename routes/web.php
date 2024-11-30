<?php

// routes/web.php
use App\Http\Controllers\controllerTP;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\HandleUserQueryController;
use Illuminate\Support\Facades\Route;


Route::get('/', [DatabaseController::class, 'index'])->name('index');
Route::get('/tables/{db_id}', [DatabaseController::class, 'getTables']);
Route::post('/run-query', [DatabaseController::class, 'runQuery']);
<<<<<<< HEAD
Route::post('/highlight-db', [DatabaseController::class, 'highlightDatabase']);
=======
>>>>>>> 36b4070544249517399a1d95007915d431bbf9d6
