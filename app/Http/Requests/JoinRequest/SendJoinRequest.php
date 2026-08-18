<?php

namespace App\Http\Requests\JoinRequest;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;

class SendJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        if (!$project instanceof Project) {
            return false;
        }

        if (in_array($project->status, ['completed', 'paused'])) {
            return false;
        }

        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($project->isOwner($user->id) || $project->hasUser($user->id)) {
            return false;
        }

        return (bool) $project->allow_join_requests;
    }

    public function rules(): array
    {
        return [
            'message' => 'nullable|string|max:500',
        ];
    }
}
