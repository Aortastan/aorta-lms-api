<?php
namespace App\Traits\Admin\Question;
use App\Traits\GetMimesRuleTrait;
use Illuminate\Support\Facades\Validator;
use App\Models\TemplateQuestion;
use App\Models\Question;
use App\Models\Answer;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;
use File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait CreateUpdateQuestionTrait
{
    public
    $question = null,
    $question_uuid = null,
    $duplicate_question = false,
    $getTemplate = null;

    public function createQuestion($request){
        $validated = $this->cleanData($request);

        $this->question = Question::create($validated);
        $this->storeAnswer($request, 'create');
    }

    public function updateQuestion($request, $question){
        $this->question = $question;

        $validated = $this->cleanData($request);
        $this->storeAnswer($request, 'update');
        Question::where('uuid', $this->question->uuid)->update($validated);
    }

    public function duplicateQuestion($request, $question){
        $this->question = $question;
        $this->duplicate_question = true;

        $validated = $this->cleanData($request);
        $question = Question::create($validated);
        $this->question_uuid = $question->uuid;
        $this->storeAnswer($request, 'duplicate');

    }

    public function storeAnswer($request, $method){
        $answersUuid = [];
        $newAnswers = [];

        if($this->getTemplate){
            Answer::where([
                'question_uuid' => $this->question->uuid,
            ])->delete();

            foreach ($request->answers as $index => $answer) {
                $path = null;
                if($answer['have_image'] == 1){
                    if(!isset($this->getTemplate->answers[$index])){ // jika jumlah jawaban melebihi template
                        if($answer['image']){
                            $mime = $answer['image']->getMimeType();
                            // Pengecekan apakah tipe MIME adalah tipe gambar
                            if (Str::startsWith($mime, 'image/')) {
                                // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                                $path = $answer['image']->store('imagesAnswer', 'public');
                            }
                        }
                    }
                    else{
                        if($answer['image']){
                            $mime = $answer['image']->getMimeType();
                            // Pengecekan apakah tipe MIME adalah tipe gambar
                            if (Str::startsWith($mime, 'image/')) {
                                // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                                $path = $answer['image']->store('imagesAnswer', 'public');
                            }
                        }else{
                            $sourcePath = 'storage/' . $this->getTemplate->answers[$index]['image']; // Sesuaikan dengan path yang sesuai
                            $path = 'imagesAnswer/' . basename($this->getTemplate->answers[$index]['image']);
                            $destinationPath = 'storage/' . $path; // Sesuaikan dengan path yang sesuai
                            // Pastikan file ada sebelum mencoba menyalin
                            if (Storage::exists($sourcePath)) {
                                // Salin file
                                Storage::copy($sourcePath, $destinationPath);
                            }
                        }
                    }

                }
                $point = null;
                $is_correct = null;

                if($request->question_type == 'most point'){
                    $is_correct = 1;
                    $point = $answer['point'];
                }else{
                    $is_correct = $answer['is_correct'];
                    if($request->different_point == 1){
                        $point = $answer['point'];
                    }
                }

                $newAnswers[]=[
                    'uuid' => Uuid::uuid4()->toString(),
                    'question_uuid' => $this->question_uuid,
                    'answer' => $answer['answer'],
                    'image' => $path,
                    'is_correct' => $is_correct,
                    'correct_answer_explanation' => $correct_answer_explanation,
                    'point' => $point,
                ];
            }
        }

        // jika tidak menggunakan template
        else{
            // jika create baru
            if($method == 'create'){
                foreach ($request->answers as $index => $answer) {
                    $path = null;
                    if($answer['have_image'] == 1){
                        if($answer['image']){
                            $mime = $answer['image']->getMimeType();
                            // Pengecekan apakah tipe MIME adalah tipe gambar
                            if (Str::startsWith($mime, 'image/')) {
                                // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                                $path = $answer['image']->store('imagesAnswer', 'public');
                            }
                        }
                    }

                    $point = null;
                    $is_correct = null;

                    if($request->question_type == 'most point'){
                        $is_correct = 1;
                        $point = $answer['point'];
                    }else{
                        $is_correct = $answer['is_correct'];
                        if($request->different_point == 1){
                            $point = $answer['point'];
                        }
                    }

                    $newAnswers[]=[
                        'uuid' => Uuid::uuid4()->toString(),
                        'question_uuid' => $this->question->uuid,
                        'answer' => $answer['answer'],
                        'image' => $path,
                        'is_correct' => $is_correct,
                        'correct_answer_explanation' => $correct_answer_explanation,
                        'point' => $point,
                    ];
                }
            }
            // jika update answer
            elseif($method == 'update'){
                foreach ($request->answers as $index => $answer) {
                    $checkAnswer = Answer::where('uuid', $answer['uuid'])->first();

                    if(!$checkAnswer){
                            $path = null;
                            if($answer['have_image'] == 1){
                                if($answer['image'] instanceof \Illuminate\Http\UploadedFile && $answer['image']->isValid()){
                                    $path = $answer['image']->store('imagesAnswer', 'public');
                                }
                            }

                            $newAnswers[]=[
                                'uuid' => Uuid::uuid4()->toString(),
                                'question_uuid' => $this->question->uuid,
                                'answer' => $answer['answer'],
                                'image' => $path,
                                'is_correct' => $answer['is_correct'],
                                'point' => $answer['point'],
                            ];
                    }else{
                        $answersUuid[] = $checkAnswer->uuid;
                        $path = $checkAnswer->image;
                        if($answer['have_image'] == 1){
                            if(!is_string($answer['image'])){
                                $path = $answer['image']->store('imagesAnswer', 'public');
                                if($checkAnswer->image){
                                    if (File::exists(public_path('storage/'.$checkAnswer->image))) {
                                        File::delete(public_path('storage/'.$checkAnswer->image));
                                    }
                                }
                            }
                        }else{
                            $path = null;
                            if($checkAnswer->image){
                                if (File::exists(public_path('storage/'.$checkAnswer->image))) {
                                    File::delete(public_path('storage/'.$checkAnswer->image));
                                }
                            }
                        }

                        $point = null;
                        $is_correct = null;

                        if($request->question_type == 'most point'){
                            $is_correct = 1;
                            $point = $answer['point'];
                        }else{
                            $is_correct = $answer['is_correct'];
                            if($request->different_point == 1){
                                $point = $answer['point'];
                            }
                        }

                        $validatedAnswer=[
                            'answer' => $answer['answer'],
                            'image' => $path,
                            'is_correct' => $is_correct,
                            'correct_answer_explanation' => $correct_answer_explanation,
                            'point' => $point,
                        ];
                        Answer::where('uuid', $checkAnswer->uuid)->update($validatedAnswer);
                    }
                }
            }elseif($method == 'duplicate'){
                foreach ($request->answers as $index => $answer) {
                    $path = null;
                    if($answer['have_image'] == 1){
                        if($answer['image']){
                            $mime = $answer['image']->getMimeType();
                            // Pengecekan apakah tipe MIME adalah tipe gambar
                            if (Str::startsWith($mime, 'image/')) {
                                // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                                $path = $answer['image']->store('imagesAnswer', 'public');
                            }
                        }else{
                            if($index < count($this->getTemplate->answers)){
                                $path = $this->getTemplate->answers[$index]['image'];
                                $sourcePath = 'storage/' . $this->getTemplate->answers[$index]['image']; // Sesuaikan dengan path yang sesuai
                                $originalFileName = basename($this->getTemplate->answers[$index]['image']);
                                $destinationFolder = 'storage/imagesAnswer/';
                                // Pastikan file ada sebelum mencoba menyalin
                                if (Storage::exists($sourcePath)) {
                                    // Dapatkan informasi ekstensi file asli
                                    $pathInfo = pathinfo($originalFileName);
                                    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

                                    // Buat nama file baru secara otomatis dengan UUID
                                    $newFileName = Str::uuid()->toString();
                                    $newFileNameWithExtension = $newFileName . $extension;
                                    $path = 'imagesAnswer/' . $newFileNameWithExtension;

                                    // Salin file
                                    Storage::copy($sourcePath, $destinationFolder . $originalFileName);

                                    // Ganti nama file di path tujuan dengan nama baru + ekstensi
                                    Storage::move($destinationFolder . $originalFileName, $destinationFolder . $newFileNameWithExtension);
                                }

                            }
                        }
                    }

                    $point = null;
                    $is_correct = null;

                    if($request->question_type == 'most point'){
                        $is_correct = 1;
                        $point = $answer['point'];
                    }else{
                        $is_correct = $answer['is_correct'];
                        if($request->different_point == 1){
                            $point = $answer['point'];
                        }
                    }

                    $newAnswers[]=[
                        'uuid' => Uuid::uuid4()->toString(),
                        'question_uuid' => $this->question->uuid,
                        'answer' => $answer['answer'],
                        'image' => $path,
                        'is_correct' => $is_correct,
                        'correct_answer_explanation' => $correct_answer_explanation,
                        'point' => $point,
                    ];
                }




                foreach ($request->answers as $index => $answer) {
                    $checkAnswer = Answer::where('uuid', $answer['uuid'])->first();

                    if(!$checkAnswer){
                            $path = null;
                            if($answer['have_image'] == 1){
                                if($answer['image'] instanceof \Illuminate\Http\UploadedFile && $answer['image']->isValid()){
                                    $path = $answer['image']->store('imagesAnswer', 'public');
                                }
                            }

                            $newAnswers[]=[
                                'uuid' => Uuid::uuid4()->toString(),
                                'question_uuid' => $question->uuid,
                                'answer' => $answer['answer'],
                                'image' => $path,
                                'is_correct' => $answer['is_correct'],
                                'point' => $answer['point'],
                            ];
                    }else{
                        $answersUuid[] = $checkAnswer->uuid;
                        $path = $checkAnswer->image;
                        if($answer['have_image'] == 1){
                            if(!is_string($answer['image'])){
                                $path = $answer['image']->store('imagesAnswer', 'public');
                                if($checkAnswer->image){
                                    if (File::exists(public_path('storage/'.$checkAnswer->image))) {
                                        File::delete(public_path('storage/'.$checkAnswer->image));
                                    }
                                }
                            }
                        }else{
                            $path = null;
                            if($checkAnswer->image){
                                if (File::exists(public_path('storage/'.$checkAnswer->image))) {
                                    File::delete(public_path('storage/'.$checkAnswer->image));
                                }
                            }
                        }

                        $point = null;
                        $is_correct = null;

                        if($request->question_type == 'most point'){
                            $is_correct = 1;
                            $point = $answer['point'];
                        }else{
                            $is_correct = $answer['is_correct'];
                            if($request->different_point == 1){
                                $point = $answer['point'];
                            }
                        }

                        $validatedAnswer=[
                            'answer' => $answer['answer'],
                            'image' => $path,
                            'is_correct' => $is_correct,
                            'correct_answer_explanation' => $correct_answer_explanation,
                            'point' => $point,
                        ];
                        Answer::where('uuid', $checkAnswer->uuid)->update($validatedAnswer);
                    }
                }
            }
        }


        if(count($newAnswers) > 0){
            Answer::insert($newAnswers);
        }

        if(count($answersUuid) > 0){
            Answer::where(['question_uuid' => $this->question->uuid])->whereNotIn('uuid', $answersUuid)->delete();
        }
    }

    private function cleanData($request){
        $path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;

        if(isset($request->question_uuid)){
            $this->getTemplate = Question::where([
                    'uuid' => $request->question_uuid,
                ])->with(['answers'])->first();
        }

        // jika ada template
        if($this->getTemplate){
            // jika ada file baru, baik saat menggunakan template atau tidak
            if($request->file){
                $file_size = $request->file->getSize();
                $path = $request->file->store('questions', 'public');
                $file_size = round($file_size / (1024 * 1024), 2); //Megabytes
                $file_duration = $request->file_duration;
            }
            // jika menggunakan template namun tidak ada file baru
            else{
                $sourcePath = 'storage/' . $this->getTemplate->file_path; // Sesuaikan dengan path yang sesuai
                $originalFileName = basename($this->getTemplate->file_path);
                $destinationFolder = 'storage/questions/';
                // Pastikan file ada sebelum mencoba menyalin
                if (Storage::exists($sourcePath)) {
                    // Dapatkan informasi ekstensi file asli
                    $pathInfo = pathinfo($originalFileName);
                    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

                    // Buat nama file baru secara otomatis dengan UUID
                    $newFileName = Str::uuid()->toString();
                    $newFileNameWithExtension = $newFileName . $extension;
                    $path = 'questions/' . $newFileNameWithExtension;

                    // Salin file
                    Storage::copy($sourcePath, $destinationFolder . $originalFileName);

                    // Ganti nama file di path tujuan dengan nama baru + ekstensi
                    Storage::move($destinationFolder . $originalFileName, $destinationFolder . $newFileNameWithExtension);
                }
                $file_size = $this->getTemplate->file_size;
                $file_duration = $this->getTemplate->file_duration;
            }

        }else{
            // jika update data
            if($this->question){
                if($request->type == 'text' || $request->type == 'youtube'){
                    $this->deleteFileQuestion();
                }

                if($request->type != 'text'){
                    if($request->type == 'youtube'){
                        $url_path = $request->url_path;
                    }else{
                        $path = $this->question->file_path;
                        $file_size = $this->question->file_size;
                        if(!is_string($request->file)){
                            $this->deleteFileQuestion();

                            if($request->file){
                                $file_size = $request->file->getSize();
                                $file_size = round($file_size / (1024 * 1024), 2);
                                $path = $request->file->store('questions', 'public');
                            }
                        }
                        $file_duration = $request->file_duration;
                    }
                }
            }
            // jika create data
            else{
                if($request->type != 'text'){
                    if($request->type == 'youtube'){
                        $url_path = $request->url_path;
                    }else{
                        $file_size = $request->file->getSize();
                        $path = $request->file->store('questions', 'public');
                        $file_size = round($file_size / (1024 * 1024), 2); //Megabytes
                        $file_duration = $request->file_duration;
                    }

                }
            }
        }

        $point = null;
        if($request->different_point == 0){
            $point = $request->point;
        }

        $validated=[
            'subject_uuid' => $request->subject_uuid,
            'question_type' => $request->question_type,
            'title' => $request->title,
            'question' => $request->question,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'type' => $request->type,
            'different_point' => $request->different_point,
            'status' => $request->status,
            'point' => $point,
            'hint' => $request->hint,
            'file_path' => $path,
        ];

        if($this->question == null || $this->duplicate_question){
            $user = JWTAuth::parseToken()->authenticate();
            $validated['author_uuid'] = $user->uuid;
        }

        return $validated;
    }

    private function deleteFileAnswer(){
        $answers = Answer::where([
            'question_uuid' => $this->question->uuid,
        ])->get();

        foreach ($answers as $index => $answer) {
            if($this->answer->image){
                if (File::exists(public_path('storage/'.$this->answer->image))) {
                    File::delete(public_path('storage/'.$this->answer->image));
                }
            }
        }
    }

    private function deleteFileQuestion(){
        if($this->question){
            if($this->question->file_path){
                if (File::exists(public_path('storage/'.$this->question->file_path))) {
                    File::delete(public_path('storage/'.$this->question->file_path));
                }
            }
        }
    }
}
