@component('mail::message')
# Hello {{ $firstname }}!

Thank you for registering with Rylees. Before you can log in, you need to
activate your account.

Click the button below to activate your account:

@component('mail::button', ['url' => $url])
Activate Account
@endcomponent

If the button does not work, copy and paste the following link into your browser:

{{ $url }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
