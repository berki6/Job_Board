<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoApplyLog extends Model
{
    protected $fillable = ['user_id', 'job_id', 'status', 'reason'];
}
