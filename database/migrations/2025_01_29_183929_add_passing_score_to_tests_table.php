<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPassingScoreToTestsTable extends Migration
{
    public function up()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('passing_score')->nullable()->after('student_title_display')->comment('Passing score for the test');
        });
    }

    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn('passing_score');
        });
    }
}
