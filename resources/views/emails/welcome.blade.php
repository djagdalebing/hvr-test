<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Welcome to {{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2b2b33;">
    <span style="display:none;max-height:0;overflow:hidden;opacity:0;">Welcome aboard — here's everything you can do on {{ $siteName }} 🎬✨</span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:92%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,0.06);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#5b2a86 0%,#9b2f6e 55%,#F65F54 100%);padding:28px 32px;text-align:center;">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" height="40" style="height:40px;max-height:40px;display:inline-block;">
                            @else
                                <div style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:1px;">{{ $siteName }}</div>
                            @endif
                        </td>
                    </tr>

                    <!-- Hero -->
                    <tr>
                        <td style="padding:0;">
                            <img src="{{ $heroUrl }}" alt="" width="600" style="width:100%;max-width:600px;display:block;border:0;">
                        </td>
                    </tr>

                    <!-- Welcome -->
                    <tr>
                        <td style="padding:32px 32px 8px;text-align:center;">
                            <h1 style="margin:0 0 8px;font-size:26px;line-height:1.25;color:#1d1d24;">
                                Welcome, {{ $displayName }}! 🎉
                            </h1>
                            <p style="margin:0;font-size:16px;line-height:1.6;color:#54545f;">
                                We're so glad you're here. 💜 {{ $siteName }} is your home for films, series and
                                stories from creators worth watching — grab your popcorn 🍿 and dive in.
                            </p>
                        </td>
                    </tr>

                    <!-- What you can do -->
                    <tr>
                        <td style="padding:18px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:10px 0;font-size:15px;line-height:1.5;color:#2b2b33;">
                                        🎬 <strong>Watch</strong> — stream movies &amp; series from independent creators.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-top:1px solid #efeff3;font-size:15px;line-height:1.5;color:#2b2b33;">
                                        🌟 <strong>Discover creators</strong> — follow the people behind the stories.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-top:1px solid #efeff3;font-size:15px;line-height:1.5;color:#2b2b33;">
                                        💬 <strong>Join the community</strong> — share thoughts and connect with others.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0;border-top:1px solid #efeff3;font-size:15px;line-height:1.5;color:#2b2b33;">
                                        🎥 <strong>Become a creator</strong> — upload your own work and reach an audience.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CTAs -->
                    <tr>
                        <td style="padding:18px 32px 8px;text-align:center;">
                            <a href="{{ $browseUrl }}" style="display:inline-block;background:#F65F54;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 28px;border-radius:8px;margin:6px 4px;">▶️ Start watching</a>
                            <a href="{{ $creatorsUrl }}" style="display:inline-block;background:#2b2b33;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 28px;border-radius:8px;margin:6px 4px;">✨ Explore creators</a>
                        </td>
                    </tr>

                    <!-- Sign-off -->
                    <tr>
                        <td style="padding:20px 32px 8px;text-align:center;">
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#54545f;">
                                Any questions? Just reply to this email — we'd love to hear from you. 😊
                            </p>
                            <p style="margin:14px 0 0;font-size:15px;color:#2b2b33;">
                                With love,<br><strong>The {{ $siteName }} team 💜</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 32px 28px;text-align:center;border-top:1px solid #efeff3;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#9a9aa5;">
                                You're receiving this because you created an account on {{ $siteName }}.<br>
                                Manage your preferences in your <a href="{{ $settingsUrl }}" style="color:#9b2f6e;text-decoration:none;">account settings</a>.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
