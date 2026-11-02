<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A;">
    <div style="max-width:640px;margin:0 auto;background:#FFFFFF;">
        <div style="background:#1A2E5A;color:#FFFFFF;padding:20px 24px;">
            <div style="font-size:13px;color:#FFD700;font-weight:bold;letter-spacing:1px;">ADMINISTRATION BINHO FLASH</div>
            <h1 style="margin:6px 0 0;font-size:22px;">{{ $heading }}</h1>
        </div>
        <div style="padding:24px;">
            <p style="margin-top:0;font-size:15px;line-height:22px;">{{ $intro }}</p>

            @if ($details)
                <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
                    @foreach ($details as $label => $value)
                        @if ($value !== null && $value !== '')
                            <tr>
                                <td style="padding:9px 8px;border-bottom:1px solid #E0E0E0;color:#555555;">{{ $label }}</td>
                                <td style="padding:9px 8px;border-bottom:1px solid #E0E0E0;text-align:right;font-weight:bold;color:#1A2E5A;">{{ $value }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            @endif

            @if ($actionUrl)
                <p style="text-align:center;margin:24px 0 8px;">
                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#FFD700;color:#1A2E5A;font-weight:bold;text-decoration:none;padding:14px 26px;border-radius:10px;">{{ $actionLabel }}</a>
                </p>
            @endif
        </div>
        <div style="background:#F5F5F5;padding:16px;text-align:center;color:#888888;font-size:12px;">
            Alerte automatique · adresse modifiable dans Admin &gt; Paramètres &gt; Général
        </div>
    </div>
</body>
</html>
