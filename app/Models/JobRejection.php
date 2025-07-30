<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRejection extends Model
{
    protected $fillable = ['job_id', 'reason'];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}
