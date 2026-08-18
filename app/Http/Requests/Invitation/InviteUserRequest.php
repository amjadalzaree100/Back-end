<?php

namespace App\Http\Requests\Invitation;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'role' => 'nullable|in:user,observer', // فقط user أو observer
            'message' => 'nullable|string|max:500',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $project = Project::find($this->project_id);
            $invitee = $this->route('profile')->user;

            if (!$project) {
                $validator->errors()->add('project_id', 'Project not found.');
                return;
            }

            if (!$project->isOwner($user->id) && !$project->isManager($user->id)) {
                $validator->errors()->add('project_id', 'You do not have permission to invite users to this project.');
                return;
            }

            if ($project->isOwner($invitee->id) || $project->hasUser($invitee->id)) {
                $validator->errors()->add('invitee', 'User is already a member or owner of this project.');
                return;
            }

            if (!$invitee->profile || !$invitee->profile->allow_invitation_requests) {
                $validator->errors()->add('invitee', 'This user does not accept invitations.');
                return;
            }

            $existing = \App\Models\Request::where('project_id', $project->id)
                ->where('receiver_id', $invitee->id)
                ->where('type', 'invitation')
                ->where('status', 'pending')
                ->exists();

            if ($existing) {
                $validator->errors()->add('project_id', 'An invitation is already pending for this user to this project.');
            }
        });
    }
}
