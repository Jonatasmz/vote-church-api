<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CandidateController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rotas de usuários
Route::apiResource('users', UserController::class);

// Rotas de candidatos
Route::apiResource('candidates', CandidateController::class);
