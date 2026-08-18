<?php

namespace app\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        if (!$project) {
            return false;
        }

        return $project->isOwner($this->user()->id);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|min:2',
            'description' => 'nullable|string|max:1000',
            'avatar' => 'nullable|string|max:255|url',
            'manager_id' => 'required|exists:users,id',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id|different:manager_id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required',
            'name.min' => 'Group name must be at least 2 characters',
            'manager_id.required' => 'You must assign a manager',
            'member_ids.required' => 'You must assign at least one member',
            'member_ids.*.different' => 'Manager cannot be added as a regular member',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'member_ids' => array_unique($this->member_ids ?? [])
        ]);
    }
}
