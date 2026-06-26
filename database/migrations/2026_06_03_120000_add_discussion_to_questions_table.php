<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscussionToQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('questions', 'discussion')) {
            Schema::table('questions', function (Blueprint $table) {
                // Pembahasan soal (terutama untuk tipe essay) yang ditampilkan ke siswa saat review.
                // Tanpa ->after() supaya MySQL 8 bisa pakai ALGORITHM=INSTANT (tanpa rebuild & lock tabel).
                $table->longText('discussion')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('discussion');
        });
    }
}
