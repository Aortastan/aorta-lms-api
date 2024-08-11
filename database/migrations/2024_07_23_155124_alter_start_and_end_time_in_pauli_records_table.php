<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterStartAndEndTimeInPauliRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pauli_records', function (Blueprint $table) {
            $table->time('time_start')->change();
            $table->time('time_end')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pauli_records', function (Blueprint $table) {
            $table->timestamp('time_start')->change();
            $table->timestamp('time_end')->change();
        });
    }
}
