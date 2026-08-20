<?php

use App\Http\Controllers\api\Auth\EmailVerificationController;
use App\Http\Controllers\api\Auth\LoginController;
use App\Http\Controllers\api\Auth\LogoutController;
use App\Http\Controllers\api\Auth\PasswordResetController;
use App\Http\Controllers\api\Auth\RegisterController;
use App\Http\Controllers\api\ChainController;
use App\Http\Controllers\api\CommentController;
use App\Http\Controllers\api\FavoriteController;
use App\Http\Controllers\api\FavoriteProjectController;
use App\Http\Controllers\api\FcmController;
use App\Http\Controllers\api\GroupController;
use App\Http\Controllers\api\GroupMemberController;
use App\Http\Controllers\api\NoteController;
use App\Http\Controllers\api\NotificationController;
use App\Http\Controllers\api\ProfileController;
use App\Http\Controllers\api\ProjectCommentController;
use App\Http\Controllers\api\ProjectController;
use App\Http\Controllers\api\ProjectReportController;
use App\Http\Controllers\api\ProjectUserController;
use App\Http\Controllers\api\ReminderController;
use App\Http\Controllers\api\ReportController;
use App\Http\Controllers\api\RequestController;
use App\Http\Controllers\api\SearchController;
use App\Http\Controllers\api\TaskAssignmentController;
use App\Http\Controllers\api\TaskController;
use App\Http\Controllers\api\TaskDependencyController;
use App\Http\Controllers\api\TaskStatusController;
use Illuminate\Support\Facades\Route;


// PUBLIC ROUTES (No authentication required)
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/verify-email', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle.verify');
Route::post('/resend-verification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle.verification');
Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

// AUTHENTICATED ROUTES (Only requires valid token)
Route::middleware(['auth:sanctum'])->group(function () {
    // Route::post('/logout', [LogoutController::class, 'logout']);
    Route::get('/me', [LoginController::class, 'me']);
    Route::get('/email-status', [EmailVerificationController::class, 'checkStatus']);
});

// PROFILES ROUTES (Requires authentication only)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/my-profile', [ProfileController::class, 'myProfile']);

    Route::post('/profiles/block/{userId}', [ProfileController::class, 'blockUser']);
    Route::delete('/profiles/unblock/{userId}', [ProfileController::class, 'unblockUser']);
    Route::get('/profiles/blocked-users', [ProfileController::class, 'getBlockedUsers']);

    Route::get('/profiles/{profile}/skills', [ProfileController::class, 'getSkills']);
    Route::post('/profiles/{profile}/skills', [ProfileController::class, 'addSkill']);
    Route::put('/profiles/{profile}/skills/{skill}', [ProfileController::class, 'updateSkillRating']);
    Route::delete('/profiles/{profile}/skills/{skill}', [ProfileController::class, 'removeSkill']);

    Route::get('/profiles/{profile}/can-message', [ProfileController::class, 'canSendMessage']);
    Route::get('/profiles/{profile}/can-invite', [ProfileController::class, 'canSendInvitation']);

    Route::get('/profiles/{profile}', [ProfileController::class, 'show']);
    Route::put('/profiles/{profile}', [ProfileController::class, 'update']);
    Route::patch('/profiles/{profile}', [ProfileController::class, 'update']);

    Route::post('/profiles/{profile}/invite', [RequestController::class, 'inviteUser']);
});
// ============= ROUTES OUTSIDE project.not.locked (Always work) =============

Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('chains')->group(function () {
        Route::post('/{chain}/projects', [ChainController::class, 'addProject']);
        Route::delete('/{chain}/projects/{project}', [ChainController::class, 'removeProject']);
        Route::post('/{chain}/reorder', [ChainController::class, 'reorder']);
    });
    Route::put('/chains/{chain}', [ChainController::class, 'updateChainName']);
});

// ============= REMINDERS ROUTES =============
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('reminders')->group(function () {
        Route::get('/', [ReminderController::class, 'index']);
        Route::post('/', [ReminderController::class, 'store']);
        Route::put('/{reminder}', [ReminderController::class, 'update']);
        Route::delete('/{reminder}', [ReminderController::class, 'destroy']);
        Route::post('/{reminder}/snooze', [ReminderController::class, 'snooze']);
        Route::post('/{reminder}/dismiss', [ReminderController::class, 'dismiss']);
    });
    Route::get('/projects/{project}/reminders', [ReminderController::class, 'getProjectReminders']);
});

Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::get('/my-active-projects', [ProjectController::class, 'myActiveOwnedProjects']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::post('/logout-all', [LogoutController::class, 'logoutFromAllDevices']);
    Route::get('/devices', [LogoutController::class, 'devices']);
    Route::delete('/devices/{tokenId}', [LogoutController::class, 'logoutDevice']);
    Route::post('/logout-other-devices', [LogoutController::class, 'logoutOtherDevices']);
});

Route::get('/my-tasks', [TaskController::class, 'myPendingTasks'])->middleware(['auth:sanctum', 'is.active', 'verified']);

// Project Reactions
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/reactions')->group(function () {
        Route::post('/toggle', [App\Http\Controllers\api\ProjectReactionController::class, 'toggleReaction']);
        Route::get('/', [App\Http\Controllers\api\ProjectReactionController::class, 'getProjectReactions']);
    });
});

// Invitations
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::get('/my-invitations', [RequestController::class, 'myInvitations']);
    Route::put('/invitations/{invitation}/accept', [RequestController::class, 'acceptInvitation']);
    Route::put('/invitations/{invitation}/reject', [RequestController::class, 'rejectInvitation']);
});


//  join Requests
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/join-requests')->group(function () {
        Route::post('/', [RequestController::class, 'sendJoinRequest']);
        Route::get('/', [RequestController::class, 'listJoinRequests']);
        Route::post('/{joinRequest}/approve', [RequestController::class, 'approveJoinRequest'])->middleware('project.not.locked');
        Route::post('/{joinRequest}/reject', [RequestController::class, 'rejectJoinRequest'])->middleware('project.not.locked');
    });

});


// PROJECT COMMENTS ROUTES (only for public projects)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/comments')->group(function () {
        Route::get('/', [ProjectCommentController::class, 'index']);
        Route::post('/', [ProjectCommentController::class, 'store']);
        Route::get('/{comment}', [ProjectCommentController::class, 'show']);
        Route::put('/{comment}', [ProjectCommentController::class, 'update']);
        Route::delete('/{comment}', [ProjectCommentController::class, 'destroy']);
    });
});

// PROJECT REPORT ROUTES
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('project-reports')->group(function () {
        Route::post('/', [ProjectReportController::class, 'store']);
    });
});

// SEARCH ROUTES
Route::get('/search', [SearchController::class, 'search'])->middleware(['auth:sanctum', 'is.active', 'verified']);

// FAVORITE PROJECTS ROUTES
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::post('/my-favorite-projects/toggle/{projectId}', [FavoriteProjectController::class, 'toggle']);
    Route::get('/my-favorite-projects', [FavoriteProjectController::class, 'index']);
    Route::post('/my-favorite-projects', [FavoriteProjectController::class, 'store']);
    Route::delete('/my-favorite-projects/{projectId}', [FavoriteProjectController::class, 'destroy']);
    Route::get('/my-favorite-projects/check/{projectId}', [FavoriteProjectController::class, 'check']);
});

// FAVORITES ROUTES
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::post('/my-favorites/toggle/{userId}', [FavoriteController::class, 'toggle']);
    Route::get('/my-favorites', [FavoriteController::class, 'index']);
    Route::post('/my-favorites', [FavoriteController::class, 'store']);
    Route::delete('/my-favorites/{userId}', [FavoriteController::class, 'destroy']);
    Route::get('/my-favorites/check/{userId}', [FavoriteController::class, 'check']);
});

// NOTES ROUTES
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::get('/my-note', [NoteController::class, 'show']);
    Route::put('/note', [NoteController::class, 'write']);
    Route::delete('/note', [NoteController::class, 'clear']);
});

// PROJECTS ROUTES - Read only (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::get('/my-projects', [ProjectController::class, 'myProjects']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('/projects/{project}/status', [ProjectController::class, 'updateStatus']);
    Route::patch('/projects/{project}/visibility', [ProjectController::class, 'updateVisibility']);
    Route::get('/projects/{project}/stats', [TaskController::class, 'getProjectStats']);


    // Trash routes
    Route::get('/my-projects/trash', [ProjectController::class, 'trashed']);
    Route::post('/projects/{projectId}/restore', [ProjectController::class, 'restore']);
    Route::delete('/projects/{project}/force-delete', [ProjectController::class, 'forceDelete']);
    Route::delete('/my-projects/empty-trash', [ProjectController::class, 'emptyTrash']);
});

// PROJECT USERS ROUTES - Read only (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/users')->group(function () {
        Route::get('/', [ProjectUserController::class, 'index']);
        Route::post('/leave', [ProjectUserController::class, 'leaveProject']);
    });
});

// ============= GROUPS ROUTES - Read only =============
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::get('/projects/{project}/groups', [GroupController::class, 'index']);
    Route::get('/projects/{project}/groups/{group}', [GroupController::class, 'show']);
    Route::get('/projects/{project}/groups/{group}/members', [GroupMemberController::class, 'index']);
    Route::post('/projects/{project}/groups/{group}/leave', [GroupMemberController::class, 'leaveGroup']);
    Route::get('/projects/{project}/groups/{group}/manager', [GroupMemberController::class, 'getManager']);
    Route::get('/my-managed-groups', [GroupController::class, 'myManagedGroups']);
    Route::get('/my-groups', [GroupController::class, 'myGroups']);
    Route::get('/projects/{project}/groups/{group}/completed-tasks', [TaskController::class, 'getGroupCompletedTasks']);
    Route::get('/projects/{project}/groups/{group}/archived-tasks', [TaskController::class, 'getGroupArchivedTasks']);
    Route::get('/projects/{project}/groups/{group}/assigned-tasks', [TaskController::class, 'getGroupAssignedTasks']);
    Route::get('/projects/{project}/groups/{group}/unassigned-tasks', [TaskController::class, 'getGroupUnassignedTasks']);
    Route::get('/projects/{project}/groups/{group}/kanban', [TaskController::class, 'getGroupKanban']);



});

// ============= GROUPS ROUTES - Write operations =============
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::post('/projects/{project}/groups', [GroupController::class, 'store']);
    Route::put('/projects/{project}/groups/{group}', [GroupController::class, 'update']);
    Route::delete('/projects/{project}/groups/{group}', [GroupController::class, 'destroy']);
    Route::post('/projects/{project}/groups/{group}/members', [GroupMemberController::class, 'addMember']);
    Route::delete('/projects/{project}/groups/{group}/members/{userId}', [GroupMemberController::class, 'removeMember']);
    Route::post('/projects/{project}/groups/{group}/transfer-manager', [GroupMemberController::class, 'transferManager']);
    Route::post('/projects/{project}/groups/{group}/transfer-manager/{userId}', [GroupMemberController::class, 'transferManager']);
    Route::post('/projects/{project}/groups/{group}/set-manager', [GroupMemberController::class, 'setManager']);
    Route::post('/projects/{project}/groups/{group}/expel-all-members', [GroupController::class, 'expelAllMembers']);
    Route::post('/projects/{project}/groups/{group}/detach-tasks', [GroupController::class, 'detachTasks']);

});

// TASKS ROUTES - Read only (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/tasks')->group(function () {
        Route::get('/archived', [TaskController::class, 'archivedTasks']);
        Route::get('/assigned-kanban', [TaskController::class, 'getAssignedTasksKanban']);
        Route::get('/trashed', [TaskController::class, 'trashed']);
        Route::get('/completed', [TaskController::class, 'getCompletedTasks']);
        Route::get('/assigned', [TaskController::class, 'getAssignedTasks']);
        Route::get('/unassigned', [TaskController::class, 'getUnassignedTasks']);
        Route::get('/group-tasks', [TaskController::class, 'getGroupTasks']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::get('/{task}/history', [TaskController::class, 'getTaskHistory']);
    });
});

Route::get('/projects/{project}/tasks/{task}/transfer-history', [TaskController::class, 'transferHistory'])
    ->middleware(['auth:sanctum', 'is.active', 'verified']);

Route::post('/projects/{project}/tasks/{task}/transfer', [TaskController::class, 'transfer'])
    ->middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked']);

Route::get('/manager-kanban', [TaskController::class, 'managerAssignedTasks'])
    ->middleware(['auth:sanctum', 'is.active', 'verified']);

// TASK ASSIGNMENTS ROUTES - Read only (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/tasks/{task}/assignments')->group(function () {
        Route::get('/', [TaskAssignmentController::class, 'index']);
    });
    Route::get('/my-assigned-tasks', [TaskAssignmentController::class, 'myAssignedTasks']);
});

// TASK DEPENDENCIES ROUTES - Read only (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/tasks/{task}/dependencies')->group(function () {
        Route::get('/', [TaskDependencyController::class, 'index']);
    });
});


// NOTIFICATIONS ROUTES (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::post('/test', [NotificationController::class, 'test']);
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // FCM device token routes
    Route::prefix('fcm')->group(function () {
        Route::post('/register', [FcmController::class, 'register'])->middleware('throttle:30,1');
        Route::post('/unregister', [FcmController::class, 'unregister'])->middleware('throttle:30,1');
        Route::get('/tokens', [FcmController::class, 'index'])->middleware('throttle:60,1');
    });
});

// REPORT ROUTES (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('reports')->group(function () {
        Route::post('/', [ReportController::class, 'store']);
    });
});

// COMMENTS ROUTES (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('tasks/{task}/comments')->group(function () {
        Route::get('/', [CommentController::class, 'index']);
        Route::get('/{comment}', [CommentController::class, 'show']);
    });
});


// ============= ROUTES INSIDE project.not.locked (Blocked when project is paused/completed) =============

//  Invitations
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::post('/projects/{project}/invitations', [RequestController::class, 'sendInvitation']);
});

// COMMENTS ROUTES (always accessible)
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::prefix('tasks/{task}/comments')->group(function () {
        Route::post('/', [CommentController::class, 'store']);
        Route::put('/{comment}', [CommentController::class, 'update']);
        Route::delete('/{comment}', [CommentController::class, 'destroy']);
    });
});

// PROJECTS ROUTES - Write operations
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
});

// PROJECT USERS ROUTES - Write operations
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::prefix('projects/{project}/users')->group(function () {
        Route::post('/', [ProjectUserController::class, 'addUser']);
        Route::put('/{userId}/role', [ProjectUserController::class, 'updateRole']);
        Route::delete('/{userId}', [ProjectUserController::class, 'removeUser']);
        Route::post('/transfer-ownership/{userId}', [ProjectUserController::class, 'transferOwnership']);
    });

    Route::post('/chains/{chain}/transfer-ownership/{userId}', [ProjectUserController::class, 'transferChainOwnership']);
});

// TASK STATUSES ROUTES (all operations - write)
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::prefix('projects/{project}/statuses')->group(function () {
        Route::post('/', [TaskStatusController::class, 'store']);
        Route::post('/default', [TaskStatusController::class, 'defaultStatuses']);
        Route::post('/reorder', [TaskStatusController::class, 'reorder']);
        Route::put('/{taskStatus}', [TaskStatusController::class, 'update']);
        Route::delete('/{taskStatus}', [TaskStatusController::class, 'destroy']);
    });
});

Route::middleware(['auth:sanctum', 'is.active', 'verified'])->group(function () {
    Route::prefix('projects/{project}/statuses')->group(function () {
        Route::get('/', [TaskStatusController::class, 'index']);
    });
});

Route::get('/my-kanban-tasks', [TaskController::class, 'myKanbanTasks'])
    ->middleware(['auth:sanctum', 'is.active', 'verified']);

// TASKS ROUTES - Write operations
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::prefix('projects/{project}/tasks')->group(function () {
        Route::post('/', [TaskController::class, 'store']);
        Route::post('/reorder', [TaskController::class, 'reorder']);
        Route::put('/{task}', [TaskController::class, 'update']);
        Route::put('/{task}/status', [TaskController::class, 'updateStatus']);
        Route::delete('/{task}', [TaskController::class, 'destroy']);

        Route::post('/{task}/restore', [TaskController::class, 'restoreTask']);
        Route::delete('/{task}/force-delete', [TaskController::class, 'forceDeleteTask']);
        Route::delete('/empty-trash', [TaskController::class, 'emptyTrash']);
    });

    // Manager tasks & subtasks (separate from tasks prefix to avoid variable duplication)
    Route::post('/projects/{project}/groups/{group}/manager-tasks', [TaskController::class, 'storeManagerTask']);
    Route::post('/projects/{project}/groups/{group}/tasks/{parentTask}/subtasks', [TaskController::class, 'storeSubTask']);
    Route::post('/projects/{project}/group-tasks', [TaskController::class, 'storeGroupTask']);
});

// TASK ASSIGNMENTS ROUTES - Write operations
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::prefix('projects/{project}/tasks/{task}/assignments')->group(function () {
        Route::post('/', [TaskAssignmentController::class, 'assign']);
        Route::delete('/{userId}', [TaskAssignmentController::class, 'unassign']);
        Route::put('/{assignmentId}/status', [TaskController::class, 'updateTaskAssignmentStatus']);
    });
});

// TASK DEPENDENCIES ROUTES - Write operations
Route::middleware(['auth:sanctum', 'is.active', 'verified', 'project.not.locked'])->group(function () {
    Route::prefix('projects/{project}/tasks/{task}/dependencies')->group(function () {
        Route::post('/', [TaskDependencyController::class, 'addDependency']);
        Route::delete('/{dependsOnTaskId}', [TaskDependencyController::class, 'removeDependency']);
        Route::put('/{dependsOnTaskId}/type', [TaskDependencyController::class, 'updateDependencyType']);
    });
});
