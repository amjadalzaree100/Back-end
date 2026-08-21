<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    private const PER_PAGE_OPTIONS = [15, 30, 50];

    private const SORT_OPTIONS = ['newest', 'oldest', 'name_asc', 'name_desc'];

    public function index(Request $request)
    {
        $dateRange = DateRange::fromRequest($request);

        $query = User::query()->withTrashed();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $verified = $request->get('email_verified', 'all');
        if ($verified === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verified === 'no') {
            $query->whereNull('email_verified_at');
        }

        $query->createdBetween($dateRange->from, $dateRange->to);

        $sort = in_array($request->get('sort'), self::SORT_OPTIONS, true)
            ? $request->get('sort')
            : 'newest';

        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'name_asc' => $query->orderBy('name', 'asc')->orderBy('id', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc')->orderBy('id', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $perPage = (int) $request->get('per_page', 15);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $users = $query->paginate($perPage)->appends(request()->query());

        return view('admin.users.index', compact(
            'users',
            'dateRange',
            'verified',
            'sort',
            'perPage',
        ));
    }

    public function show($id)
    {
        $user = User::withTrashed()->with(['profile', 'projects', 'ownedProjects'])->findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    public function toggleStatus(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);
        $isActivating = !$user->is_active;

        DB::beginTransaction();
        try {
            if ($isActivating) {
                // Reactivate user
                $user->reactivate();
                
                // Unlock all owned projects
                Project::where('created_by', $user->id)
                    ->where('owner_suspended', true)
                    ->update(['owner_suspended' => false]);

                // Notify project owners (batched) about reactivation
                $this->notifyOwnersAboutMemberReactivation($user);

                $message = 'User activated successfully.';
            } else {
                // Suspend user
                $user->suspend();

                // Lock all owned projects
                Project::where('created_by', $user->id)
                    ->update(['owner_suspended' => true]);

                // Delete reminders
                $deletedCount = Reminder::where('user_id', $user->id)->delete();
                Log::info("Deleted {$deletedCount} reminders for suspended user ID: {$user->id}");

                // Notify project owners (batched) about suspension
                $this->notifyOwnersAboutMemberSuspension($user);

                $message = 'User suspended. All reminders deleted and owned projects locked.';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to toggle user status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
            ], 500);
        }
    }

    /**
     * Notify project owners (batched) that a member has been suspended.
     * Each owner gets one notification with the count of affected projects.
     */
    private function notifyOwnersAboutMemberSuspension(User $suspendedUser): void
    {
        // Get all projects where this user is a member (not as owner)
        // Group by project owner (created_by)
        $ownerProjectCounts = \App\Models\ProjectUser::where('user_id', $suspendedUser->id)
            ->where('role', '!=', 'owner')
            ->join('projects', 'project_users.project_id', '=', 'projects.id')
            ->whereNull('projects.deleted_at')
            ->groupBy('projects.created_by')
            ->selectRaw('projects.created_by as owner_id, COUNT(*) as project_count')
            ->pluck('project_count', 'owner_id');

        $notificationService = app(\App\Services\NotificationService::class);

        foreach ($ownerProjectCounts as $ownerId => $projectCount) {
            $notificationService->send(
                $ownerId,
                'Member Suspended',
                "User {$suspendedUser->name} has been suspended from the platform. They were a member of {$projectCount} project(s) you own.",
                'warning',
                null,
                null,
                '⚠️'
            );
        }
    }

    /**
     * Notify project owners (batched) that a member has been reactivated.
     * Each owner gets one notification with the count of affected projects.
     */
    private function notifyOwnersAboutMemberReactivation(User $reactivatedUser): void
    {
        // Get all projects where this user is a member (not as owner)
        $ownerProjectCounts = \App\Models\ProjectUser::where('user_id', $reactivatedUser->id)
            ->where('role', '!=', 'owner')
            ->join('projects', 'project_users.project_id', '=', 'projects.id')
            ->whereNull('projects.deleted_at')
            ->groupBy('projects.created_by')
            ->selectRaw('projects.created_by as owner_id, COUNT(*) as project_count')
            ->pluck('project_count', 'owner_id');

        $notificationService = app(\App\Services\NotificationService::class);

        foreach ($ownerProjectCounts as $ownerId => $projectCount) {
            $notificationService->send(
                $ownerId,
                'Member Reactivated',
                "User {$reactivatedUser->name} has been reactivated on the platform. They are a member of {$projectCount} project(s) you own.",
                'success',
                null,
                null,
                '✅'
            );
        }
    }
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "User {$name} has been permanently deleted.");
    }
}
