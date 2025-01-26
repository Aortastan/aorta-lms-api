<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTestTypeCommentInTestsTable extends Migration
{
    public function up()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('test_type')
                ->comment('classical, IRT, Tes Potensi, TSKKWK')
                ->change();
        });
    }

    public function down()
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('test_type')
                ->comment('classical, IRT')
                ->change();
        });
    }
}
