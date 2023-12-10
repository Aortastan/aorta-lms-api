<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type_coupon')->comment('discount amount, percentage discount');
            $table->integer('type_limit')->comment('1 = by total user, 2 = by date');
            $table->string('code')->unique();
            $table->integer('price')->nullable();
            $table->integer('discount')->nullable();
            $table->integer('limit')->nullable()->comment('if type_limit 1, this field is a must');
            $table->datetime('expired_date')->nullable()->comment('if type_limit 2, this field is a must');
            $table->integer('limit_per_user');
            $table->boolean('is_restricted');
            $table->string('restricted_by')->nullable()->comment('package/category');
            $table->string('package_uuid')->nullable();
            $table->string('category_uuid')->nullable();
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
        Schema::dropIfExists('coupons');
    }
}
