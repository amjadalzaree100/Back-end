<?php

namespace App\Http\Requests\projectReport;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reported_project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    $project = \App\Models\Project::find($value);
                    if ($project && $project->created_by === $this->user()->id) {
                        $fail('You cannot report your own project.');
                    }
                },
            ],
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reported_project_id.required' => 'Please specify which project you are reporting',
            'reported_project_id.exists' => 'The reported project does not exist',
            'reason.required' => 'Please provide a reason for this report',
            'reason.max' => 'Reason must not exceed 255 characters',
            'details.max' => 'Details must not exceed 1000 characters',
        ];
    }
}
