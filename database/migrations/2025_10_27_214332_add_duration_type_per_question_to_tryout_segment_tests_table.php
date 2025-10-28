<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDurationTypePerQuestionToTryoutSegmentTestsTable extends Migration
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
            $table->string('duration_type')->enum('per_question', 'per_test')->default('per_test');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tryout_segment_tests', function (Blueprint $table) {
            //
        });
    }
}
