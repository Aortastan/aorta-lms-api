<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

// class ResetPasswordCustom extends Notification 
class ResetPasswordCustom extends Notification implements ShouldQueue
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;

        // Optional: delay kirim email (misal 5 detik)
        // $this->delay(now()->addSeconds(5));
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $token = $this->token;
        $email = $notifiable->getEmailForPasswordReset();
        $expires = Carbon::now()->addMinutes(config('auth.passwords.users.expire'))->timestamp;

        // Ambil base url dari APP_URL
        $baseUrl = config('frontend_url'); // APP_URL dari .env

        // Buat URL frontend untuk reset password
        $url = $baseUrl . "/auth/change-password?email={$email}&token={$token}&expires={$expires}";

        return (new MailMessage)
            ->subject('Reset Password Akun AORTAEDU')
            ->view('emails.reset-password', [
                'url'  => $url,
                'user' => $notifiable,
            ]);
    }
}
