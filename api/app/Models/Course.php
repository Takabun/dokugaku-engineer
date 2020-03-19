<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $guarded = [];

    public function parts()
    {
        return $this->hasMany('App\Models\Part');
    }

    public function course_users()
    {
        return $this->hasMany('App\Models\CourseUser');
    }

    public function withCourses()
    {
        return $this->load(['parts.lessons.lectures' => function ($query) {
            $query->where('lectures.public', 1);
        }]);
    }
}
