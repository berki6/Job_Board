<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoApplyPreference extends Model
{
    protected $fillable = [
        'user_id',
        'auto_apply_enabled',
        'job_titles',
        'locations',
        'salary_min',
        'salary_max',
        'job_types',
        'cover_letter_template'
    ];

    protected $casts = [
        'auto_apply_enabled' => 'boolean',
        'job_titles' => 'array',
        'locations' => 'array',
        'job_types' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
