<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCorrectDatasAndIncorrectDatasToPauliRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pauli_records', function (Blueprint $table) {
            $table->json('correct_datas')->nullable();
            $table->json('incorrect_datas')->nullable();
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
            $table->dropColumn('correct_datas');
            $table->dropColumn('incorrect_datas');
        });
    }
}
