<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ElectionController;
use App\Http\Controllers\Api\VoteTokenController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\MemberController;

// Rotas públicas de autenticação
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Rotas públicas de votação (não requerem autenticação)
Route::post('tokens/validate', [VoteTokenController::class, 'validate']);
Route::post('members/validate-cpf', [MemberController::class, 'validateCpf']);
Route::post('vote', [VoteController::class, 'store']);
Route::post('vote-by-cpf', [VoteController::class, 'storeByCpf']);

// Rotas protegidas por autenticação JWT
Route::middleware('auth:api')->group(function () {
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);
    
    // Rotas de usuários
    Route::apiResource('users', UserController::class);
    
    // Rotas de membros
    Route::apiResource('members', MemberController::class);
    
    // Rotas de eleições
    Route::apiResource('elections', ElectionController::class);
    Route::post('elections/{election}/members', [ElectionController::class, 'addMembers']);
    Route::delete('elections/{election}/members/{member}', [ElectionController::class, 'removeMember']);
    
    // Rotas de tokens (QR Codes)
    Route::get('elections/{election}/tokens', [VoteTokenController::class, 'index']);
    Route::post('elections/{election}/tokens', [VoteTokenController::class, 'store']);
    Route::get('elections/{election}/tokens/{token}', [VoteTokenController::class, 'show']);
    Route::delete('elections/{election}/tokens/{token}', [VoteTokenController::class, 'destroy']);
    
    // Rotas de estatísticas
    Route::get('elections/{election}/statistics', [VoteController::class, 'statistics']);
});
