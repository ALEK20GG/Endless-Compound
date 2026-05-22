<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Code – Endless Compound</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #030712; color: #f1f5f9; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #111827; border-radius: 16px; overflow: hidden; border: 1px solid #1f2937; }
        .header { background: #1e1b4b; padding: 32px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; color: #a5b4fc; }
        .body { padding: 32px; }
        .body p { color: #cbd5e1; line-height: 1.7; margin: 0 0 16px; }
        .code-box { background: #0f172a; border: 1px solid #4f46e5; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
        .code-box .code { font-size: 40px; font-weight: 700; font-family: monospace; color: #fff; letter-spacing: 8px; }
        .code-box .expires { color: #6366f1; font-size: 12px; margin-top: 8px; }
        .footer { padding: 16px 32px; border-top: 1px solid #1f2937; text-align: center; }
        .footer p { color: #475569; font-size: 12px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚗️ Your login code</h1>
        </div>
        <div class="body">
            <p>Hey <strong>{{ $username }}</strong>,</p>
            <p>Use this code to complete your login to Endless Compound:</p>
            <div class="code-box">
                <div class="code">{{ $otp }}</div>
                <div class="expires">Expires in 10 minutes</div>
            </div>
            <p style="font-size: 13px; color: #475569;">
                If you didn't request this code, you can safely ignore this email.
            </p>
        </div>
        <div class="footer">
            <p>Endless Compound · support.endlesscompound@gmail.com</p>
        </div>
    </div>
</body>
</html>
