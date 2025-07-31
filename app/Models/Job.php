<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Job extends Model
{
    use HasFactory, Searchable;

    protected $table = 'jobs_listing';

    protected $fillable = [
        'user_id',
        'job_type_id',
        'category_id',
        'title',
        'description',
        'location',
        'salary_min',
        'salary_max',
        'remote',
        'status',
        'is_open',
        'is_featured',
        'application_method',
        'external_link',
        'slug',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'is_open' => $this->is_open, // Include in search for filtering
        ];
    }

    /**
     * Generate a unique slug for the job title.
     */
    protected function generateUniqueSlug($title)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($job) {
            $job->slug = $job->generateUniqueSlug($job->title);
            $job->is_open = true; // Default to open
        });

        static::updating(function ($job) {
            if ($job->isDirty('title')) {
                $job->slug = $job->generateUniqueSlug($job->title);
            }
        });
    }

    /**
     * Get the company that posted the job.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function savedBy()
    {
        return $this->belongsToMany(User::class, 'saved_jobs');
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

    public function rejections()
    {
        return $this->hasMany(JobRejection::class);
    }
}
