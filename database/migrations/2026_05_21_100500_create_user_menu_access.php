<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserMenuAccess extends Migration
{
    public function up()
    {
        Schema::create('user_menu_access', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->string('menu_key', 100);
            $table->timestamps();

            $table->unique(['user_uuid', 'menu_key'], 'uma_user_menu_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_menu_access');
    }
}
