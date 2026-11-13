<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1d1d1f; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d2d2d7; }
        .muted { color: #6e6e73; }
        .header { border-bottom: 2px solid #1d1d1f; padding-bottom: 8px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: bold; color: #6e6e73; font-size: 9px; padding: 4px 5px; border-bottom: 1px solid #d2d2d7; }
        td { padding: 4px 5px; border-bottom: 1px solid #ececf0; vertical-align: top; }
        .right { text-align: right; }
        .kpis td { width: 25%; border: 1px solid #d2d2d7; padding: 8px; }
        .kpi-value { font-size: 16px; font-weight: bold; }
        .ok { color: #1a7f37; } .late { color: #b25000; } .bad { color: #c9252d; }
        .footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #86868b; }
    </style>
</head>
<body>
    <div class="footer">EVENT TRANS · ACCES EVENT × {{ $company ?? '' }} · généré le {{ now()->format('d/m/Y à H\hi') }}</div>
    @yield('content')
</body>
</html>
