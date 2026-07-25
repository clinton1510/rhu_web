<?php
// Shared Mailer module for RedPulse RHU.
// Uses authenticated SMTP as the primary delivery method.

if (!function_exists('sendRHUEmail')) {
    function sendRHUEmail(string $toEmail, string $subject, string $htmlContent): array {
        @include_once __DIR__ . '/db.php';

        $mailSettings = [];
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $mailSettings = $pdo->query("SELECT setting_key, setting_value FROM portal_settings WHERE setting_key LIKE 'smtp_%'")
                    ->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            } catch (Throwable $e) {
                error_log('Mailer settings: ' . $e->getMessage());
            }
        }
        $smtpHost = trim((string)($mailSettings['smtp_host'] ?? '')) ?: (function_exists('rhuEnv') ? rhuEnv('SMTP_HOST', 'smtp.gmail.com') : 'smtp.gmail.com');
        $smtpPort = (int)(trim((string)($mailSettings['smtp_port'] ?? '')) ?: (function_exists('rhuEnv') ? rhuEnv('SMTP_PORT', '587') : 587));
        $smtpUser = trim((string)($mailSettings['smtp_user'] ?? '')) ?: (function_exists('rhuEnv') ? (rhuEnv('SMTP_USER', '') ?: '') : '');
        $smtpPass = function_exists('rhuEnv') ? (rhuEnv('SMTP_PASS', '') ?: rhuEnv('SMTP_PASSWORD', '')) : '';
        $smtpPass = str_replace(' ', '', $smtpPass);
        $smtpFrom = function_exists('rhuEnv') ? (rhuEnv('SMTP_FROM', '') ?: ($smtpUser ?: 'no-reply@nasugbu.rhu.gov.ph')) : 'no-reply@nasugbu.rhu.gov.ph';
        $smtpName = 'RedPulse RHU Security';

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'method' => 'validation', 'error' => 'Invalid recipient email address.'];
        }

        $logPath = dirname(__DIR__, 2) . '/sent_emails.txt';
        $safeSubject = trim(str_replace(["\r", "\n"], '', $subject));
        $logPrefix = "[" . date('Y-m-d H:i:s') . "] TO: {$toEmail} | SUBJECT: {$safeSubject} | ";

        if ($smtpUser !== '' && $smtpPass !== '') {
            try {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                        'peer_name' => $smtpHost,
                    ]
                ]);

                $remote = ($smtpPort === 465) ? "ssl://{$smtpHost}:{$smtpPort}" : "tcp://{$smtpHost}:{$smtpPort}";
                $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);

                if ($socket) {
                    $read = function($sock) {
                        $res = '';
                        while ($line = fgets($sock, 512)) {
                            $res .= $line;
                            if (substr($line, 3, 1) === ' ') break;
                        }
                        return $res;
                    };

                    $write = function($sock, $cmd) use ($read) {
                        fputs($sock, $cmd . "\r\n");
                        return $read($sock);
                    };

                    $expect = function(string $response, array $codes, string $stage): void {
                        $code = (int)substr($response, 0, 3);
                        if (!in_array($code, $codes, true)) {
                            throw new RuntimeException("SMTP {$stage} failed with response code {$code}.");
                        }
                    };

                    $expect($read($socket), [220], 'connection');
                    $expect($write($socket, 'EHLO localhost'), [250], 'EHLO');

                    if ($smtpPort === 587) {
                        $expect($write($socket, 'STARTTLS'), [220], 'STARTTLS');
                        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                            throw new RuntimeException('SMTP TLS negotiation failed.');
                        }
                        $expect($write($socket, 'EHLO localhost'), [250], 'secure EHLO');
                    }

                    $expect($write($socket, 'AUTH LOGIN'), [334], 'authentication');
                    $expect($write($socket, base64_encode($smtpUser)), [334], 'username');
                    $expect($write($socket, base64_encode($smtpPass)), [235], 'password');

                    $expect($write($socket, "MAIL FROM: <{$smtpFrom}>"), [250], 'sender');
                    $expect($write($socket, "RCPT TO: <{$toEmail}>"), [250, 251], 'recipient');
                    $expect($write($socket, 'DATA'), [354], 'message data');

                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: {$smtpName} <{$smtpFrom}>\r\n";
                    $headers .= "To: <{$toEmail}>\r\n";
                    $headers .= "Subject: {$safeSubject}\r\n";
                    $headers .= "Date: " . date('r') . "\r\n";
                    $headers .= "Message-ID: <" . bin2hex(random_bytes(12)) . "@redpulse-rhu.local>\r\n";

                    $dotSafeHtml = preg_replace('/(?m)^\\./', '..', $htmlContent);
                    $expect($write($socket, $headers . "\r\n" . $dotSafeHtml . "\r\n."), [250], 'delivery');
                    $write($socket, 'QUIT');
                    fclose($socket);

                    @file_put_contents($logPath, $logPrefix . "SUCCESS (SMTP)\n", FILE_APPEND);
                    return ['success' => true, 'method' => 'SMTP'];
                }
                $message = "SMTP connection failed ({$errno}).";
                @file_put_contents($logPath, $logPrefix . "FAILED ({$message})\n", FILE_APPEND);
                return ['success' => false, 'method' => 'SMTP', 'error' => $message];
            } catch (Throwable $e) {
                error_log('sendRHUEmail SMTP Exception: ' . $e->getMessage());
                @file_put_contents($logPath, $logPrefix . "FAILED (" . $e->getMessage() . ")\n", FILE_APPEND);
                return ['success' => false, 'method' => 'SMTP', 'error' => $e->getMessage()];
            }
        }

        $message = 'SMTP credentials are not configured.';
        @file_put_contents($logPath, $logPrefix . "FAILED ({$message})\n", FILE_APPEND);
        return ['success' => false, 'method' => 'SMTP', 'error' => $message];
    }
}
