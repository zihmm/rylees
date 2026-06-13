@component('mail::message')
# Hello {{ $firstname }}!

We received a request to reset the password for your Rylees account.

Click the button below to choose a new password:

@component('mail::button', ['url' => $url])
Reset Password
@endcomponent

This link expires in 60 minutes. If you did not request a password reset, you
can safely ignore this email — your password will remain unchanged.

If the button does not work, copy and paste the following link into your browser:

{{ $url }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
