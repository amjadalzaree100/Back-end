<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2|max:100',

            // 'limit' is intentionally not validated as 'integer' so the
            // string sentinel 'all' and the legacy '0' are both accepted.
            'limit' => 'nullable',

            // ---- user filters (6) ----
            'job_title' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:100',
            'created_from' => 'nullable|date_format:Y-m-d',
            'created_to' => 'nullable|date_format:Y-m-d',
            'is_public_profile' => 'nullable|boolean',
            'skills' => 'nullable|string|max:200',

            // ---- project filters (6) ----
            'visibility' => 'nullable|string|in:private,public',
            'status' => 'nullable|string|in:active,paused,completed',
            'start_date_from' => 'nullable|date_format:Y-m-d',
            'start_date_to' => 'nullable|date_format:Y-m-d',
            'end_date_from' => 'nullable|date_format:Y-m-d',
            'end_date_to' => 'nullable|date_format:Y-m-d',
            'created_by' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Search query is required',
            'q.min' => 'Please enter at least 2 characters to search',
            'q.max' => 'Search query cannot exceed 100 characters',

            'job_title.max' => 'job_title must be at most 100 characters',
            'location.max' => 'location must be at most 100 characters',
            'created_from.date_format' => 'created_from must be a date in Y-m-d format',
            'created_to.date_format' => 'created_to must be a date in Y-m-d format',
            'is_public_profile.boolean' => 'is_public_profile must be a boolean',
            'skills.max' => 'skills must be at most 200 characters',

            'visibility.in' => 'visibility must be one of: private, public',
            'status.in' => 'status must be one of: active, paused, completed',
            'start_date_from.date_format' => 'start_date_from must be a date in Y-m-d format',
            'start_date_to.date_format' => 'start_date_to must be a date in Y-m-d format',
            'end_date_from.date_format' => 'end_date_from must be a date in Y-m-d format',
            'end_date_to.date_format' => 'end_date_to must be a date in Y-m-d format',
            'created_by.integer' => 'created_by must be an integer',
            'created_by.min' => 'created_by must be a positive integer',
        ];
    }
}
