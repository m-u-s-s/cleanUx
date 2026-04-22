<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Notification CleanUx' }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
                    <tr>
                        <td style="padding:24px 28px;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;">
                            <div style="font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#cbd5e1;font-weight:700;">{{ $eyebrow ?? 'CleanUx' }}</div>
                            <div style="margin-top:8px;font-size:28px;line-height:1.2;font-weight:800;">{{ $title ?? 'Notification' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @if(!empty($intro))
                                <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#334155;">{{ $intro }}</p>
                            @endif

                            @if(!empty($highlight))
                                <div style="margin:0 0 18px;padding:14px 16px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:14px;line-height:1.6;">
                                    {{ $highlight }}
                                </div>
                            @endif

                            @if(!empty($details))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border-collapse:separate;border-spacing:0 10px;">
                                    @foreach($details as $detail)
                                        <tr>
                                            <td style="width:160px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;font-size:13px;color:#64748b;font-weight:700;">{{ $detail['label'] }}</td>
                                            <td style="padding:10px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;font-size:14px;color:#0f172a;font-weight:600;">{{ $detail['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if(!empty($actionText) && !empty($actionUrl))
                                <div style="margin:24px 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 18px;border-radius:14px;background:#0ea5e9;color:#fff;text-decoration:none;font-size:14px;font-weight:700;">{{ $actionText }}</a>
                                </div>
                            @endif

                            @if(!empty($outro))
                                <p style="margin:18px 0 0;font-size:14px;line-height:1.7;color:#475569;">{{ $outro }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.6;color:#64748b;">
                            {{ $footer ?? 'CleanUx — plateforme de gestion des interventions.' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
