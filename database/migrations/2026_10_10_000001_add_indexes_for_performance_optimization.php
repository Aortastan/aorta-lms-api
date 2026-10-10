<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForPerformanceOptimization extends Migration
{
    /**
     * Check if an index exists on a table.
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();
        $result = $conn->select(
            "SELECT COUNT(1) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$dbName, $table, $indexName]
        );
        return !empty($result) && $result[0]->cnt > 0;
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Index pada tabel answers untuk mempercepat pencarian jawaban berdasarkan question_uuid
        if (Schema::hasTable('answers') && Schema::hasColumn('answers', 'question_uuid')) {
            if (!$this->hasIndex('answers', 'answers_question_uuid_idx')) {
                Schema::table('answers', function (Blueprint $table) {
                    $table->index('question_uuid', 'answers_question_uuid_idx');
                });
            }
        }

        // 2. Index pada student_tryouts untuk mempercepat count attempt dan filter per user & package_test
        if (Schema::hasTable('student_tryouts')) {
            if (Schema::hasColumns('student_tryouts', ['user_uuid', 'package_test_uuid'])) {
                if (!$this->hasIndex('student_tryouts', 'student_tryouts_user_package_test_idx')) {
                    Schema::table('student_tryouts', function (Blueprint $table) {
                        $table->index(['user_uuid', 'package_test_uuid'], 'student_tryouts_user_package_test_idx');
                    });
                }
            }
            if (Schema::hasColumn('student_tryouts', 'package_test_uuid')) {
                if (!$this->hasIndex('student_tryouts', 'student_tryouts_package_test_idx')) {
                    Schema::table('student_tryouts', function (Blueprint $table) {
                        $table->index('package_test_uuid', 'student_tryouts_package_test_idx');
                    });
                }
            }
        }

        // 3. Index pada student_quizzes dan student_pretest_posttests untuk riwayat attempt
        if (Schema::hasTable('student_quizzes') && Schema::hasColumns('student_quizzes', ['user_uuid', 'lesson_quiz_uuid'])) {
            if (!$this->hasIndex('student_quizzes', 'student_quizzes_user_lesson_idx')) {
                Schema::table('student_quizzes', function (Blueprint $table) {
                    $table->index(['user_uuid', 'lesson_quiz_uuid'], 'student_quizzes_user_lesson_idx');
                });
            }
        }

        Schema::table('student_pretest_posttests', function (Blueprint $table) {
            if (Schema::hasColumns('student_pretest_posttests', ['user_uuid', 'pretest_posttest_uuid'])) {
                if (!$this->hasIndex('student_pretest_posttests', 'student_pretest_posttests_user_pretest_idx')) {
                    $table->index(['user_uuid', 'pretest_posttest_uuid'], 'student_pretest_posttests_user_pretest_idx');
                }
            }
        });

        // 4. Index pada carts untuk mempercepat GET /api/v1/student/carts
        if (Schema::hasTable('carts') && Schema::hasColumn('carts', 'user_uuid')) {
            if (!$this->hasIndex('carts', 'carts_user_uuid_idx')) {
                Schema::table('carts', function (Blueprint $table) {
                    $table->index('user_uuid', 'carts_user_uuid_idx');
                });
            }
        }

        // 5. Index pada purchased_packages untuk mempercepat GET /api/v1/student/packages
        if (Schema::hasTable('purchased_packages')) {
            if (Schema::hasColumns('purchased_packages', ['user_uuid', 'package_uuid'])) {
                if (!$this->hasIndex('purchased_packages', 'purchased_packages_user_package_idx')) {
                    Schema::table('purchased_packages', function (Blueprint $table) {
                        $table->index(['user_uuid', 'package_uuid'], 'purchased_packages_user_package_idx');
                    });
                }
            }
        }

        // 6. Index pada membership_histories untuk mempercepat cek kepemilikan paket
        if (Schema::hasTable('membership_histories')) {
            if (Schema::hasColumns('membership_histories', ['user_uuid', 'package_uuid'])) {
                if (!$this->hasIndex('membership_histories', 'membership_histories_user_package_idx')) {
                    Schema::table('membership_histories', function (Blueprint $table) {
                        $table->index(['user_uuid', 'package_uuid'], 'membership_histories_user_package_idx');
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('answers') && $this->hasIndex('answers', 'answers_question_uuid_idx')) {
            Schema::table('answers', function (Blueprint $table) {
                $table->dropIndex('answers_question_uuid_idx');
            });
        }

        if (Schema::hasTable('student_tryouts')) {
            Schema::table('student_tryouts', function (Blueprint $table) {
                if ($this->hasIndex('student_tryouts', 'student_tryouts_user_package_test_idx')) {
                    $table->dropIndex('student_tryouts_user_package_test_idx');
                }
                if ($this->hasIndex('student_tryouts', 'student_tryouts_package_test_idx')) {
                    $table->dropIndex('student_tryouts_package_test_idx');
                }
            });
        }

        if (Schema::hasTable('student_quizzes') && $this->hasIndex('student_quizzes', 'student_quizzes_user_lesson_idx')) {
            Schema::table('student_quizzes', function (Blueprint $table) {
                $table->dropIndex('student_quizzes_user_lesson_idx');
            });
        }

        if (Schema::hasTable('student_pretest_posttests') && $this->hasIndex('student_pretest_posttests', 'student_pretest_posttests_user_pretest_idx')) {
            Schema::table('student_pretest_posttests', function (Blueprint $table) {
                $table->dropIndex('student_pretest_posttests_user_pretest_idx');
            });
        }
    }
}
