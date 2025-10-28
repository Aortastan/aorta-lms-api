<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDurationPerQuestionToTryoutSegmentTestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tryout_segment_tests', function (Blueprint $table) {
            //
            $table->integer('duration_per_question')->comment('in seconds')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tryout_segment_test', function (Blueprint $table) {
            //
        });
    }
}
