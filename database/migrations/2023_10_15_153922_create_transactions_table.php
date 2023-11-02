<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid');
            $table->string('package_uuid');
            $table->string('coupon_uuid')->nullable();
            $table->string('type_of_purchase')->comment('lifetime,one month,three months,six months,one year');
            $table->string('transaction_type')->comment('course, test');
            $table->integer('transaction_amount');
            $table->string('payment_method_uuid');
            $table->string('transaction_status')->comment('pending, success, failed, canceled');
            $table->string('url')->nullable();
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
        Schema::dropIfExists('transactions');
    }
}
