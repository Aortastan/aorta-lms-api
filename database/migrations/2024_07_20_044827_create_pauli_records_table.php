<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePauliRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pauli_records', function (Blueprint $table) {
            $table->id();
            $table->enum('selected_time', [1, 2, 5, 10, 15, 30, 60])->nullable();
            $table->integer('questions_attempted')->nullable();
            $table->integer('total_correct')->nullable();
            $table->integer('total_wrong')->nullable();
            $table->timestamp('time_start')->nullable();
            $table->timestamp('time_end')->nullable();
            $table->date('date')->nullable();
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
        Schema::dropIfExists('pauli_records');
    }
}
