<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify Your Email - SukiCart</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: #f3f4f6;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: #111827;
        }
        .wrap {
            max-width: 560px;
            margin: 48px auto;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #052e16 0%, #14532d 50%, #166534 100%);
            padding: 40px 48px 36px;
            text-align: center;
        }
        .logo-text {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.4px;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.4px;
            line-height: 1.25;
            margin-bottom: 8px;
        }
        .header h1 span { color: #6ee7b7; }
        .header-sub {
            font-size: 13px;
            color: #a7f3d0;
        }
        .body {
            padding: 40px 48px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }
        .intro {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.75;
            margin-bottom: 32px;
        }
        .cta-wrap { text-align: center; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #166534, #16a34a);
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(22,101,52,0.30);
        }
        .expiry {
            text-align: center;
            margin-bottom: 32px;
        }
        .expiry span {
            display: inline-block;
            background: #fef9c3;
            border: 1px solid #fde68a;
            border-radius: 999px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
            color: #92400e;
        }
        hr {
            border: none;
            border-top: 1px solid #f3f4f6;
            margin: 28px 0;
        }
        .fallback {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
        }
        .fallback p {
            font-size: 12px;
            color: #9ca3af;
            margin-bottom: 8px;
        }
        .fallback-url {
            font-size: 11px;
            color: #6b7280;
            word-break: break-all;
            line-height: 1.6;
        }
        .footer {
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
            padding: 24px 48px;
            text-align: center;
        }
        .footer p {
            font-size: 11px;
            color: #d1d5db;
            line-height: 1.7;
        }
        @media only screen and (max-width: 600px) {
            .header, .body, .footer { padding-left: 24px; padding-right: 24px; }
            .header h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="wrap">

        <div class="header">
            <div class="logo-text">SukiCart</div>
            <h1>Verify Your<br><span>Email Address</span></h1>
            <p class="header-sub">One quick step to activate your SukiCart account</p>
        </div>

        <div class="body">
            <p class="greeting">Hi {{ $user->firstname }},</p>
            <p class="intro">
                Welcome to SukiCart � your trusted online shop!
                Click the button below to verify your email and unlock your account.
                Fresh deals from local sellers are waiting for you.
            </p>

            <div class="cta-wrap">
                <a href="{{ $frontendUrl }}/verify-email?token={{ $token }}" class="btn">
                    Verify My Email Address
                </a>
            </div>

            <div class="expiry">
                <span>This link expires in 5 minutes</span>
            </div>

            <hr />

            <div class="fallback">
                <p>Button not working? Copy and paste this link into your browser:</p>
                <span class="fallback-url">{{ $frontendUrl }}/verify-email?token={{ $token }}</span>
            </div>
        </div>

        <div class="footer">
            <p>
                &copy; {{ date('Y') }} SukiCart � Your trusted online shop<br>
                If you didn't create this account, you can safely ignore this email.
            </p>
        </div>

    </div>
</body>
</html>
