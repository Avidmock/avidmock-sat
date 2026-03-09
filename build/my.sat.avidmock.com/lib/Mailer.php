<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AVIDMOCK · Mailer
 *  SMTP email with branded HTML templates.
 * ═══════════════════════════════════════════════════════════════════
 */

class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $headers = [
            'From'         => SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
            'Reply-To'     => SMTP_FROM,
            'MIME-Version'  => '1.0',
            'Content-Type'  => 'text/html; charset=UTF-8',
            'X-Mailer'     => 'Avidmock/4.0',
        ];

        $wrapped = self::wrapInTemplate($htmlBody);

        // Use SMTP in production, mail() in dev
        if (AVIDMOCK_ENV === 'development') {
            return mail($to, $subject, $wrapped, implode("\r\n", array_map(
                fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers
            )));
        }

        return self::sendSmtp($to, $subject, $wrapped, $headers);
    }

    private static function sendSmtp(string $to, string $subject, string $body, array $headers): bool
    {
        try {
            $socket = fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
            if (!$socket) return false;

            self::smtpCmd($socket, '', 220);
            self::smtpCmd($socket, 'EHLO avidmock.com', 250);
            self::smtpCmd($socket, 'AUTH LOGIN', 334);
            self::smtpCmd($socket, base64_encode(SMTP_USER), 334);
            self::smtpCmd($socket, base64_encode(SMTP_PASS), 235);
            self::smtpCmd($socket, 'MAIL FROM:<' . SMTP_FROM . '>', 250);
            self::smtpCmd($socket, 'RCPT TO:<' . $to . '>', 250);
            self::smtpCmd($socket, 'DATA', 354);

            $headerStr = '';
            foreach ($headers as $k => $v) $headerStr .= "{$k}: {$v}\r\n";
            $headerStr .= "To: {$to}\r\nSubject: {$subject}\r\n";

            fwrite($socket, $headerStr . "\r\n" . $body . "\r\n.\r\n");
            self::smtpCmd($socket, 'QUIT', 221);
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            error_log("Mailer error: " . $e->getMessage());
            return false;
        }
    }

    private static function smtpCmd($socket, string $cmd, int $expect): string
    {
        if ($cmd) fwrite($socket, $cmd . "\r\n");
        $response = fgets($socket, 1024);
        if ((int) substr($response, 0, 3) !== $expect) {
            throw new RuntimeException("SMTP error: expected {$expect}, got: {$response}");
        }
        return $response;
    }

    public static function sendVerifyEmail(int $userId): bool
    {
        $user = User::findById($userId);
        if (!$user) return false;

        $link = STUDENT_URL . '/auth/verify-email.php?token=' . urlencode($user['verification_token']);

        return self::send($user['email'], 'Verify your Avidmock account', "
            <h2 style='color: #143230; margin-bottom: 8px;'>Welcome to Avidmock, {$user['name']}!</h2>
            <p style='color: #6B7280; font-size: 16px; line-height: 1.6;'>
                You're one step away from your personalized SAT prep journey.
                Click the button below to verify your email and get started.
            </p>
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$link}' style='display: inline-block; padding: 14px 40px;
                   background: #1FE290; color: #143230; font-weight: 700; font-size: 16px;
                   text-decoration: none; border-radius: 8px;'>
                    Verify My Email
                </a>
            </div>
            <p style='color: #9CA3AF; font-size: 13px;'>
                If the button doesn't work, copy this link:<br>
                <a href='{$link}' style='color: #1FE290; word-break: break-all;'>{$link}</a>
            </p>
        ");
    }

    public static function sendPasswordReset(int $userId): bool
    {
        $user = User::findById($userId);
        if (!$user) return false;

        $token = User::setResetToken($userId);
        $link = STUDENT_URL . '/auth/reset-password.php?token=' . urlencode($token);

        return self::send($user['email'], 'Reset your Avidmock password', "
            <h2 style='color: #143230;'>Password Reset</h2>
            <p style='color: #6B7280; font-size: 16px; line-height: 1.6;'>
                We received a request to reset your password. Click below to create a new one.
                This link expires in 1 hour.
            </p>
            <div style='text-align: center; margin: 32px 0;'>
                <a href='{$link}' style='display: inline-block; padding: 14px 40px;
                   background: #1FE290; color: #143230; font-weight: 700;
                   text-decoration: none; border-radius: 8px;'>Reset Password</a>
            </div>
            <p style='color: #9CA3AF; font-size: 13px;'>If you didn't request this, just ignore this email.</p>
        ");
    }

    public static function sendWeeklyReport(int $parentId, string $reportHtml): bool
    {
        $parent = User::findById($parentId);
        if (!$parent) return false;

        return self::send($parent['email'], 'Weekly SAT Prep Report — Avidmock', $reportHtml);
    }

    private static function wrapInTemplate(string $content): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='utf-8'><meta name='viewport' content='width=device-width'></head>
        <body style='margin: 0; padding: 0; background: #F0FAF4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", sans-serif;'>
            <div style='max-width: 560px; margin: 0 auto; padding: 40px 24px;'>
                <!-- Logo -->
                <div style='text-align: center; margin-bottom: 32px;'>
                    <span style='font-size: 24px; font-weight: 800; color: #143230; letter-spacing: -0.5px;'>
                        <span style='color: #1FE290;'>●</span> avidmock
                    </span>
                </div>
                <!-- Card -->
                <div style='background: #FFFFFF; border-radius: 16px; padding: 40px 32px;
                            box-shadow: 0 2px 12px rgba(20,50,48,0.06);'>
                    {$content}
                </div>
                <!-- Footer -->
                <div style='text-align: center; margin-top: 24px; color: #9CA3AF; font-size: 12px;'>
                    <p>Avidmock SAT Prep · The smartest way to ace the SAT</p>
                    <p><a href='" . STUDENT_URL . "' style='color: #1FE290;'>my.sat.avidmock.com</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
}