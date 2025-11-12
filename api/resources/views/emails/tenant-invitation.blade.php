<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f4f4f4; padding: 20px; border-radius: 5px;">
        <h2 style="color: #444; margin-top: 0;">You've been invited!</h2>

        <p>Hello,</p>

        <p>You've been invited to join our platform. Click the button below to accept your invitation and create your account.</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $acceptUrl }}"
               style="background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                Accept Invitation
            </a>
        </div>

        <p style="color: #666; font-size: 14px;">
            This invitation will expire on {{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}.
        </p>

        <p style="color: #666; font-size: 14px;">
            If you didn't expect this invitation, you can safely ignore this email.
        </p>

        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

        <p style="color: #999; font-size: 12px;">
            If the button above doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $acceptUrl }}" style="color: #007bff; word-break: break-all;">{{ $acceptUrl }}</a>
        </p>
    </div>
</body>
</html>
