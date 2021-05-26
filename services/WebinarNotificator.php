<?php

namespace Lms\Application\Conventions\Webinars;

use App\Mail\WebinarStartNotificationEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Lms\Infrastructure\Conventions\Db\Eloquent\Convention;
use Lms\Infrastructure\Users\Db\Eloquent\User;

class WebinarNotificator
{
    public function webinarStartNotifierSend(Convention $webinar, User $participant): void
    {
        $message = new WebinarStartNotificationEmail(
            $participant->email,
            $webinar->name,
            Carbon::parse($webinar->start_at->toDateTimeString())->format('d-m-Y H:i'),
            env('APP_URL') . '/' . 'conventions' . '/' . $webinar->getKey(),
            $participant->pivot->personal_webinar_url
        );
        Mail::to($participant->email)->send($message);
    }
}
