<?php

namespace App\Http\Controllers\api;

use App\Events\InvitationNotificationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\ProcessInvitationRequest;
use App\Http\Requests\Invitation\SendInvitationRequest;
use App\Http\Requests\JoinRequest\ProcessJoinRequest;
use App\Http\Requests\JoinRequest\SendJoinRequest;
use App\Models\Project;
use App\Models\Request as JoinRequestModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Invitation\InviteUserRequest;
use App\Models\Profile;


class RequestController extends Controller
{
    // =============  (Join Requests) =============

    /**
     * List all join requests for a project (owner or manager only).
     */
    public function listJoinRequests(Request $request, Project $project): JsonResponse
    {
        try {
            // Authorization: only owner or manager can view join requests
            $userId = $request->user()->id;
            if (!$project->isOwner($userId) && !$project->isManager($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only project owner or manager can view join requests.'
                ], 403);
            }

            $perPage = $request->input('per_page', 20);
            $requests = $project->joinRequests()
                ->with(['sender.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $requests->items(),
                'meta' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('List join requests failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load join requests. Please try again later.'
            ], 500);
        }
    }

    public function sendJoinRequest(SendJoinRequest $request, Project $project): JsonResponse
    {
        $existing = JoinRequestModel::where('project_id', $project->id)
            ->where('sender_id', $request->user()->id)
            ->where('type', 'join_request')
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending request for this project.'
            ], 409);
        }

        try {
            DB::beginTransaction();

            $joinRequest = JoinRequestModel::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $project->created_by,
                'project_id' => $project->id,
                'type' => 'join_request',
                'status' => 'pending',
                'message' => $request->input('message'),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Send join request failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send join request. Please try again later.'
            ], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$project->created_by],
            scenario: 'join_request_received',
            request: $joinRequest,
            project: $project,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Join request sent successfully.',
            'data' => $joinRequest
        ], 201);
    }

    public function approveJoinRequest(ProcessJoinRequest $request, Project $project, JoinRequestModel $joinRequest): JsonResponse
    {
        if ($joinRequest->project_id !== $project->id || $joinRequest->type !== 'join_request') {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 404);
        }

        if ($joinRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 400);
        }

        if ($project->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Project is not active, cannot accept new members.'], 422);
        }

        try {
            DB::beginTransaction();

            $role = $request->input('role', 'user');
            $project->addUser($joinRequest->sender_id, $role);

            $joinRequest->update([
                'status' => 'approved',
                'responded_at' => now(),
                'responded_by' => $request->user()->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve join request failed: ' . $e->getMessage(), [
                'request_id' => $joinRequest->id,
                'project_id' => $project->id,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to approve request. Please try again later.'], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$joinRequest->sender_id],
            scenario: 'join_request_approved',
            request: $joinRequest,
            project: $project,
            actor: $request->user(),
        );

        return response()->json(['success' => true, 'message' => 'Join request approved. User added to project.']);
    }

    public function rejectJoinRequest(Request $request, Project $project, JoinRequestModel $joinRequest): JsonResponse
    {
        if ($joinRequest->project_id !== $project->id || $joinRequest->type !== 'join_request') {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 404);
        }

        if ($joinRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request already processed.'], 400);
        }

        try {
            DB::beginTransaction();

            $joinRequest->update([
                'status' => 'rejected',
                'responded_at' => now(),
                'responded_by' => $request->user()->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject join request failed: ' . $e->getMessage(), [
                'request_id' => $joinRequest->id,
                'project_id' => $project->id,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to reject request. Please try again later.'], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$joinRequest->sender_id],
            scenario: 'join_request_rejected',
            request: $joinRequest,
            project: $project,
            actor: $request->user(),
        );

        return response()->json(['success' => true, 'message' => 'Join request rejected.']);
    }


    // =============  (Invitations) =============

/*Send an invitation from a user's profile page */
    public function inviteUser(InviteUserRequest $request, Profile $profile): JsonResponse
    {
        // Get the project and check its status
        $project = Project::find($request->project_id);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'The selected project does not exist.'
            ], 422);
        }

        if ($project->status !== 'active') {
            $statusMessage = match ($project->status) {
                'completed' => 'This project is already completed.',
                'paused' => 'This project is currently paused.',
                default => 'This project is not active.',
            };
            return response()->json([
                'success' => false,
                'message' => "Cannot send invitation. {$statusMessage} Only active projects can accept new members."
            ], 422);
        }

        // Restrict role to only 'user' or 'observer' (default 'user')
        $allowedRoles = ['user', 'observer'];
        $role = $request->input('role', 'user');
        if (!in_array($role, $allowedRoles)) {
            $role = 'user';
        }

        try {
            DB::beginTransaction();

            $invitation = JoinRequestModel::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $profile->user_id,
                'project_id' => $request->project_id,
                'type' => 'invitation',
                'status' => 'pending',
                'message' => $request->input('message'),
                'role' => $role,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invite user failed: ' . $e->getMessage(), [
                'sender_id' => $request->user()->id,
                'receiver_id' => $profile->user_id,
                'project_id' => $request->project_id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation. Please try again later.'
            ], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$invitation->receiver_id],
            scenario: 'invitation_sent',
            request: $invitation,
            project: $project,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully.',
            'data' => $invitation
        ], 201);
    }

    public function myInvitations(Request $request): JsonResponse
    {
        try {
            $invitations = JoinRequestModel::where('receiver_id', $request->user()->id)
                ->where('type', 'invitation')
                ->where('status', 'pending')
                ->with(['project', 'sender.profile'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $invitations,
                'total' => $invitations->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Fetching my invitations failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load invitations. Please try again later.'
            ], 500);
        }
    }

    /*Send an invitation from inside a project */
    public function sendInvitation(SendInvitationRequest $request, Project $project): JsonResponse
    {
        // Check project status before proceeding
        if ($project->status !== 'active') {
            $statusMessage = match ($project->status) {
                'completed' => 'This project is already completed.',
                'paused' => 'This project is currently paused.',
                default => 'This project is not active.',
            };
            return response()->json([
                'success' => false,
                'message' => "Cannot send invitation. {$statusMessage} Only active projects can accept new members."
            ], 422);
        }

        $inviteeId = $request->invitee_id;

        // Check for pending invitation
        $existing = JoinRequestModel::where('project_id', $project->id)
            ->where('receiver_id', $inviteeId)
            ->where('type', 'invitation')
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation already sent to this user.'
            ], 409);
        }

        // Restrict role to 'user' or 'observer' only (default 'user')
        $allowedRoles = ['user', 'observer'];
        $role = $request->input('role', 'user');
        if (!in_array($role, $allowedRoles)) {
            $role = 'user';
        }

        try {
            DB::beginTransaction();

            $invitation = JoinRequestModel::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $inviteeId,
                'project_id' => $project->id,
                'type' => 'invitation',
                'status' => 'pending',
                'message' => $request->input('message'),
                'role' => $role,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Send invitation failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'sender_id' => $request->user()->id,
                'invitee_id' => $request->invitee_id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation. Please try again later.'
            ], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$invitation->receiver_id],
            scenario: 'invitation_sent',
            request: $invitation,
            project: $project,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent.',
            'data' => $invitation
        ], 201);
    }
    public function acceptInvitation(ProcessInvitationRequest $request, JoinRequestModel $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invitation already processed.'
            ], 400);
        }

        $project = $invitation->project;

        if ($project->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot join this project because it is not active.'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $role = $invitation->role ?? 'user';
            $project->addUser($invitation->receiver_id, $role);

            $invitation->update([
                'status' => 'approved',
                'responded_at' => now(),
                'responded_by' => $request->user()->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accept invitation failed: ' . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation. Please try again later.'
            ], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$invitation->sender_id],
            scenario: 'invitation_accepted',
            request: $invitation,
            project: $project,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted. You are now a member of the project.'
        ]);
    }

    public function rejectInvitation(ProcessInvitationRequest $request, JoinRequestModel $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invitation already processed.'
            ], 400);
        }

        $project = $invitation->project;

        try {
            DB::beginTransaction();

            $invitation->update([
                'status' => 'rejected',
                'responded_at' => now(),
                'responded_by' => $request->user()->id,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject invitation failed: ' . $e->getMessage(), [
                'invitation_id' => $invitation->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject invitation. Please try again later.'
            ], 500);
        }

        InvitationNotificationEvent::dispatch(
            userIds: [$invitation->sender_id],
            scenario: 'invitation_rejected',
            request: $invitation,
            project: $project,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation rejected.'
        ]);
    }
}
