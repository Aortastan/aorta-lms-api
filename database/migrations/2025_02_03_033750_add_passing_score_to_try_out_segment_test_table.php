<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPassingScoreToTryOutSegmentTestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tryout_segment_tests', function (Blueprint $table) {
            $table->integer('passing_score')->nullable()->comment('Passing score for the test');
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
        $table->dropColumn('passing_score');
        });
    }
}
