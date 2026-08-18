<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class TransferManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $user = $this->user();

        if (!$group) {
            return false;
        }

        return $group->project->isOwner($user->id) || $group->isManager($user->id);
    }

    public function rules(): array
    {
        return [
            'new_manager_id' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'new_manager_id.required' => 'You must select a new manager',
            'new_manager_id.exists' => 'Selected user does not exist',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $group = $this->route('group');
            $newManagerId = $this->new_manager_id;

            if ($group && !$group->isMember($newManagerId)) {
                $validator->errors()->add(
                    'new_manager_id',
                    'The new manager must be a member of this group first'
                );
            }

            if ($group && $group->manager_id === $newManagerId) {
                $validator->errors()->add(
                    'new_manager_id',
                    'This user is already the manager'
                );
            }
        });
    }
}
