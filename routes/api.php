<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ElectionController;
use App\Http\Controllers\Api\VoteTokenController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\MinistryController;
use App\Http\Controllers\Api\OccurrenceController;
use App\Http\Controllers\Api\OccurrenceDutyController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\TokenGroupController;

// Rotas públicas de autenticação
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Rotas públicas de votação (não requerem autenticação)
Route::get('elections/active/public', [ElectionController::class, 'activePublic']);
Route::get('elections/{election}/public', [ElectionController::class, 'showPublic']);
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
    Route::put('elections/{election}/members/order', [ElectionController::class, 'updateMembersOrder']);
    Route::delete('elections/{election}/members/{member}', [ElectionController::class, 'removeMember']);
    
    // Rotas de grupos de tokens
    Route::apiResource('token-groups', TokenGroupController::class);
    Route::get('token-groups/{tokenGroup}/active-elections', [TokenGroupController::class, 'getActiveElections']);
    Route::post('token-groups/{tokenGroup}/attach-elections', [TokenGroupController::class, 'attachElections']);
    Route::post('token-groups/{tokenGroup}/detach-elections', [TokenGroupController::class, 'detachElections']);
    
    // Rotas de tokens (QR Codes) - agora vinculados a grupos
    Route::get('token-groups/{tokenGroup}/tokens', [VoteTokenController::class, 'index']);
    Route::post('token-groups/{tokenGroup}/tokens', [VoteTokenController::class, 'store']);
    Route::get('token-groups/{tokenGroup}/tokens/{token}', [VoteTokenController::class, 'show']);
    Route::delete('token-groups/{tokenGroup}/tokens/{token}', [VoteTokenController::class, 'destroy']);
    
    // Rotas de ministérios
    Route::apiResource('ministries', MinistryController::class);
    Route::post('ministries/{ministry}/users', [MinistryController::class, 'attachUser']);
    Route::delete('ministries/{ministry}/users/{user}', [MinistryController::class, 'detachUser']);
    Route::post('ministries/{ministry}/members', [MinistryController::class, 'attachMember']);
    Route::delete('ministries/{ministry}/members/{member}', [MinistryController::class, 'detachMember']);

    // Rotas de programação da igreja
    Route::apiResource('schedules', ScheduleController::class);

    // Rotas de ocorrências (cultos específicos)
    Route::get('schedules/{schedule}/occurrences', [OccurrenceController::class, 'index']);
    Route::post('schedules/{schedule}/occurrences', [OccurrenceController::class, 'store']);
    Route::get('schedules/{schedule}/occurrences/{occurrence}', [OccurrenceController::class, 'show']);
    Route::delete('schedules/{schedule}/occurrences/{occurrence}', [OccurrenceController::class, 'destroy']);

    // Rotas de escalas (duties) por ocorrência
    Route::post('schedules/{schedule}/occurrences/{occurrence}/duties', [OccurrenceDutyController::class, 'store']);
    Route::delete('schedules/{schedule}/occurrences/{occurrence}/duties/{duty}', [OccurrenceDutyController::class, 'destroy']);

    // Rotas de estatísticas
    Route::get('elections/{election}/statistics', [VoteController::class, 'statistics']);
});
