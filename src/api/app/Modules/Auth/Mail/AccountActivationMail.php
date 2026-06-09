<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class AccountActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function build(): self
    {
        return $this->subject('Activate your Rylees account')
            ->markdown('emails.account-activation', [
                'firstname' => $this->user->profile->firstname,
                'url' => 'https://console.rylees.ai/activate?token='.$this->user->activation_token,
            ]);
    }
}
