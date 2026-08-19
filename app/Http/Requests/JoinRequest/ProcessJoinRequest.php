<?php

namespace App\Http\Requests\JoinRequest;

use Illuminate\Foundation\Http\FormRequest;

class ProcessJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        if (!$project) {
            return false;
        }

        $user = $this->user();
        return $project->isOwner($user->id) || $project->isManager($user->id);
    }
    public function rules(): array
    {
        return [
            'role' => 'sometimes|in:user,manager,observer',
        ];
    }
}
