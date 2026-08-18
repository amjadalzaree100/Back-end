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
