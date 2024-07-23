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
    Route::get('banners/active', 'AllRole\BannerController@index')->name('index');
    Route::post('logout', 'AuthController@logout');
    Route::get('payment-methods', 'AllRole\PaymentMethodController@index');
    Route::get('admin-fee', "AllRole\PaymentMethodController@adminFee");

    Route::get('categories/{category_uuid}', "AllRole\CategoryController@show");
    Route::get('categories', "AllRole\CategoryController@index");
    Route::get('search', "AllRole\SearchController@index");

    Route::group(['prefix' => 'packages', 'as' => 'packages.',], function () {
        Route::get('popular/{package_type}', 'AllRole\DashboardController@popularPackages')->name('popularPackages');
    });

    // Blog for all role
    Route::group(['prefix' => 'blogs', 'as' => 'blogs.',], function () {
        Route::get('', 'AllRole\BlogController@index')->name('index');
        Route::get('limit/{number_of_limit}', 'AllRole\BlogController@limit')->name('limit');
        Route::get('{blog_uuid}', 'AllRole\BlogController@show')->name('show');
    });
    // end Blog for all role

    Route::group(['prefix' => 'coupons', 'as' => 'coupons.',], function () {
        Route::get('', 'AllRole\CouponController@index')->name('index');
        Route::get('{coupon_uuid}', 'AllRole\CouponController@show')->name('show');
    });

    // Course
    Route::group(['prefix' => 'student', 'as' => 'student.',], function () {
        Route::get('courses/{package_uuid}/{course_uuid}', 'Student\CourseController@show')->name('course.show');
        Route::get('tests/{package_uuid}/{course_uuid}', 'Student\TestController@show')->name('test.show');
        Route::get('packages/all', 'Student\PackageController@allPackage')->name('all.packages');
        Route::get('packages/{package_type}/{uuid}', 'Student\PackageController@show')->name('show');
    });
    // End Course

    // Pauli Test
    Route::group(['prefix'=> 'pauli', 'as'=> 'pauli.',], function() {
        Route::post('assign-to-package', 'Pauli\PackageAssignmentController@assignToPackage')->name('assignToPackage');
        Route::post('user', 'Pauli\UserController@checkEligibility')->name('checkEligiblement');
        Route::post('record', 'Pauli\RecordDataController@postRecord')->name('postRecord');
        Route::post('record-detail', 'Pauli\RecordDetailController@postRecordDetail')->name('postRecordDetail');
        Route::post('leaderboard', 'Pauli\LeaderboardController@getLeaderboard')->name('getLeaderboard');
    });
    // End Pauli Test

    /* KOMEN MULAI DI SINI BUAT TEST API
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
        Route::get('popular/{package_type}', 'Admin\DashboardController@popularPackages')->name('popular');
        // end dashboard

        // Manage Transaction
        Route::group(['prefix' => 'transactions', 'as' => 'transactions.',], function () {
            Route::get('', 'Admin\TransactionController@index');
        });
        // End Manage Transaction

        // Banner management
        Route::group(['prefix' => 'banners', 'as' => 'banners.',], function () {
            Route::get('', 'Admin\BannerController@index')->name('get');
            Route::get('{uuid}', 'Admin\BannerController@show')->name('show');
            Route::post('{uuid}', 'Admin\BannerController@update')->name('update');
            Route::post('', 'Admin\BannerController@store')->name('store');
            Route::delete('{uuid}', 'Admin\BannerController@delete')->name('delete');
        });
        // end Banner management

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
                Route::get('export', 'Admin\UserController@exportStudent')->name('export');
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
            Route::get('published', 'Admin\QuestionController@published')->name('published');
            Route::get('{uuid}', 'Admin\QuestionController@show')->name('show');
            Route::post('', 'Admin\QuestionController@store')->name('store');
            Route::post('duplicate', 'Admin\QuestionController@duplicate')->name('duplicate');
            Route::post('upload/csv', 'Admin\QuestionController@uploadCSV')->name('upload.csv');
            Route::get('download/csv', 'Admin\QuestionController@downloadCSV')->name('download.csv');
            Route::post('{uuid}', 'Admin\QuestionController@update')->name('update');
            Route::delete('{uuid}', 'Admin\QuestionController@delete')->name('delete');
        });
        // end question management

        // test management
        Route::group(['prefix' => 'tests', 'as' => 'tests.',], function () {
            Route::get('', 'Admin\TestController@index')->name('get');
            Route::get('published', 'Admin\TestController@published')->name('published');
            Route::get('{uuid}', 'Admin\TestController@show')->name('show');
            Route::post('', 'Admin\TestController@store')->name('store');
            Route::post('duplicate/{uuid}', 'Admin\TestController@duplicate')->name('duplicate');
            Route::put('{uuid}', 'Admin\TestController@update')->name('update');
            Route::put('add-questions/{uuid}', 'Admin\TestController@addQuestions')->name('update');
            Route::post('update-tags/{uuid}', 'Admin\TestController@updateTag')->name('update.tag');
            Route::get('preview/{uuid}', 'Admin\TestController@preview')->name('preview');
            Route::delete('{uuid}', 'Admin\TestController@delete')->name('delete');
        });
        // end test management

        // tryout management
        Route::group(['prefix' => 'tryouts', 'as' => 'tryouts.',], function () {
            Route::get('', 'Admin\TryoutController@index')->name('get');
            Route::get('{tryout_uuid}', 'Admin\TryoutController@show')->name('show');
            Route::put('{tryout_uuid}', 'Admin\TryoutController@update')->name('update');
            Route::put('add-tests/{tryout_uuid}', 'Admin\TryoutController@addTests')->name('addTests');
            Route::post('', 'Admin\TryoutController@submit')->name('submit');
            Route::delete('{uuid}', 'Admin\TryoutController@delete')->name('delete');
        });
        // end tryout management

        // pretest posttest management
        Route::group(['prefix' => 'pretest-posttests', 'as' => 'pretestPosttest.',], function () {
            Route::get('preview/{uuid}', 'Admin\PretestPosttestController@preview')->name('preview');
        });
        // end pretest posttest management

        // quiz management
        Route::group(['prefix' => 'quizzes', 'as' => 'quiz.',], function () {
            Route::get('preview/{uuid}', 'Admin\QuizController@preview')->name('preview');
        });
        // end quiz management

        // course management
        Route::group(['prefix' => 'courses', 'as' => 'courses.',], function () {
            Route::get('', 'Admin\CourseController@index')->name('get');
            Route::get('published', 'Admin\CourseController@published')->name('published');
            Route::get('{uuid}', 'Admin\CourseController@show')->name('show');
            Route::post('', 'Admin\CourseController@store')->name('store');
            Route::post('duplicate/{uuid}', 'Admin\CourseController@duplicate')->name('duplicate');
            Route::post('{uuid}', 'Admin\CourseController@update')->name('update');
            Route::post('update-tags/{uuid}', 'Admin\CourseController@updateTag')->name('update.tag');
            Route::get('preview/{course_uuid}', 'Admin\CourseController@preview')->name('preview');
            Route::delete('{uuid}', 'Admin\CourseController@delete')->name('delete');
        });
        // end course management

        // course lesson management
        Route::group(['prefix' => 'course-lessons', 'as' => 'lessons.',], function () {
            Route::get('{uuid}', 'Admin\CourseLessonController@show')->name('show');
            Route::post('', 'Admin\CourseLessonController@store')->name('store');
            Route::put('{uuid}', 'Admin\CourseLessonController@update')->name('update');
            Route::delete('{uuid}', 'Admin\CourseLessonController@delete')->name('delete');
        });
        // end course lesson management

        // course lesson lecture management
        Route::group(['prefix' => 'lessons-lectures', 'as' => 'lectures.',], function () {
            Route::get('{uuid}', 'Admin\LessonLectureController@show')->name('show');
            Route::post('', 'Admin\LessonLectureController@store')->name('store');
            Route::post('{uuid}', 'Admin\LessonLectureController@update')->name('update');
            Route::delete('{uuid}', 'Admin\LessonLectureController@delete')->name('delete');
        });
        // end course lesson lecture management

        // packages management
        Route::group(['prefix' => 'packages', 'as' => 'packages.'], function () {
            Route::get('', 'Admin\PackageController@index')->name('get');
            Route::get('{uuid}', 'Admin\PackageController@show')->name('show');
            Route::post('', 'Admin\PackageController@store')->name('store');
            Route::post('duplicate/{uuid}', 'Admin\PackageController@duplicate')->name('duplicate');
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

        // Redeem Coupon
        Route::group(['prefix' => 'coupon', 'as' => 'coupon.',], function () {
            Route::post('redeem', 'Student\CouponController@redeem')->name('redeem');
        });
        // End Redeem Coupon

        // Manage Transaction
        Route::group(['prefix' => 'transactions', 'as' => 'transactions.',], function () {
            Route::get('', 'Student\TransactionController@index');
        });
        // End Manage Transaction

        // Manage Course
        Route::group(['prefix' => 'courses', 'as' => 'course.',], function () {
            Route::get('', 'Student\CourseController@getStudentCourses');
            Route::get('{course_uuid}', 'Student\CourseController@detailPurchasedCourse')->name('detailPurchasedCourse');
            Route::post('', 'Student\CartController@store')->name('store');
            Route::delete('{cart_uuid}', 'Student\CartController@delete')->name('delete');
        });
        // End Manage Course

        // Manage Test
        Route::group(['prefix' => 'tests', 'as' => 'test.',], function () {
        Route::get('', 'Student\TestController@getStudentTests');
        Route::post('submit/{session_uuid}', 'Student\SubmitTestController@submitTest');
            // Route::get('{tryout_uuid}', 'Student\TestController@detailPurchasedTest')->name('detailPurchasedTest');
        });
        // End Manage Test

        // Cart
        Route::group(['prefix' => 'carts', 'as' => 'cart.',], function () {
            Route::get('', 'Student\CartController@index')->name('index');
            Route::post('', 'Student\CartController@store')->name('store');
            Route::delete('{cart_uuid}', 'Student\CartController@delete')->name('delete');
        });
        // End Cart

        // Package
        Route::group(['prefix' => 'packages', 'as' => 'package.',], function () {
            Route::post('buy', 'API\Payment\XenditController@create')->name('buy');
            Route::get('{uuid}', 'Student\PackageController@showDetailPurchasedPackage')->name('show');
            Route::post('expired/{transaction_uuid}', 'API\Payment\XenditController@expired')->name('expired');
            Route::get('', 'Student\PackageController@index')->name('index');

        });
        // End Package

        // Lesson
        Route::group(['prefix' => 'lessons', 'as' => 'lesson.',], function () {
            Route::get('{package_uuid}/{course_uuid}/{lesson_uuid}', 'Student\CourseLessonController@show')->name('show');
        });
        // End Lesson

        // Lecture
        Route::group(['prefix' => 'lectures', 'as' => 'lecture.',], function () {
            Route::get('{lecture_uuid}', 'Student\LessonLectureController@show')->name('show');
        });
        // End Lecture

        // Assignments
        Route::group(['prefix' => 'assignments', 'as' => 'assignment.',], function () {
            Route::get('{assignment_uuid}', 'Student\AssignmentController@index')->name('index');
            Route::post('{assignment_uuid}', 'Student\AssignmentController@store')->name('store');
        });
        // End Assignments

        // Quiz
        Route::group(['prefix' => 'quizzes', 'as' => 'quiz.',], function () {
            Route::get('take-quiz/{quiz_uuid}', 'Student\QuizController@takeQuiz')->name('takeQuiz');
            Route::get('{quiz_uuid}', 'Student\QuizController@index')->name('index');
            Route::get('review/{student_quiz_uuid}', 'Student\QuizController@show')->name('show');
            Route::post('{package_uuid}/{quiz_uuid}', 'Student\QuizController@store')->name('store');
        });
        // End Quiz

        // Pretest posttest
        Route::group(['prefix' => 'pretest-posttests', 'as' => 'pretestPosttest.',], function () {
            Route::get('take-test/{pretest_posttest_uuid}', 'Student\PretestPosttestController@takeTest')->name('takeTest');
            Route::get('{pretest_posttest_uuid}', 'Student\PretestPosttestController@index')->name('index');
            Route::get('review/{student_posttest_uuid}', 'Student\PretestPosttestController@show')->name('show');
            Route::post('{package_uuid}/{quiz_uuid}', 'Student\PretestPosttestController@store')->name('store');
        });
        // End Pretest posttest

        // Tryout
        Route::group(['prefix' => 'tryout', 'as' => 'tryout.',], function () {
            Route::get('take-test/{tryout_uuid}', 'Student\TryoutController@takeTest')->name('takeTest');
            Route::get('{tryout_uuid}', 'Student\TryoutController@index')->name('index');
            Route::get('review/{tryout_uuid}', 'Student\TryoutController@show')->name('show');
            Route::post('{package_uuid}/{quiz_uuid}', 'Student\TryoutController@store')->name('store');
            Route::get('leaderboard/{tryout_uuid}', 'Student\TryoutController@getLeaderboard')->name('getLeaderboard');
            Route::get('analytics/{tryout_uuid}', 'Student\TryoutController@getUserTryoutAnalytic')->name('getUserTryoutAnalytic');
        });
        // End Tryout

        Route::post('session/{session_uuid}', 'Student\SessionController@update')->name('test.session');
    });

    Route::group(['middleware' => ['auth', 'instructor', 'verified'], 'prefix' => 'instructor', 'as' => 'instructor.',], function () {
        // Dashboard
        Route::get('', 'Instructor\DashboardController@index')->name('get');
        // end dashboard

        // Manage Course
        Route::group(['prefix' => 'courses', 'as' => 'course.',], function () {
            Route::get('', 'Instructor\CourseController@index');
            Route::get('{course_uuid}', 'Instructor\CourseController@show')->name('show');
        });
        // End Manage Course

        // Lecture
        Route::group(['prefix' => 'lectures', 'as' => 'lecture.',], function () {
            Route::get('{lecture_uuid}', 'Instructor\LessonLectureController@show')->name('show');
        });
        // End Lecture

        // Manage Assignment
        Route::group(['prefix' => 'assignments', 'as' => 'assignments.',], function () {
            Route::get('{assignment_uuid}', 'Instructor\AssignmentController@index');
            Route::post('{student_assignment_uuid}', 'Instructor\AssignmentController@review')->name('review');
        });
        // End Manage Assignment


    });
    // */
    // SAMPAI SINI
});
