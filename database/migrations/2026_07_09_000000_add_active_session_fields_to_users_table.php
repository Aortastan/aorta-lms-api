<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActiveSessionFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'active_device_id')) {
                $table->string('active_device_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'active_token')) {
                $table->text('active_token')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'active_token')) {
                $table->dropColumn('active_token');
            }
            if (Schema::hasColumn('users', 'active_device_id')) {
                $table->dropColumn('active_device_id');
            }
        });
    }
}
