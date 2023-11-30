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
        $rules = [
            'subject_uuid' => 'required|string',
            'title' => 'required|string',
            'question' => 'required|string',
            'question_type' => 'required|in:multi choice,most point,single choice,fill in blank,true false',
            'type' => 'required|in:video,youtube,text,image,pdf,audio,slide document',
            'status' => 'required|in:Published,Waiting for review,Draft',
            'different_point' => 'required|in:1,0',
            'answers' => 'required|array',
            'answers.*' => 'array',
            'answers.*.answer' => 'required|string',
        ];

        if($method == 'duplicate'){
            $rules['question_uuid'] = 'required|string';
        }

        $getTemplate = null;
        if(isset($request->question_uuid)){
            $getTemplate = Question::where([
                    'uuid' => $request->question_uuid,
                ])->with(['answers'])->first();
        }

        if($request->type != 'text'){
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

        if($request->question_type != 'fill in blank'){
            $rules['answers.*.is_correct'] = 'required|in:1,0';
            $rules['answers.*.have_image'] = 'required|in:1,0';
        }

        if($request->different_point == 1){
            $rules['answers.*.point'] = 'required|integer';
        }else{
            $rules['point'] = 'required|integer';
        }

        return Validator::make($request->all(), $rules);
    }
}
