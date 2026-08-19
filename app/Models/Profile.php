<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    protected $fillable = [
        'user_id',
        'phone',
        'bio',
        'job_title',
        'skills',
        'avatar',
        'location',
        'alternative_email',
        'twitter_url',
        'facebook_url',
        'instagram_url',
        'youtube_url',
        'github_url',
        'portfolio_url',
        'linkedin_url',
        'cv_url',
        'language',
        'theme',
        'is_public',
        'allow_messages',
        'allow_invitation_requests',
        'projects_count',
        'tasks_completed',
        'report_count',
    ];

    protected $casts = [
        'skills' => 'array',
        'is_public' => 'boolean',
        'allow_messages' => 'boolean',
        'allow_invitation_requests' => 'boolean',
        'projects_count' => 'integer',
        'tasks_completed' => 'integer',
        'report_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'language' => 'ar',
        'theme' => 'light',
        'is_public' => false,
        'allow_messages' => false,
        'allow_invitation_requests' => false,
        'projects_count' => 0,
        'tasks_completed' => 0,
        'report_count' => 0,
        'skills' => '[]',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function addSkill(string $skillName, ?int $rating = null): bool
    {
        $rating = min(10, max(1, $rating ?? 5));
        $skills = $this->skills;        
        $skillName = trim($skillName);

        foreach ($skills as $skill) {
            if (strcasecmp($skill['name'], $skillName) === 0) {
                return false;
            }
        }

        $skills[] = ['name' => $skillName, 'rating' => $rating];

        return (bool) $this->update(['skills' => $skills]);
    }
    public function updateSkillRating(string $skillName, int $rating): bool
    {
        $skills = $this->skills;
        $skillName = trim($skillName);
        $rating = min(10, max(1, $rating));

        foreach ($skills as $index => $skill) {
            if (strcasecmp($skill['name'], $skillName) === 0) {
                $skills[$index]['rating'] = $rating;
                $this->skills = $skills;
                return (bool) $this->save();
            }
        }

        return false;
    }

    public function removeSkill(string $skillName): bool
    {
        $skills = $this->skills;
        $skillName = trim($skillName);

        foreach ($skills as $index => $skill) {
            if (strcasecmp($skill['name'], $skillName) === 0) {
                unset($skills[$index]);
                $this->skills = array_values($skills);
                return (bool) $this->save();
            }
        }

        return false;
    }


}
