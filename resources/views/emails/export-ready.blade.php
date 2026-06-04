@php
    /** @var string $filename */
    /** @var string|null $url */
    /** @var string|null $subject */
    $title = $subject ?? 'Your export is ready';
    $appName = config('app.name', 'Our app');
    $hasLink = ! empty($url);
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Client resets */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { text-decoration: none; }

        /* Mobile */
        @media only screen and (max-width: 600px) {
            .sm-w-full { width: 100% !important; }
            .sm-px-24 { padding-left: 24px !important; padding-right: 24px !important; }
            .sm-py-32 { padding-top: 32px !important; padding-bottom: 32px !important; }
            .sm-btn { display: block !important; width: 100% !important; }
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .body-bg { background-color: #0b1220 !important; }
            .card { background-color: #111827 !important; }
            .text, .heading { color: #e5e7eb !important; }
            .muted { color: #9ca3af !important; }
            .chip { background-color: #1f2937 !important; color: #e5e7eb !important; }
            .divider { border-color: #1f2937 !important; }
        }
    </style>
</head>
<body class="body-bg" style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    {{-- Hidden preheader (preview text in the inbox) --}}
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #f3f4f6;">
        @if ($hasLink)
            {{ $filename }} is ready — download it from {{ $appName }}.
        @else
            {{ $filename }} is ready and attached to this email.
        @endif
        &#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="body-bg" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 32px 16px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" class="sm-w-full" style="width: 600px; max-width: 600px;">

                    {{-- Brand --}}
                    <tr>
                        <td style="padding: 0 8px 16px; font-size: 14px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280;" class="muted">
                            {{ $appName }}
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td class="card sm-py-32 sm-px-24" style="background-color: #ffffff; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

                            <h1 class="heading" style="margin: 0 0 16px; font-size: 22px; line-height: 1.3; color: #111827;">
                                Your export is ready 🎉
                            </h1>

                            <p class="text" style="margin: 0 0 8px; font-size: 16px; line-height: 1.6; color: #374151;">
                                @if ($hasLink)
                                    Your file has finished generating and is ready to download.
                                @else
                                    Your file has finished generating and is attached to this email.
                                @endif
                            </p>

                            {{-- File chip --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 16px 0 24px;">
                                <tr>
                                    <td class="chip" style="background-color: #f3f4f6; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #111827; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; word-break: break-all;">
                                        📄&nbsp;&nbsp;{{ $filename }}
                                    </td>
                                </tr>
                            </table>

                            @if ($hasLink)
                                {{-- Bulletproof CTA button --}}
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;" class="sm-w-full">
                                    <tr>
                                        <td align="center" bgcolor="#4472C4" style="border-radius: 8px;">
                                            <!--[if mso]>
                                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:280px;" arcsize="16%" stroke="f" fillcolor="#4472C4">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">Download file</center>
                                            </v:roundrect>
                                            <![endif]-->
                                            <!--[if !mso]><!-- -->
                                            <a href="{{ $url }}" class="sm-btn" target="_blank" rel="noopener" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 700; color: #ffffff; background-color: #4472C4; border-radius: 8px; text-decoration: none;">
                                                Download file
                                            </a>
                                            <!--<![endif]-->
                                        </td>
                                    </tr>
                                </table>

                                <p class="muted" style="margin: 0; font-size: 13px; line-height: 1.6; color: #6b7280;">
                                    If the button doesn't work, copy and paste this link into your browser:
                                </p>
                                <p style="margin: 4px 0 0; font-size: 13px; line-height: 1.6; word-break: break-all;">
                                    <a href="{{ $url }}" target="_blank" rel="noopener" style="color: #4472C4; text-decoration: underline;">{{ $url }}</a>
                                </p>
                            @else
                                <p class="muted" style="margin: 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                    Look for the attachment on this message to save your file.
                                </p>
                            @endif

                            <hr class="divider" style="border: none; border-top: 1px solid #e5e7eb; margin: 28px 0 0;">

                            <p class="muted" style="margin: 20px 0 0; font-size: 12px; line-height: 1.6; color: #9ca3af;">
                                You're receiving this email because an export was requested for your account. If you didn't request it, you can safely ignore this message.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 8px; text-align: center;">
                            <p class="muted" style="margin: 0; font-size: 12px; line-height: 1.6; color: #9ca3af;">
                                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
