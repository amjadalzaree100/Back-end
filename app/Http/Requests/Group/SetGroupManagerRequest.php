<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class SetGroupManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $user = $this->user();

        if (!$group) {
            return false;
        }

        // Only project owner, project manager, or current group manager can set/change manager
        return $group->project->isOwner($user->id)
            || $group->project->isManager($user->id)
            || $group->isManager($user->id);
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'Selected user does not exist',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $group = $this->route('group');
            $userId = $this->user_id;

            if ($group && !$group->isMember($userId)) {
                $validator->errors()->add(
                    'user_id',
                    'User must be a member of this group first'
                );
            }
        });
    }
}