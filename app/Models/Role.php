<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const SEEKER = 'JOB_SEEKER';

    public const RECRUITER = 'RECRUITER';

    public const ADMIN = 'SUPER_ADMIN';

    protected $fillable = ['name'];
}
