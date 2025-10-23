<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CandidateController;
use App\Http\Controllers\Api\ElectionController;
use App\Http\Controllers\Api\VoteTokenController;
use App\Http\Controllers\Api\VoteController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rotas de usuários
Route::apiResource('users', UserController::class);

// Rotas de candidatos
Route::apiResource('candidates', CandidateController::class);

// Rotas de eleições
Route::apiResource('elections', ElectionController::class);
Route::post('elections/{election}/candidates', [ElectionController::class, 'addCandidates']);
Route::delete('elections/{election}/candidates/{candidate}', [ElectionController::class, 'removeCandidate']);

// Rotas de tokens (QR Codes)
Route::get('elections/{election}/tokens', [VoteTokenController::class, 'index']);
Route::post('elections/{election}/tokens', [VoteTokenController::class, 'store']);
Route::get('elections/{election}/tokens/{token}', [VoteTokenController::class, 'show']);
Route::delete('elections/{election}/tokens/{token}', [VoteTokenController::class, 'destroy']);
Route::post('tokens/validate', [VoteTokenController::class, 'validate']);

// Rotas de votação
Route::post('vote', [VoteController::class, 'store']);
Route::get('elections/{election}/statistics', [VoteController::class, 'statistics']);
