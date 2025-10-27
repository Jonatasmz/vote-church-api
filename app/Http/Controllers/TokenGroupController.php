<?php

namespace App\Http\Controllers;

use App\Models\TokenGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TokenGroupController extends Controller
{
    /**
     * Display a listing of token groups.
     */
    public function index(Request $request)
    {
        $query = TokenGroup::with(['elections', 'tokens']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by active (considering dates)
        if ($request->has('active') && $request->active == 'true') {
            $now = now();
            $query->where('status', 'active')
                  ->where(function($q) use ($now) {
                      $q->whereNull('valid_from')
                        ->orWhere('valid_from', '<=', $now);
                  })
                  ->where(function($q) use ($now) {
                      $q->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', $now);
                  });
        }

        // Order by creation date
        $query->orderBy('created_at', 'desc');

        $tokenGroups = $query->get();

        // Add token counts
        $tokenGroups->each(function($group) {
            $group->unused_tokens_count = $group->getUnusedTokensCount();
            $group->used_tokens_count = $group->getUsedTokensCount();
            $group->total_tokens_count = $group->getTotalTokensCount();
            $group->is_active = $group->isActive();
        });

        return response()->json($tokenGroups);
    }

    /**
     * Store a newly created token group.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'required|in:active,inactive',
            'election_ids' => 'nullable|array',
            'election_ids.*' => 'exists:elections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tokenGroup = TokenGroup::create($validator->validated());

        // Attach elections if provided
        if ($request->has('election_ids')) {
            $tokenGroup->elections()->sync($request->election_ids);
        }

        $tokenGroup->load(['elections', 'tokens']);
        
        return response()->json([
            'message' => 'Token group created successfully',
            'data' => $tokenGroup
        ], 201);
    }

    /**
     * Display the specified token group.
     */
    public function show($id)
    {
        $tokenGroup = TokenGroup::with(['elections', 'tokens'])->find($id);

        if (!$tokenGroup) {
            return response()->json([
                'message' => 'Token group not found'
            ], 404);
        }

        // Add token counts
        $tokenGroup->unused_tokens_count = $tokenGroup->getUnusedTokensCount();
        $tokenGroup->used_tokens_count = $tokenGroup->getUsedTokensCount();
        $tokenGroup->total_tokens_count = $tokenGroup->getTotalTokensCount();
        $tokenGroup->is_active = $tokenGroup->isActive();

        return response()->json($tokenGroup);
    }

    /**
     * Update the specified token group.
     */
    public function update(Request $request, $id)
    {
        $tokenGroup = TokenGroup::find($id);

        if (!$tokenGroup) {
            return response()->json([
                'message' => 'Token group not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'status' => 'sometimes|required|in:active,inactive',
            'election_ids' => 'nullable|array',
            'election_ids.*' => 'exists:elections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tokenGroup->update($validator->validated());

        // Update elections if provided
        if ($request->has('election_ids')) {
            $tokenGroup->elections()->sync($request->election_ids);
        }

        $tokenGroup->load(['elections', 'tokens']);

        return response()->json([
            'message' => 'Token group updated successfully',
            'data' => $tokenGroup
        ]);
    }

    /**
     * Remove the specified token group.
     */
    public function destroy($id)
    {
        $tokenGroup = TokenGroup::find($id);

        if (!$tokenGroup) {
            return response()->json([
                'message' => 'Token group not found'
            ], 404);
        }

        // Check if there are used tokens
        if ($tokenGroup->getUsedTokensCount() > 0) {
            return response()->json([
                'message' => 'Cannot delete token group with used tokens'
            ], 422);
        }

        $tokenGroup->delete();

        return response()->json([
            'message' => 'Token group deleted successfully'
        ]);
    }

    /**
     * Get active elections for a token group.
     */
    public function getActiveElections($id)
    {
        $tokenGroup = TokenGroup::find($id);

        if (!$tokenGroup) {
            return response()->json([
                'message' => 'Token group not found'
            ], 404);
        }

        $activeElections = $tokenGroup->getActiveElections();

        return response()->json($activeElections);
    }

    /**
     * Attach elections to a token group.
     */
    public function attachElections(Request $request, $id)
    {
        $tokenGroup = TokenGroup::find($id);

        if (!$tokenGroup) {
            return response()->json([
                'message' => 'Token group not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'election_ids' => 'required|array',
            'election_ids.*' => 'exists:elections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tokenGroup->elections()->syncWithoutDetaching($request->election_ids);
        $tokenGroup->load('elections');

        return response()->json([
            'message' => 'Elections attached successfully',
            'data' => $tokenGroup
        ]);
    }

    /**
     * Detach elections from a token group.
     */
    public function detachElections(Request $request, $id)
    {
        $tokenGroup = TokenGroup::find($id);

        if (!$tokenGroup) {
            return response()->json([
                'message' => 'Token group not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'election_ids' => 'required|array',
            'election_ids.*' => 'exists:elections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tokenGroup->elections()->detach($request->election_ids);
        $tokenGroup->load('elections');

        return response()->json([
            'message' => 'Elections detached successfully',
            'data' => $tokenGroup
        ]);
    }
}
