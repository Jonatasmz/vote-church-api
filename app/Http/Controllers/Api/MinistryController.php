<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Ministry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MinistryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ministries = Ministry::withCount('members')
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
        $ministry->load('members:id,name,status');

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
