<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Submit a report against a user
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $reportedUser = User::findOrFail($request->reported_user_id);

            if ($user->hasReported($reportedUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this user'
                ], 409);
            }

            $report = Report::create([
                'reporter_id' => $user->id,
                'reported_user_id' => $reportedUser->id,
                'reason' => $request->reason,
                'details' => $request->details,
            ]);

            if ($reportedUser->profile) {
                $reportedUser->profile->increment('report_count');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully.',
                'data' => ['report_id' => $report->id]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User report failed: ' . $e->getMessage(), [
                'reporter_id' => $request->user()->id,
                'reported_user_id' => $request->reported_user_id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting the report. Please try again later.'
            ], 500);
        }
    }
}
