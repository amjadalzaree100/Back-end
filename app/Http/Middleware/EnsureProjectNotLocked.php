<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureProjectNotLocked
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->route('project');

        if (!$project) {
            return $next($request);
        }

        // Check if project is locked (owner suspended OR admin suspended)
        if ($project->isLocked()) {
            // Allow status changes (owner can still change status when locked)
            $isStatusApi = $request->isMethod('patch') &&
                $request->route()->getName() === 'projects.update.status';

            if (!$isStatusApi) {
                $lockReason = $project->owner_suspended 
                    ? 'project owner has been suspended' 
                    : 'project has been suspended by admin';

                return response()->json([
                    'success' => false,
                    'message' => "This project is locked because {$lockReason}. Only viewing is allowed.",
                ], 403);
            }
        }

        // Check traditional locked states (completed/paused)
        if (in_array($project->status, ['completed', 'paused'])) {
            $isStatusApi = $request->isMethod('patch') &&
                $request->route()->getName() === 'projects.update.status';

            if (!$isStatusApi) {
                return response()->json([
                    'success' => false,
                    'message' => "Project is '{$project->status}'. Only status can be changed."
                ], 403);
            }
        }

        return $next($request);
    }
}