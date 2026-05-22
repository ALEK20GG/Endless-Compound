<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Invite – Endless Compound</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #030712; color: #f1f5f9; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #111827; border-radius: 16px; overflow: hidden; border: 1px solid #1f2937; }
        .header { background: #1e1b4b; padding: 40px 32px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; color: #a5b4fc; }
        .header .emoji { font-size: 48px; display: block; margin-bottom: 12px; }
        .body { padding: 32px; }
        .body p { color: #cbd5e1; line-height: 1.7; margin: 0 0 16px; }
        .body strong { color: #fff; }
        .code-box { background: #0f172a; border: 1px solid #4f46e5; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
        .code-box .label { color: #6366f1; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .code-box .code { font-size: 36px; font-weight: 700; font-family: monospace; color: #fff; letter-spacing: 6px; }
        .cta { text-align: center; margin: 24px 0 16px; }
        .cta a { background: #4f46e5; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px; display: inline-block; }
        .footer { padding: 20px 32px; border-top: 1px solid #1f2937; text-align: center; }
        .footer p { color: #475569; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="emoji">👥</span>
            <h1>You've been invited to play!</h1>
        </div>
        <div class="body">
            <p>
                <strong>{{ $inviterName }}</strong> has invited you to join their room
                <strong>"{{ $roomName }}"</strong> on Endless Compound.
            </p>
            <p>Use this code to join:</p>
            <div class="code-box">
                <div class="label">Room Code</div>
                <div class="code">{{ $roomCode }}</div>
            </div>
            <p>Or click the button below to join directly:</p>
            <div class="cta">
                <a href="{{ $joinUrl }}">Join the room →</a>
            </div>
            <p style="font-size: 13px; color: #475569;">
                Don't have an account yet? You'll need to create one first at
                <a href="{{ config('app.url') }}" style="color: #6366f1;">{{ config('app.url') }}</a>
            </p>
        </div>
        <div class="footer">
            <p>Endless Compound · support.endlesscompound@gmail.com</p>
        </div>
    </div>
</body>
</html>
