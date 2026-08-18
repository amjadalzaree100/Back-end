<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class AddGroupMemberRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User does not exist',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => (int) $this->user_id
        ]);
    }
}
