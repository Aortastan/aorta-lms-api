<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNameVerifiedToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'name_verified_at')) {
                $table->timestamp('name_verified_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'name_verified_by')) {
                $table->string('name_verified_by')->nullable()->after('name_verified_at');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name_verified_by')) {
                $table->dropColumn('name_verified_by');
            }
            if (Schema::hasColumn('users', 'name_verified_at')) {
                $table->dropColumn('name_verified_at');
            }
        });
    }
}
