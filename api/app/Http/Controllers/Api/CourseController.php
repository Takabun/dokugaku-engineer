<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\TakingCourse;
use App\Http\Resources\Course\Course as CourseResource;
use App\Http\Resources\Course\CourseWithLecture as CourseWithLectureResource;

/**
 * @group 3. Course
 */
class CourseController extends ApiController
{
    /**
     * コース一覧を取得
     *
     * @responsefile responses/course.index.json
     *
     * @return CourseResourceCollection
     *
     */
    public function index(Request $request)
    {
        $courses = Course::all();
        return CourseResource::collection($courses);
    }

    /**
     * コースとレクチャー一覧を取得
     *
     * @responsefile responses/course.getAllLectures.json
     *
     * @return CourseWithLectureResourceCollection
     *
     */
    public function getAllLectures(Request $request)
    {
        $course = Course::with('parts.lessons.lectures')->get();
        return CourseWithLectureResource::collection($course);
    }

    /**
     * レクチャーを取得
     *
     * @bodyParam name string required Course name. Example: serverside
     *
     * @responsefile responses/course.getLectures.json
     *
     * @param string $slug
     * @return CourseWithLectureResource
     */
    public function getLectures(Request $request, $name)
    {
        $user_id = $request['user_id'];
        $course = Course::where('name', $name)->first();
        if (TakingCourse::doesntExist($user_id, $course->id)) {
            return $this->respondNotFound('Taking course not found');
        }

        $course->withCourses();
        return new CourseWithLectureResource($course);
    }

    public function test(Request $request, $name)
    {
        $course = Course::where('name', $name)->first();
        $course->withCourses(1);
        return new CourseWithLectureResource($course);
    }
}
