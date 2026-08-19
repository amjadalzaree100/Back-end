<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'nullable|string|max:50|unique:profiles,phone',
            'bio' => 'nullable|string|max:1000',
            'job_title' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'skills.*.name' => 'required|string|max:100',
            'skills.*.rating' => 'sometimes|integer|min:1|max:10',
            'avatar' => 'nullable|string|max:255|url',
            'location' => 'nullable|string|max:255',
            'alternative_email' => 'nullable|string|email|max:255',

            // Social links
            'twitter_url' => 'nullable|string|max:255|url',
            'facebook_url' => 'nullable|string|max:255|url',
            'instagram_url' => 'nullable|string|max:255|url',
            'youtube_url' => 'nullable|string|max:255|url',
            'github_url' => 'nullable|string|max:255|url',
            'portfolio_url' => 'nullable|string|max:255|url',
            'linkedin_url' => 'nullable|string|max:255|url',
            'cv_url' => 'nullable|string|max:255|url',

            // Preferences
            'language' => ['nullable', Rule::in(['ar', 'en'])],
            'theme' => ['nullable', Rule::in(['light', 'dark'])],

            // Privacy settings
            'is_public' => 'nullable|boolean',
            'allow_messages' => 'nullable|boolean',
            'allow_invitation_requests' => 'nullable|boolean',

            // Stats
            'projects_count' => 'nullable|integer|min:0',
            'tasks_completed' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.max' => 'Phone number must not exceed 50 characters',
            'bio.max' => 'Bio must not exceed 1000 characters',
            'job_title.max' => 'Job title must not exceed 255 characters',
            'skills.array' => 'Skills must be an array',
            'skills.*.string' => 'Each skill must be a string',
            'skills.*.max' => 'Each skill must not exceed 100 characters',
            'avatar.url' => 'Avatar URL must be a valid URL',
            'location.max' => 'Location must not exceed 255 characters',
            'twitter_url.url' => 'Twitter URL must be a valid URL',
            'facebook_url.url' => 'Facebook URL must be a valid URL',
            'instagram_url.url' => 'Instagram URL must be a valid URL',
            'youtube_url.url' => 'YouTube URL must be a valid URL',
            'github_url.url' => 'GitHub URL must be a valid URL',
            'portfolio_url.url' => 'Portfolio URL must be a valid URL',
            'linkedin_url.url' => 'LinkedIn URL must be a valid URL',
            'cv_url.url' => 'CV URL must be a valid URL',
            'language.in' => 'Language must be either ar or en',
            'theme.in' => 'Theme must be either light or dark',
        ];
    }
}
