<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Verifikasi Email AORTAEDU</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0"
                   style="background:#ffffff;border-radius:14px;overflow:hidden;
                          box-shadow:0 8px 24px rgba(0,0,0,0.08);">
                <tr>
                    <td style="background:#0b57a4;padding:22px 30px;">
                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <img src="https://aorta-edu.com/_next/static/media/logo.977180bb.png"
                                         alt="AORTAEDU" width="48"
                                         style="display:block;border-radius:10px;background:#ffffff;padding:6px;">
                                </td>
                                <td style="padding-left:14px;color:#ffffff;">
                                    <div style="font-size:18px;font-weight:700;letter-spacing:0.5px;">
                                        AORTAEDU
                                    </div>
                                    <div style="font-size:12px;opacity:0.9;">
                                        Learns great things everyday
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:36px 32px;color:#1f2937;">
                        <h2 style="margin:0 0 14px;font-size:22px;">Verifikasi Email</h2>

                        <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#4b5563;">
                            Hai <strong>{{ $user->name ?? 'Pengguna' }}</strong>,<br>
                            Terima kasih telah mendaftar di AORTAEDU.  
                            Silakan klik tombol di bawah ini untuk memverifikasi email Anda.
                        </p>

                        <div style="margin:28px 0;text-align:center;">
                            <a href="{{ $url }}"
                               style="display:inline-block;background:#0b57a4;color:#ffffff;
                                      text-decoration:none;padding:14px 26px;border-radius:10px;
                                      font-size:14px;font-weight:600;">
                                Verifikasi Email
                            </a>
                        </div>

                        <p style="margin:0 0 12px;font-size:13px;color:#6b7280;">
                            Jika tombol di atas tidak berfungsi, salin link berikut:
                        </p>

                        <p style="word-break:break-all;font-size:12px;color:#2563eb;">
                            {{ $url }}
                        </p>

                        <p style="margin-top:24px;font-size:12px;color:#9ca3af;">
                            Link ini akan kedaluwarsa dalam {{ $expire }} menit.
                            Jika Anda tidak melakukan pendaftaran, silakan abaikan email ini.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="background:#f8fafc;padding:18px;text-align:center;font-size:12px;color:#9ca3af;">
                        © {{ date('Y') }} AORTAEDU · All rights reserved
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
