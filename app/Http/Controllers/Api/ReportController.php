<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Ministry;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Relatório de membros em ministérios.
     */
    public function ministries()
    {
        $members = Member::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->withCount('ministries')
            ->with('ministries:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Member $m) => [
                'id'                => $m->id,
                'name'              => $m->name,
                'ministries_count'  => $m->ministries_count,
                'ministries'        => $m->ministries->map(fn ($mi) => [
                    'id'   => $mi->id,
                    'name' => $mi->name,
                ])->values(),
            ])
            ->values();

        $ministryStats = Ministry::query()
            ->withCount([
                'members as active_members_count' => fn ($q) => $q->where('status', 'active')->whereNull('members.deleted_at'),
                'memberRequests as pending_requests_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->orderByDesc('active_members_count')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Ministry $mi) => [
                'id'                      => $mi->id,
                'name'                    => $mi->name,
                'active_members_count'    => $mi->active_members_count,
                'pending_requests_count'  => $mi->pending_requests_count,
            ])
            ->values();

        $totalActive = $members->count();
        $withMinistry = $members->where('ministries_count', '>', 0)->count();
        $withoutMinistry = $totalActive - $withMinistry;
        $totalLinks = $members->sum('ministries_count');
        $avgPerMember = $totalActive > 0 ? round($totalLinks / $totalActive, 2) : 0;
        $avgPerEnrolled = $withMinistry > 0 ? round($totalLinks / $withMinistry, 2) : 0;
        $maxMinistries = (int) ($members->max('ministries_count') ?? 0);

        return response()->json([
            'success' => true,
            'data'    => [
                'summary' => [
                    'total_active_members'      => $totalActive,
                    'members_with_ministry'     => $withMinistry,
                    'members_without_ministry'  => $withoutMinistry,
                    'total_ministries'          => $ministryStats->count(),
                    'total_links'               => $totalLinks,
                    'avg_ministries_per_member' => $avgPerMember,
                    'avg_ministries_per_enrolled' => $avgPerEnrolled,
                    'max_ministries_per_member' => $maxMinistries,
                ],
                'ministries' => $ministryStats,
                'members'    => $members,
            ],
        ]);
    }
}
