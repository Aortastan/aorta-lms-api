<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category_uuid');
            $table->string('subcategory_uuid');
            $table->string('package_type')->comment('course, test');
            $table->string('name');
            $table->text('description');
            $table->string('price_lifetime');
            $table->string('price_one_month');
            $table->string('price_three_months');
            $table->string('price_six_months');
            $table->string('price_one_year');
            $table->string('learner_accesibility')->comment('pain, free');
            $table->string('image');
            $table->integer('discount');
            $table->boolean('is_membership');
            $table->string('status')->comment('Published, Waiting for review, Draft');
            $table->string('test_type')->nullable()->comment('classical, IRT');
            $table->integer('max_point')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('packages');
    }
}
