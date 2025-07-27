<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $table = 'jobs_listing';

    protected $fillable = [
        'company_id',
        'category_id',
        'job_type_id',
        'title',
        'description',
        'location',
        'salary',
        'salary_min',
        'salary_max',
        'status',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];

    /**
     * Get the company that posted the job.
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    /**
     * Get the category of the job.
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the applications for this job.
     */
    public function applications()
    {
        return $this->hasMany(Application::class, 'job_id');
    }

    /**
     * Get the skills required for this job.
     */
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'jobs_skills', 'job_id', 'skill_id');
    }

    /**
     * Get the job type for this job.
     */
    public function jobType()
    {
        return $this->belongsTo(JobType::class, 'job_type_id');
    }
}
