<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIrtPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('irt_points', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('package_test_uuid');
            $table->text('data_question');
            $table->integer('total_submit');
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
        Schema::dropIfExists('irt_points');
    }
}
