<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoApplyLog extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'job_id', 'status', 'reason'];
}
