<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
{
    use Queueable;

    protected $userId;
    protected $title;
    protected $message;

    public function __construct($userId, $title, $message)
    {
        $this->userId = $userId;
        $this->title = $title;
        $this->message = $message;
    }

    public function handle()
    {
        $user = User::find($this->userId);
        if ($user && $user->email) {
            Mail::raw($this->message, function ($mail) use ($user) {
                $mail->to($user->email)
                    ->subject($this->title);
            });
        }
    }
}
