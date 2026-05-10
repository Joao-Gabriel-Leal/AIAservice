<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $subject ?? config('app.name') }}</title>
    </head>
    <body style="margin:0;background:#edf2f7;padding:0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#edf2f7;margin:0;padding:32px 16px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;border-collapse:collapse;">
                        <tr>
                            <td style="border-radius:18px;background:#ffffff;padding:32px;border:1px solid #dbe3ef;box-shadow:0 16px 38px rgba(15,23,42,.08);">
                                {!! $html !!}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
