<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Endless Compound</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #030712; color: #f1f5f9; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #111827; border-radius: 16px; overflow: hidden; border: 1px solid #1f2937; }
        .header { background: #1e1b4b; padding: 40px 32px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; color: #a5b4fc; }
        .header .emoji { font-size: 48px; display: block; margin-bottom: 12px; }
        .body { padding: 32px; }
        .body p { color: #cbd5e1; line-height: 1.7; margin: 0 0 16px; }
        .body .username { color: #fff; font-weight: 600; }
        .elements { display: flex; gap: 8px; flex-wrap: wrap; margin: 24px 0; }
        .element { background: #1e293b; border: 1px solid #334155; border-radius: 9999px; padding: 6px 14px; font-size: 14px; color: #e2e8f0; }
        .cta { text-align: center; margin: 32px 0 16px; }
        .cta a { background: #4f46e5; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px; display: inline-block; }
        .footer { padding: 20px 32px; border-top: 1px solid #1f2937; text-align: center; }
        .footer p { color: #475569; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="emoji">⚗️</span>
            <h1>Welcome to Endless Compound!</h1>
        </div>
        <div class="body">
            <p>Hey <span class="username">{{ $username }}</span>,</p>
            <p>
                You've just joined <strong>Endless Compound</strong> — the game where you combine elements
                to discover new ones. Start with the four basics and see how far you can go.
            </p>
            <div class="elements">
                <span class="element">💧 Water</span>
                <span class="element">🔥 Fire</span>
                <span class="element">🌍 Earth</span>
                <span class="element">💨 Air</span>
            </div>
            <p>
                Every new combination you discover is saved globally — be the first to find something
                and your name will be attached to it forever. 🏆
            </p>
            <div class="cta">
                <a href="{{ config('app.url') }}">Start playing →</a>
            </div>
        </div>
        <div class="footer">
            <p>Endless Compound · support.endlesscompound@gmail.com</p>
        </div>
    </div>
</body>
</html>
