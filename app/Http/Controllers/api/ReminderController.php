<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Http\Requests\Reminder\SnoozeReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Project;


class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $reminders = $request->user()
                ->reminders()
                ->with('tasks')
                ->orderBy('remind_at', 'asc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => ReminderResource::collection($reminders),
                'meta' => [
                    'current_page' => $reminders->currentPage(),
                    'last_page' => $reminders->lastPage(),
                    'per_page' => $reminders->perPage(),
                    'total' => $reminders->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch reminders: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load reminders. Please try again later.',
            ], 500);
        }
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reminder = Reminder::create([
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'message' => $request->message,
                'remind_at' => $request->remind_at,
                'status' => 'pending',
            ]);

            if ($request->has('task_ids')) {
                $reminder->tasks()->sync($request->task_ids);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reminder created successfully.',
                'data' => new ReminderResource($reminder->load('tasks')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create reminder: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reminder. Please try again later.',
            ], 500);
        }
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): JsonResponse
    {
        try {
            if ($reminder->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this reminder.',
                ], 403);
            }

            DB::beginTransaction();

            $reminder->update($request->only(['title', 'message', 'remind_at']));

            if ($request->has('task_ids')) {
                $reminder->tasks()->sync($request->task_ids);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reminder updated successfully.',
                'data' => new ReminderResource($reminder->load('tasks')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update reminder: ' . $e->getMessage(), [
                'reminder_id' => $reminder->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reminder. Please try again later.',
            ], 500);
        }
    }

    public function destroy(Request $request, Reminder $reminder): JsonResponse
    {
        try {
            // Authorization: only the owner can delete
            if ($reminder->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this reminder.',
                ], 403);
            }

            DB::beginTransaction();

            $reminder->tasks()->detach();
            $reminder->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reminder deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete reminder: ' . $e->getMessage(), [
                'reminder_id' => $reminder->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete reminder. Please try again later.',
            ], 500);
        }
    }

    public function snooze(SnoozeReminderRequest $request, Reminder $reminder): JsonResponse
    {
        try {
            // Authorization: only the owner can snooze
            if ($reminder->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to snooze this reminder.',
                ], 403);
            }

            if ($reminder->remind_at > now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The reminder cannot be snoozed until its time arrives.'
                ], 422);
            }

            DB::beginTransaction();

            $reminder->update([
                'remind_at' => $request->new_remind_at,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reminder snoozed successfully.',
                'data' => new ReminderResource($reminder->load('tasks')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to snooze reminder: ' . $e->getMessage(), [
                'reminder_id' => $reminder->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to snooze reminder. Please try again later.',
            ], 500);
        }
    }

    public function dismiss(Request $request, Reminder $reminder): JsonResponse
    {
        try {
            if ($reminder->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to dismiss this reminder.',
                ], 403);
            }

            if ($reminder->remind_at > now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The reminder cannot be dismissed until its time arrives.'
                ], 422);
            }

            DB::beginTransaction();

            $reminder->tasks()->detach();
            $reminder->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reminder dismissed successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to dismiss reminder: ' . $e->getMessage(), [
                'reminder_id' => $reminder->id,
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to dismiss reminder. Please try again later.',
            ], 500);
        }
    }
        /**
     * Get all reminders for a specific project and user.
     */

    public function getProjectReminders(Request $request, Project $project): JsonResponse
    {
        try {
            $reminders = $request->user()
                ->reminders()
                ->whereHas('tasks', function ($query) use ($project) {
                    $query->where('project_id', $project->id);
                })
                ->with('tasks')
                ->orderBy('remind_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ReminderResource::collection($reminders),
                'total' => $reminders->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch project reminders: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load project reminders. Please try again later.'
            ], 500);
        }
    }
}
