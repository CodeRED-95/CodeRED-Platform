<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica tu correo electrónico - CodeRED</title>
</head>
<body style="margin:0;background:#0f1117;color:#e8eaf0;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0f1117;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#191d27;border:1px solid #303746;border-radius:16px;overflow:hidden;">
            <tr><td style="padding:28px 32px;background:#df1d48;color:#fff;font-size:22px;font-weight:700;">CodeRED</td></tr>
            <tr><td style="padding:32px;">
                <h1 style="margin:0 0 16px;color:#fff;font-size:26px;">Verifica tu correo electrónico</h1>
                <p style="margin:0 0 8px;color:#b6bdcc;">Hemos recibido una solicitud para verificar:</p>
                <p style="margin:0 0 24px;color:#fff;font-weight:700;">{{ $emailMasked }}</p>
                <p style="margin:0 0 8px;color:#b6bdcc;">Tu código de verificación es:</p>
                <p style="margin:0 0 24px;padding:16px;text-align:center;background:#242a38;border-radius:10px;color:#fff;font-size:32px;letter-spacing:8px;font-weight:700;">{{ $code }}</p>
                <p style="margin:0 0 8px;color:#b6bdcc;">Este código expira en {{ $expiresInMinutes }} minutos.</p>
                <p style="margin:24px 0 0;color:#8e97aa;font-size:13px;">Si no solicitaste esta cuenta, puedes ignorar este mensaje. No respondas a este correo.</p>
            </td></tr>
        </table>
        <p style="margin:18px 0 0;color:#737c90;font-size:12px;">CodeRED Platform · Perú</p>
    </td></tr>
</table>
</body>
</html>
