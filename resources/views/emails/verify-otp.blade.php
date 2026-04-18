<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 40px; }
        .container { background: #fff; max-width: 480px; margin: auto; padding: 40px; border-radius: 8px; }
        .otp { font-size: 42px; font-weight: bold; letter-spacing: 12px; color: #1a1a1a; text-align: center; margin: 32px 0; }
        .note { color: #888; font-size: 13px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hi {{ $userName }},</h2>
        <p>Use the code below to verify your email address. It expires in <strong>10 minutes</strong>.</p>
        <div class="otp">{{ $otp }}</div>
        <p class="note">If you didn't create an account, you can safely ignore this email.</p>
    </div>
</body>
</html>