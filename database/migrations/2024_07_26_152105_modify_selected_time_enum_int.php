<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifySelectedTimeEnumInt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pauli_records', function (Blueprint $table) {
            // Modify selected_time to enum integer
            $table->integer('selected_time')->change();
        });

        // Convert existing enum string values to integer values
        DB::table('pauli_records')->update([
            'selected_time' => DB::raw("CASE
                WHEN selected_time = '15' THEN 15
                WHEN selected_time = '30' THEN 30
                WHEN selected_time = '45' THEN 45
                WHEN selected_time = '60' THEN 60
                WHEN selected_time = '120' THEN 120
                WHEN selected_time = '180' THEN 180
                ELSE selected_time
            END")
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pauli_records', function (Blueprint $table) {
            // Revert selected_time to enum string
            $table->string('selected_time')->change();
        });

        // Convert integer values back to string values
        DB::table('pauli_records')->update([
            'selected_time' => DB::raw("CASE
                WHEN selected_time = 15 THEN '15'
                WHEN selected_time = 30 THEN '30'
                WHEN selected_time = 45 THEN '45'
                WHEN selected_time = 60 THEN '60'
                ELSE selected_time
            END")
        ]);
    }
}
