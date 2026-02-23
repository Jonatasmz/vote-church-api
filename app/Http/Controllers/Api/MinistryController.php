<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MinistryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ministries = Ministry::withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ministries,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $ministry = Ministry::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ministério criado com sucesso',
            'data' => $ministry,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Ministry $ministry)
    {
        $ministry->load('users:id,name,email,permission');

        return response()->json([
            'success' => true,
            'data' => $ministry,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Ministry $ministry)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $ministry->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ministério atualizado com sucesso',
            'data' => $ministry,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ministry $ministry)
    {
        $ministry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ministério removido com sucesso',
        ]);
    }

    /**
     * Attach a user to the ministry.
     */
    public function attachUser(Request $request, Ministry $ministry)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($ministry->users()->where('user_id', $validated['user_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário já pertence a este ministério',
            ], 422);
        }

        $ministry->users()->attach($validated['user_id']);

        $ministry->load('users:id,name,email,permission');

        return response()->json([
            'success' => true,
            'message' => 'Usuário adicionado ao ministério com sucesso',
            'data' => $ministry,
        ]);
    }

    /**
     * Detach a user from the ministry.
     */
    public function detachUser(Ministry $ministry, User $user)
    {
        if (! $ministry->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não pertence a este ministério',
            ], 422);
        }

        $ministry->users()->detach($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usuário removido do ministério com sucesso',
        ]);
    }

    /**
     * Attach a member to the ministry.
     */
    public function attachMember(Request $request, Ministry $ministry)
    {
        $validated = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->whereNull('deleted_at')],
        ]);

        if ($ministry->members()->where('member_id', $validated['member_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Membro já pertence a este ministério',
            ], 422);
        }

        $ministry->members()->attach($validated['member_id']);

        return response()->json([
            'success' => true,
            'message' => 'Membro adicionado ao ministério com sucesso',
        ]);
    }

    /**
     * Detach a member from the ministry.
     */
    public function detachMember(Ministry $ministry, Member $member)
    {
        if (! $ministry->members()->where('member_id', $member->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Membro não pertence a este ministério',
            ], 422);
        }

        $ministry->members()->detach($member->id);

        return response()->json([
            'success' => true,
            'message' => 'Membro removido do ministério com sucesso',
        ]);
    }
}
