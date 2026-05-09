<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LessonAttendances;
use App\Models\LessonLecture;
use Auth;
use Carbon\Carbon;

class LessonAttendanceController extends Controller
{
    public function approve($id)
    {
        try {
            LessonAttendances::where("uuid", $id)->update([
                "note_status" => "approved",
                "note_approved_by" => Auth::id(),
            ]);

            return response()->json([
                "message" => "Success approve note",
                "success" => true
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                "message" => "failed approve note",
                "success" => false,
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function reject($id)
    {
        try {
            LessonAttendances::where("uuid", $id)->update([
                "note_status" => "reject",
                "note_approved_by" => Auth::id(),
            ]);

            return response()->json([
                "message" => "Success reject note",
                "ok" => true
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                "message" => "failed reject note",
                "ok" => false,
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function submitNote(Request $request)
    {
        try {
            LessonAttendances::updateOrCreate([
                "lesson_lecture_uuid" => $request->lesson_lecture_uuid,
                "user_uuid" => Auth::id(),
            ], [
                "lesson_lecture_uuid" => $request->lesson_lecture_uuid,
                "note" => $request->note,
                "user_uuid" => Auth::id(),
            ]);

            return response()->json([
                "message" => "Success submit note",
                "success" => true
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                "message" => "failed submit note",
                "success" => false,
                "error" => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $attendances = LessonAttendances::get();
        return response()->json([
            'message' => "Sukses mengambil data",
            "data" => $attendances,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $exist = LessonAttendances::where('lesson_lecture_uuid', $request->lesson_lecture_uuid)->where('user_uuid', Auth::id())->first();
            if ($exist && $exist->start_attendance) {
                $start = Carbon::parse($exist->start_attendance);
            }

            $lecture = LessonLecture::where('uuid', $request->lesson_lecture_uuid)->first();
            if (!$lecture->attendance_started_at) {
                return response()->json([
                    "message" => "Absensi belum dimulai",
                ], 200);
            }

            $attendanceStartAt = Carbon::parse($lecture->attendance_started_at);
            $attendanceEndAt = Carbon::parse($lecture->attendance_ended_at);

            if ($exist && $exist->start_attendance && $request->type === "start") {
                return response()->json([
                    "message" => "Kamu sudah melakukan absen masuk",
                ], 200);
            } else if ($exist && $exist->end_attendance && $request->type === "end") {
                return response()->json([
                    "message" => "Kamu sudah melakukan absen keluar",
                ], 200);
            }

            if ($attendanceStartAt->diffInHours(now()) >= 1 && $request->type === "start") {
                return response()->json([
                    "message" => "Tidak bisa absen awal, sesi absen awal sudah berakhir",
                ], 200);
            }

            if ($attendanceStartAt->diffInMinutes(now()) <= 60 && $request->type === "end") {
                return response()->json([
                    "message" => "Tidak bisa absen akhir, sesi absen akhir belum dimulai",
                ], 200);
            }

            if ($attendanceEndAt->diffInHours(now()) >= 1 && $request->type === "end") {
                return response()->json([
                    "message" => "Tidak bisa absen akhir, sesi absen akhir sudah berakhir",
                ], 200);
            }




            $attendance = new LessonAttendances();
            if ($request->type === "start") {
                $attendance->start_attendance = now();
            } else {
                $attendance->end_attendance = now();
            }
            $attendance->lesson_lecture_uuid = $request->lesson_lecture_uuid;
            $attendance->user_uuid = Auth::id();

            $attendance->updateOrCreate([
                'lesson_lecture_uuid' => $attendance->lesson_lecture_uuid,
                'user_uuid' => auth()->id(),
            ], $attendance->toArray());
            return response()->json([
                "message" => "Absen berhasil",
                "data" => $attendance
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => "Absen gagal",
                "data" => null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $resp = LessonAttendances::with('user')
            ->where('lesson_lecture_uuid', $id)
            ->get()
            ->map(function ($item) {

                $item->start_attendance = $item->start_attendance
                    ? Carbon::parse($item->start_attendance)
                    ->format('d/m/Y H:i:s')
                    : null;

                $item->end_attendance = $item->end_attendance
                    ? Carbon::parse($item->end_attendance)
                    ->format('d/m/Y H:i:s')
                    : null;
                $item->approval_status = $item->note_status;
                $item->approved_by = $item->note_approved_by;

                return $item;
            });

        return response()->json([
            "message" => "Success",
            "data" => $resp
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
