<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profile = $this->route('profile');

        return [
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('profiles', 'phone')->ignore($profile->id),
            ],
            'bio' => 'nullable|string|max:1000',
            'job_title' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'skills.*.name' => 'required_with:skills|string|max:100',
            'skills.*.rating' => 'nullable|integer|min:1|max:10',
            'avatar' => 'nullable|string|max:255|url',
            'location' => 'nullable|string|max:255',
            'alternative_email' => 'nullable|string|email|max:255',

            // Social links
            'twitter_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'youtube_url' => 'nullable|url|max:255',
            'github_url' => 'nullable|url|max:255',
            'portfolio_url' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'cv_url' => 'nullable|max:255',

            // Preferences
            'language' => ['nullable', Rule::in(['ar', 'en'])],
            'theme' => ['nullable', Rule::in(['light', 'dark'])],

            // Privacy settings
            'is_public' => 'nullable|boolean',
            'allow_messages' => 'nullable|boolean',
            'allow_invitation_requests' => 'nullable|boolean',

        ];
    }
    public function messages(): array
    {
        return [
            'phone.unique' => 'Phone number is already in use',
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
            'projects_count.integer' => 'Projects count must be an integer',
            'projects_count.min' => 'Projects count must be at least 0',
            'tasks_completed.integer' => 'Tasks completed must be an integer',
            'tasks_completed.min' => 'Tasks completed must be at least 0',
        ];
    }
}
