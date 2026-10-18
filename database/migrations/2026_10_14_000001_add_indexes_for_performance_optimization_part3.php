<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForPerformanceOptimizationPart3 extends Migration
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
        // 1. Composite index pada student_progress untuk mempercepat pengecekan materi selesai
        if (Schema::hasTable('student_progress') && Schema::hasColumns('student_progress', ['user_uuid', 'lecture_uuid'])) {
            if (!$this->hasIndex('student_progress', 'student_progress_user_lecture_idx')) {
                Schema::table('student_progress', function (Blueprint $table) {
                    $table->index(['user_uuid', 'lecture_uuid'], 'student_progress_user_lecture_idx');
                });
            }
        }

        // 2. Composite index pada student_assignments
        if (Schema::hasTable('student_assignments') && Schema::hasColumns('student_assignments', ['student_uuid', 'assignment_uuid'])) {
            if (!$this->hasIndex('student_assignments', 'student_assignments_student_assignment_idx')) {
                Schema::table('student_assignments', function (Blueprint $table) {
                    $table->index(['student_uuid', 'assignment_uuid'], 'student_assignments_student_assignment_idx');
                });
            }
        }

        // 3. Index pada package_courses untuk mempercepat pengecekan kepemilikan kursus
        if (Schema::hasTable('package_courses')) {
            if (Schema::hasColumns('package_courses', ['package_uuid', 'course_uuid']) && !$this->hasIndex('package_courses', 'package_courses_pkg_course_idx')) {
                Schema::table('package_courses', function (Blueprint $table) {
                    $table->index(['package_uuid', 'course_uuid'], 'package_courses_pkg_course_idx');
                });
            }
            if (Schema::hasColumn('package_courses', 'course_uuid') && !$this->hasIndex('package_courses', 'package_courses_course_idx')) {
                Schema::table('package_courses', function (Blueprint $table) {
                    $table->index('course_uuid', 'package_courses_course_idx');
                });
            }
        }

        // 4. Index pada package_tests untuk mempercepat pengecekan kepemilikan test/tryout
        if (Schema::hasTable('package_tests')) {
            if (Schema::hasColumns('package_tests', ['package_uuid', 'test_uuid']) && !$this->hasIndex('package_tests', 'package_tests_pkg_test_idx')) {
                Schema::table('package_tests', function (Blueprint $table) {
                    $table->index(['package_uuid', 'test_uuid'], 'package_tests_pkg_test_idx');
                });
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
        if (Schema::hasTable('student_progress') && $this->hasIndex('student_progress', 'student_progress_user_lecture_idx')) {
            Schema::table('student_progress', function (Blueprint $table) {
                $table->dropIndex('student_progress_user_lecture_idx');
            });
        }

        if (Schema::hasTable('student_assignments') && $this->hasIndex('student_assignments', 'student_assignments_student_assignment_idx')) {
            Schema::table('student_assignments', function (Blueprint $table) {
                $table->dropIndex('student_assignments_student_assignment_idx');
            });
        }

        if (Schema::hasTable('package_courses')) {
            Schema::table('package_courses', function (Blueprint $table) {
                if ($this->hasIndex('package_courses', 'package_courses_pkg_course_idx')) {
                    $table->dropIndex('package_courses_pkg_course_idx');
                }
                if ($this->hasIndex('package_courses', 'package_courses_course_idx')) {
                    $table->dropIndex('package_courses_course_idx');
                }
            });
        }

        if (Schema::hasTable('package_tests') && $this->hasIndex('package_tests', 'package_tests_pkg_test_idx')) {
            Schema::table('package_tests', function (Blueprint $table) {
                $table->dropIndex('package_tests_pkg_test_idx');
            });
        }
    }
}
