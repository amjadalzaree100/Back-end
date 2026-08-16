<?php

namespace app\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\Validator;

class SendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::find($this->route('project'));
        if (!$project) {
            return false;
        }

        $user = $this->user();
        return $project->isOwner($user->id) || $project->isManager($user->id);
    }

    public function rules(): array
    {
        return [
            'invitee_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:500',
            'role' => 'nullable|in:user,observer',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');
            $inviteeId = $this->invitee_id;
            $invitee = User::find($inviteeId);

            if (!$invitee || !$invitee->profile || !$invitee->profile->allow_invitation_requests) {
                $validator->errors()->add('invitee_id', 'This user does not accept invitations (based on profile settings).');
                return;
            }

            if ($project->isOwner($inviteeId) || $project->hasUser($inviteeId)) {
                $validator->errors()->add('invitee_id', 'User is already a member or owner of this project.');
            }
        });
    }
}
