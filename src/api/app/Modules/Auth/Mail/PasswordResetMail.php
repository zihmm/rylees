<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function build(): self
    {
        return $this->subject('Reset your Rylees password')
            ->markdown('emails.password-reset', [
                'firstname' => $this->user->profile->firstname,
                'url' => 'https://console.rylees.ai/reset-password?token='.$this->user->password_reset_token,
            ]);
    }
}
