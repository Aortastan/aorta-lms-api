<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePauliRecordDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pauli_record_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pauli_record_id');
            $table->boolean('correct');
            $table->boolean('wrong');
            $table->timestamp('time');
            $table->timestamps();

            $table->foreign('pauli_record_id')->references('id')->on('pauli_records')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pauli_record_details');
    }
}
