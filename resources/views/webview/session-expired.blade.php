<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session</title>
</head>
<body>
    <script>
        if (window.ReactNativeWebView) {
            window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'sessionExpired' }));
        }
    </script>
    <p style="font-family: sans-serif; padding: 24px;">Session expirée. Reconnexion…</p>
</body>
</html>
