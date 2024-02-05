<?php
namespace App\Traits;

trait GetMimesRuleTrait
{
    public function getMimesRule($type)
    {
        switch ($type) {
            case 'pdf':
                return 'mimes:pdf';
            case 'video':
                return 'mimes:mp4,avi,mov,wmv';
            case 'audio':
                return 'mimes:mp3,wav,ogg';
            case 'image':
                return 'mimes:jpeg,png,jpg,gif';
            case 'slide document':
                return 'mimes:ppt,pptx';
            default:
                return null;
        }
    }
}
