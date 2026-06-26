<?php
namespace App\Traits\Admin\Question;
use App\Traits\GetMimesRuleTrait;
use Illuminate\Support\Facades\Validator;
use App\Models\TemplateQuestion;
use App\Models\Question;

trait QuestionValidationRuleTrait
{
    use GetMimesRuleTrait;
    public function validateRule($request, $method){
        $isEssay = $request->question_type === 'essay';

        $rules = [
            'subject_uuid' => 'required|string',
            'title' => 'required|string',
            'question' => 'required|string',
            'question_type' => 'required|in:multi choice,most point,single choice,fill in blank,true false,essay,diagram reasoning,checklist,visual matching',
            // Essay tidak butuh field type (video/text/pdf/dll) — default "text"
            'type' => $isEssay
                ? 'nullable|in:video,youtube,text,image,pdf,audio,slide document'
                : 'required|in:video,youtube,text,image,pdf,audio,slide document',
            'status' => 'required|in:Published,Waiting for review,Draft',
            'different_point' => 'required|in:1,0',
        ];

        // Essay: tidak butuh answers (manual scoring oleh instructor)
        if (!$isEssay) {
            $rules['answers'] = 'required|array';
            $rules['answers.*'] = 'required|array';
            $rules['answers.*.answer'] = 'required|string';
        }

        if($method == 'duplicate'){
            $rules['question_uuid'] = 'required|string';
        }

        $getTemplate = null;
        if(isset($request->question_uuid)){
            $getTemplate = Question::where([
                    'uuid' => $request->question_uuid,
                ])->with(['answers'])->first();
        }

        // Skip media validation untuk essay
        if(!$isEssay && $request->type != 'text'){
            if($request->type == 'youtube'){
                $rules['url_path'] = 'required';
                $rules['file_duration'] = 'required';
            }else{
                if($request->type == 'video' || $request->type == 'audio'){
                    $rules['file_duration'] = 'required';
                }

                if($getTemplate){
                    // jika mengambil dari template, kemudian mengganti file dari template tersebut. Maka diperlukan validasi
                    if($request->file){
                        $rules['file'] = "required|" . $this->getMimesRule($request->type);
                    }
                }
                // jika bukan dari template
                else{
                    // jika create
                    if($method == 'create'){
                        $rules['file'] = "required|" . $this->getMimesRule($request->type);
                    }elseif (($method == 'update' || $method == 'duplicate') && $request->file) { // jika update data, kemudian ada file baru
                        $rules['file'] = "required|" . $this->getMimesRule($request->type);
                    }
                }
            }
        }

        if($request->hint){
            $rules['hint'] = 'string';
        }

        // Pembahasan soal (opsional) — ditampilkan ke siswa saat review
        if($request->discussion){
            $rules['discussion'] = 'string';
        }

        // Validasi per-answer hanya untuk non-essay & non-fill-in-blank
        if(!$isEssay && $request->question_type != 'fill in blank'){
            $rules['answers.*.is_correct'] = 'required|in:1,0';
            $rules['answers.*.have_image'] = 'required|in:1,0';
        }

        // Point/different_point: essay pakai 'point' sebagai skor maksimum, boleh dikosongkan
        if($isEssay){
            $rules['point'] = 'nullable|integer|min:0';
        } elseif($request->different_point == 1){
            $rules['answers.*.point'] = 'required|integer';
        }else{
            $rules['point'] = 'required|integer';
        }

        // Checklist (multi-pilih) : batas maksimum jawaban dipilih (opsional).
        if($request->question_type == 'checklist'){
            $rules['max_answers'] = 'nullable|integer|min:1';
        }

        // Visual matching : konten referensi yang ditampilkan menempel di atas soal.
        if($request->question_type == 'visual matching' && $request->reference){
            $rules['reference'] = 'string';
        }

        return Validator::make($request->all(), $rules);
    }
}
