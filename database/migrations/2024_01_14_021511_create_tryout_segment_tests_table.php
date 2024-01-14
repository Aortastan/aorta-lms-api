<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTryoutSegmentTestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tryout_segment_tests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('tryout_segment_uuid');
            $table->string('test_uuid');
            $table->integer('attempt');
            $table->integer('duration');
            $table->integer('max_point');
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
        Schema::dropIfExists('tryout_segment_tests');
    }
}
