<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOrderToQuestionTestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('question_tests', 'order')) {
            Schema::table('question_tests', function (Blueprint $table) {
                $table->unsignedInteger('order')->nullable()->after('question_uuid');
            });
        }

        DB::statement('UPDATE question_tests qt JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY test_uuid ORDER BY id) AS rn FROM question_tests) ranked ON ranked.id = qt.id SET qt.`order` = ranked.rn');
    }

    public function down()
    {
        if (Schema::hasColumn('question_tests', 'order')) {
            Schema::table('question_tests', function (Blueprint $table) {
                $table->dropColumn('order');
            });
        }
    }
}
