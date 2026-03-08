<x-mail::message>
# Log in to Poopers

Tap the button below to log in to your Poopers account. This link expires {{ $expiresAt }}.

<x-mail::button :url="$url">
Log In to Poopers
</x-mail::button>

If you didn't request this link, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
