<p>Hi,</p>

<p>{{ $inviter->name }} invited you to join <strong>{{ $household->name }}</strong> on {{ config('app.name') }}.</p>

@if ($invite->player)
<p>You'll be connected to the player <strong>{{ $invite->player->name }}</strong> and can see the games you've played.</p>
@endif

<p>To accept, open this link:</p>

<p><a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></p>

<p>This invitation expires on {{ $invite->expires_at->format('F j, Y') }}.</p>

<p>If you weren't expecting this invitation, you can ignore this email.</p>
