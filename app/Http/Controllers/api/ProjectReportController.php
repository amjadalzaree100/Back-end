<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectReport\StoreProjectReportRequest;
use App\Models\Project;
use App\Models\ProjectReport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectReportController extends Controller
{
    /**
     * Store a report against a project.
     */
    public function store(StoreProjectReportRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            $reportedProject = Project::findOrFail($request->reported_project_id);

            // Check if user already reported this project
            $existingReport = ProjectReport::where('reporter_id', $user->id)
                ->where('reported_project_id', $reportedProject->id)
                ->exists();

            if ($existingReport) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this project.',
                ], 409);
            }

            $report = ProjectReport::create([
                'reporter_id' => $user->id,
                'reported_project_id' => $reportedProject->id,
                'reason' => $request->reason,
                'details' => $request->details,
                'status' => 'open', // Default status
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Project report submitted successfully. Our team will review it.',
                'data' => [
                    'report_id' => $report->id,
                    'reported_project' => $reportedProject->name,
                    'reason' => $report->reason,
                ]
            ], 201);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Project not found: ' . $e->getMessage(), [
                'project_id' => $request->reported_project_id,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'The reported project does not exist.',
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Project report creation failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'reported_project_id' => $request->reported_project_id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting the report. Please try again later.',
            ], 500);
        }
    }
}
