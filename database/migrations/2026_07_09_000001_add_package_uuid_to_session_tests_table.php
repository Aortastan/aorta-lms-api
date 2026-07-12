<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPackageUuidToSessionTestsTable extends Migration
{
    public function up()
    {
        Schema::table('session_tests', function (Blueprint $table) {
            $table->string('package_uuid')->nullable()->after('user_uuid');
        });
    }

    public function down()
    {
        Schema::table('session_tests', function (Blueprint $table) {
            $table->dropColumn('package_uuid');
        });
    }
}
