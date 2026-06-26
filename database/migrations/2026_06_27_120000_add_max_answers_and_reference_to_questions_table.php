<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaxAnswersAndReferenceToQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tambahan untuk tipe soal psikotes baru:
     * - max_answers : batas maksimum jawaban yang boleh dipilih (tipe "checklist").
     * - reference   : konten referensi (HTML) yang ditampilkan menempel di atas soal
     *                 (tipe "visual matching", mis. tabel/gambar referensi A-F).
     * Keduanya nullable supaya tidak mengganggu data & tipe soal yang sudah ada.
     * Tanpa ->after() agar MySQL 8 bisa pakai ALGORITHM=INSTANT.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'max_answers')) {
                $table->integer('max_answers')->nullable();
            }
            if (!Schema::hasColumn('questions', 'reference')) {
                $table->longText('reference')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'max_answers')) {
                $table->dropColumn('max_answers');
            }
            if (Schema::hasColumn('questions', 'reference')) {
                $table->dropColumn('reference');
            }
        });
    }
}
