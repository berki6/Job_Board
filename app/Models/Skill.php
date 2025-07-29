<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the jobs that require this skill.
     */
    public function jobs()
    {
        return $this->belongsToMany(Job::class, 'jobs_skills', 'skill_id', 'job_id');
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
