<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePretestPosttestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pretest_posttests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('course_uuid');
            $table->string('test_uuid');
            $table->string('duration');
            $table->integer('max_attempt');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pretest_posttests');
    }
}
