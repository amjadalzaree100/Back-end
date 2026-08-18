<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $note = $request->user()->note;

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new NoteResource($note)
        ]);
    }

    public function write(UpdateNoteRequest $request): JsonResponse
    {
        $note = $request->user()->note;

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        $note->update($request->only(['title', 'content', 'color']));

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => new NoteResource($note)
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $note = $request->user()->note;

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found'
            ], 404);
        }

        $note->update([
            'content' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note content cleared successfully'
        ]);
    }
}
