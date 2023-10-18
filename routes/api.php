<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    Route::post('authenticate', 'AuthController@authenticate')->name('authenticate');
    Route::post('register', 'AuthController@register')->name('register');
    Route::post('logout', 'AuthController@logout');

    Route::group(['middleware' => ['auth', 'admin', 'verified'], 'prefix' => 'admin', 'as' => 'admin.',], function () {
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

            Route::delete('users/{uuid}', 'Admin\UserController@delete')->name('delete');
        });
        // end user management

        // question management
        Route::group(['prefix' => 'questions', 'as' => 'questions.',], function () {
            Route::get('', 'Admin\QuestionController@index')->name('get');
            Route::get('{detail}', 'Admin\QuestionController@show')->name('get.detail'); // ambil data, bisa ambil semua question berdasarkan question_type, jika diluar question type, bisa dipakai untuk mengambil data berdasarkan uuid
            Route::post('', 'Admin\QuestionController@store')->name('store');
            Route::put('{uuid}', 'Admin\QuestionController@update')->name('update');
            Route::delete('{uuid}', 'Admin\QuestionController@delete')->name('delete');
        });
        // end question management

    });
});
