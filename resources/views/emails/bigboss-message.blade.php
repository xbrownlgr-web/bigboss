<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $mailTitle }}</title>
</head>
<body style="margin:0;padding:16px 0;background:#F2F5F3;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
    <div style="max-width:600px;margin:0 auto;background:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #E2E8F0;">
        <div style="background:#0C2A1E;color:#FFFFFF;padding:24px 28px;text-align:center;">
            <div style="display:inline-block;padding:4px 12px;border-radius:999px;background:rgba(34,197,94,0.18);color:#86EFAC;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;">
                {{ $badgeText ?? 'BIG BOSS · PARIS SPORTIFS' }}
            </div>
            <h1 style="margin:0;font-size:22px;font-weight:800;letter-spacing:-0.3px;">{{ $mailTitle }}</h1>
        </div>

        <div style="padding:26px 28px;font-size:15px;line-height:1.65;color:#1E293B;">
            <p style="margin-top:0;">Bonjour <strong>{{ $recipientName }}</strong>,</p>

            @foreach ($paragraphs as $paragraph)
                <p style="margin:0 0 14px 0;">{!! nl2br(e($paragraph)) !!}</p>
            @endforeach

            @if (! empty($highlightBox))
                <div style="margin:18px 0;padding:16px 18px;border-radius:12px;background:#F0FDF4;border:1px solid #86EFAC;text-align:center;">
                    @if (! empty($highlightBox['badge']))
                        <div style="display:inline-block;padding:3px 10px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:11px;font-weight:800;margin-bottom:8px;">
                            {{ $highlightBox['badge'] }}
                        </div>
                    @endif
                    <div style="font-size:15px;font-weight:800;color:#0C2A1E;">{{ $highlightBox['title'] }}</div>
                    @if (! empty($highlightBox['subtitle']))
                        <div style="margin-top:6px;font-size:13.5px;color:#15803D;font-weight:700;">{{ $highlightBox['subtitle'] }}</div>
                    @endif
                </div>
            @endif

            @if (! empty($ctaLabel) && ! empty($ctaUrl))
                <div style="text-align:center;margin:22px 0 8px;">
                    <a href="{{ $ctaUrl }}" style="display:inline-block;padding:13px 26px;border-radius:10px;background:#22C55E;color:#FFFFFF;text-decoration:none;font-weight:800;font-size:15px;">
                        {{ $ctaLabel }}
                    </a>
                </div>
            @endif

            <p style="margin-top:20px;margin-bottom:0;color:#64748B;font-size:13px;">
                L’équipe BIG BOSS
            </p>
        </div>

        <div style="background:#F8FAFC;padding:16px 24px;text-align:center;color:#64748B;font-size:12px;border-top:1px solid #E2E8F0;">
            BIG BOSS{{ ($supportEmail = \App\Models\AppSetting::value('support_email')) ? ' · Support : ' . $supportEmail : '' }}<br>
            <span style="font-size:11px;color:#94A3B8;">Les pronostics sportifs comportent un risque de perte. Réservé aux 18 ans et plus.</span>
        </div>
    </div>
</body>
</html>
