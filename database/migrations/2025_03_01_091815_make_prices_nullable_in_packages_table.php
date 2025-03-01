<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakePricesNullableInPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('price_lifetime')->nullable()->change();
            $table->string('price_one_month')->nullable()->change();
            $table->string('price_three_months')->nullable()->change();
            $table->string('price_six_months')->nullable()->change();
            $table->string('price_one_year')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('price_lifetime')->nullable(false)->change();
            $table->string('price_one_month')->nullable(false)->change();
            $table->string('price_three_months')->nullable(false)->change();
            $table->string('price_six_months')->nullable(false)->change();
            $table->string('price_one_year')->nullable(false)->change();
        });
    }
}
