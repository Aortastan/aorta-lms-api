<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('email/verify/{id}', 'Auth\VerificationController@verify')->name('verification.verify');
Route::get('email/resend', 'Auth\VerificationController@resend')->name('verification.resend');

Route::group(['middleware' => 'api', 'prefix' => 'v1', 'as' => 'api.',], function () {
    Route::post('payments/webhook/xendit/paid', 'API\Payment\XenditController@webhook')->name('xendit.webhook');
    Route::post('authenticate', 'AuthController@authenticate')->name('authenticate');
    Route::post('register', 'AuthController@register')->name('register');
    Route::post('forgot-password', 'AuthController@forgotPassword')->name('forgotPassword');
    Route::post('reset-password', 'AuthController@resetPassword')->name('resetPassword');
    Route::post('logout', 'AuthController@logout');

    Route::group(['middleware' => ['auth', 'verified']], function () {
       // profile management
       Route::group(['prefix' => 'profile', 'as' => 'profile.',], function () {
            Route::get('', 'AllRole\ProfileController@index')->name('get');
            Route::post('', 'AllRole\ProfileController@update')->name('update');
            Route::put('change-password', 'AllRole\ProfileController@changePassword')->name('changePassword');
        });
        // end profile management
    });

    Route::group(['middleware' => ['auth', 'admin', 'verified'], 'prefix' => 'admin', 'as' => 'admin.',], function () {
        // Dashboard
        Route::get('', 'Admin\DashboardController@index')->name('get');
        // end dashboard

        // User management
        Route::group(['prefix' => 'users', 'as' => 'users.',], function () {
            Route::group(['prefix' => 'admin', 'as' => 'admin.',], function () {
                Route::get('', 'Admin\UserController@indexAdmin')->name('get');
                Route::put('{uuid}', 'Admin\UserController@updateAdmin')->name('update');
                Route::post('', 'Admin\UserController@storeAdmin')->name('store');
            });
            Route::group(['prefix' => 'instructor', 'as' => 'instructor.',], function () {
                Route::get('', 'Admin\UserController@indexInstructor')->name('get');
                Route::put('{uuid}', 'Admin\UserController@updateInstructor')->name('update');
                Route::post('', 'Admin\UserController@storeInstructor')->name('store');
            });
            Route::group(['prefix' => 'student', 'as' => 'student.',], function () {
                Route::get('', 'Admin\UserController@indexStudent')->name('get');
                Route::put('{uuid}', 'Admin\UserController@updateStudent')->name('update');
            });

            Route::delete('{uuid}', 'Admin\UserController@delete')->name('delete');
        });
        // end user management

        // category management
        Route::group(['prefix' => 'categories', 'as' => 'categories.',], function () {
            Route::get('', 'Admin\CategoryController@index')->name('get');
            Route::get('{uuid}', 'Admin\CategoryController@show')->name('show');
            Route::post('', 'Admin\CategoryController@store')->name('store');
            Route::put('{uuid}', 'Admin\CategoryController@update')->name('update');
            Route::delete('{uuid}', 'Admin\CategoryController@delete')->name('delete');
        });
        // end category management

        // tag management
        Route::group(['prefix' => 'tags', 'as' => 'tags.',], function () {
            Route::get('', 'Admin\TagController@index')->name('get');
            Route::get('{uuid}', 'Admin\TagController@show')->name('show');
            Route::post('', 'Admin\TagController@store')->name('store');
            Route::put('{uuid}', 'Admin\TagController@update')->name('update');
            Route::delete('{uuid}', 'Admin\TagController@delete')->name('delete');
        });
        // end tag management

        // subject management
        Route::group(['prefix' => 'subjects', 'as' => 'subjects.',], function () {
            Route::get('', 'Admin\SubjectController@index')->name('get');
            Route::get('{uuid}', 'Admin\SubjectController@show')->name('show');
            Route::post('', 'Admin\SubjectController@store')->name('store');
            Route::put('{uuid}', 'Admin\SubjectController@update')->name('update');
            Route::delete('{uuid}', 'Admin\SubjectController@delete')->name('delete');
        });
        // end subject management

        // blog management
        Route::group(['prefix' => 'blogs', 'as' => 'blogs.',], function () {
            Route::get('', 'Admin\BlogController@index')->name('get');
            Route::get('{uuid}', 'Admin\BlogController@show')->name('show');
            Route::post('', 'Admin\BlogController@store')->name('store');
            Route::post('{uuid}', 'Admin\BlogController@update')->name('update');
            Route::delete('{uuid}', 'Admin\BlogController@delete')->name('delete');
        });
        // end blog management

        // question management
        Route::group(['prefix' => 'questions', 'as' => 'questions.',], function () {
            Route::get('', 'Admin\QuestionController@index')->name('get');
            Route::get('{detail}', 'Admin\QuestionController@show')->name('show'); // ambil data, bisa ambil semua question berdasarkan question_type, jika diluar question type, bisa dipakai untuk mengambil data berdasarkan uuid
            Route::post('', 'Admin\QuestionController@store')->name('store');
            Route::post('upload/csv', 'Admin\QuestionController@uploadCSV')->name('upload.csv');
            Route::post('{uuid}', 'Admin\QuestionController@update')->name('update');
            Route::delete('{uuid}', 'Admin\QuestionController@delete')->name('delete');
        });
        // end question management

        // test management
        Route::group(['prefix' => 'tests', 'as' => 'tests.',], function () {
            Route::get('', 'Admin\TestController@index')->name('get');
            Route::get('{uuid}', 'Admin\TestController@show')->name('show'); // if uuid is quiz / tryout, its return list question quiz / tryout, if its not quiz/tryout, return spesific test
            Route::post('', 'Admin\TestController@store')->name('store');
            Route::put('{uuid}', 'Admin\TestController@update')->name('update');
            Route::put('add-questions/{uuid}', 'Admin\TestController@addQuestions')->name('update');
            Route::post('update-tags/{uuid}', 'Admin\TestController@updateTag')->name('update.tag');
        });
        // end test management

        // course management
        Route::group(['prefix' => 'courses', 'as' => 'courses.',], function () {
            Route::get('', 'Admin\CourseController@index')->name('get');
            Route::get('{uuid}', 'Admin\CourseController@show')->name('show');
            Route::post('', 'Admin\CourseController@store')->name('store');
            Route::post('{uuid}', 'Admin\CourseController@update')->name('update');
            Route::post('update-tags/{uuid}', 'Admin\CourseController@updateTag')->name('update.tag');
        });
        // end course management

        // course lesson management
        Route::group(['prefix' => 'course-lessons', 'as' => 'lessons.',], function () {
            Route::get('{uuid}', 'Admin\CourseLessonController@show')->name('show');
            Route::post('', 'Admin\CourseLessonController@store')->name('store');
            Route::put('{uuid}', 'Admin\CourseLessonController@update')->name('update');
        });
        // end course lesson management

        // course lesson lecture management
        Route::group(['prefix' => 'lessons-lectures', 'as' => 'lectures.',], function () {
            Route::get('{uuid}', 'Admin\LessonLectureController@show')->name('show');
            Route::post('', 'Admin\LessonLectureController@store')->name('store');
            Route::post('{uuid}', 'Admin\LessonLectureController@update')->name('update');
        });
        // end course lesson lecture management

        // packages management
        Route::group(['prefix' => 'packages', 'as' => 'packages.'], function () {
            Route::get('', 'Admin\PackageController@index')->name('get');
            Route::get('{uuid}', 'Admin\PackageController@show')->name('show');
            Route::post('', 'Admin\PackageController@store')->name('store');
            Route::post('{type}/{uuid}', 'Admin\PackageController@update')->name('update');
            Route::post('lists/{type}/{uuid}', 'Admin\PackageController@packageLists')->name('lists');
        });
        // end packages management

        // payment gateway management
        Route::group(['prefix' => 'payment-gateway', 'as' => 'payments.',], function () {
            Route::get('', 'Admin\PaymentGatewayController@index')->name('get');
            Route::get('{uuid}', 'Admin\PaymentGatewayController@show')->name('show');
            Route::post('', 'Admin\PaymentGatewayController@store')->name('store');
            Route::put('{uuid}', 'Admin\PaymentGatewayController@update')->name('update');
            Route::delete('{uuid}', 'Admin\PaymentGatewayController@delete')->name('delete');
        });
        // end payment gateway management

        // coupon management
        Route::group(['prefix' => 'coupons', 'as' => 'coupons.',], function () {
            Route::get('', 'Admin\CouponController@index')->name('get');
            Route::get('{uuid}', 'Admin\CouponController@show')->name('show');
            Route::post('', 'Admin\CouponController@store')->name('store');
            Route::put('{uuid}', 'Admin\CouponController@update')->name('update');
            Route::delete('{uuid}', 'Admin\CouponController@delete')->name('delete');
        });
        // end coupon management
    });

    Route::group(['middleware' => ['auth', 'student', 'verified'], 'prefix' => 'student', 'as' => 'student.',], function () {
        // Dashboard
        Route::get('', 'Student\DashboardController@index')->name('get');
        // end dashboard

        // Package
        Route::group(['prefix' => 'carts', 'as' => 'cart.',], function () {
            Route::get('', 'Student\CartController@index')->name('index');
            Route::post('', 'Student\CartController@store')->name('store');
            Route::delete('{cart_uuid}', 'Student\CartController@delete')->name('delete');
        });
        // End Package

        // Package
        Route::group(['prefix' => 'packages', 'as' => 'package.',], function () {
            Route::post('buy', 'API\Payment\XenditController@create')->name('buy');
            Route::get('', 'Student\PackageController@index')->name('index');
            Route::get('{package_type}/{uuid}', 'Student\PackageController@show')->name('show');
        });
        // End Package

        // Course
        Route::group(['prefix' => 'courses', 'as' => 'course.',], function () {
            Route::get('{package_uuid}/{course_uuid}', 'Student\CourseController@show')->name('show');
        });
        // End Course

        // Lesson
        Route::group(['prefix' => 'lessons', 'as' => 'lesson.',], function () {
            Route::get('{package_uuid}/{course_uuid}/{lesson_uuid}', 'Student\CourseLessonController@show')->name('show');
        });
        // End Lesson

        // Lecture
        Route::group(['prefix' => 'lectures', 'as' => 'lecture.',], function () {
            Route::get('{package_uuid}/{course_uuid}/{lesson_uuid}/{lecture_uuid}', 'Student\LessonLectureController@show')->name('show');
        });
        // End Lecture

        // Assignments
        Route::group(['prefix' => 'assignments', 'as' => 'assignment.',], function () {
            Route::get('{package_uuid}/{assignment_uuid}', 'Student\AssignmentController@index')->name('index');
            Route::post('{package_uuid}/{assignment_uuid}', 'Student\AssignmentController@store')->name('store');
        });
        // End Assignments

        // Quiz
        Route::group(['prefix' => 'quizzes', 'as' => 'quiz.',], function () {
            Route::get('detail-student-quiz/{student_quiz_uuid}', 'Student\QuizController@show')->name('show');
            Route::get('take-quiz/{package_uuid}/{quiz_uuid}', 'Student\QuizController@takeQuiz')->name('takeQuiz');
            Route::get('{package_uuid}/{quiz_uuid}', 'Student\QuizController@index')->name('index');
            Route::post('{package_uuid}/{quiz_uuid}', 'Student\QuizController@store')->name('store');
        });
        // End Quiz
    });
});
