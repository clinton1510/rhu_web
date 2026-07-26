<?php
// Shared Mailer module for ResiHUnity RHU.
// Uses HTTPS Mail API (FormSubmit HTTPS relay) for guaranteed real-world email delivery on local XAMPP environments,
// as well as direct SMTP socket transport and PHP mail().

if (!function_exists('sendRHUEmail')) {
    function sendRHUEmail(string $toEmail, string $subject, string $htmlContent): array {
        @include_once __DIR__ . '/db.php';

        $smtpHost = function_exists('rhuEnv') ? rhuEnv('SMTP_HOST', 'smtp.gmail.com') : 'smtp.gmail.com';
        $smtpPort = function_exists('rhuEnv') ? (int) rhuEnv('SMTP_PORT', '587') : 587;
        $smtpUser = function_exists('rhuEnv') ? (rhuEnv('SMTP_USER', '') ?: '') : '';
        $smtpPass = function_exists('rhuEnv') ? (rhuEnv('SMTP_PASS', '') ?: rhuEnv('SMTP_PASSWORD', '')) : '';
        $smtpPass = str_replace(' ', '', $smtpPass);
        $smtpFrom = function_exists('rhuEnv') ? (rhuEnv('SMTP_FROM', '') ?: ($smtpUser ?: 'no-reply@nasugbu.rhu.gov.ph')) : 'no-reply@nasugbu.rhu.gov.ph';
        $smtpName = 'ResiHUnity RHU Security';

        // Extract raw code if present
        preg_match('/class=[\'"]code-box[\'"]>([0-9]{6})</', $htmlContent, $codeMatches);
        $rawCode = $codeMatches[1] ?? '';

        // Log locally to sent_emails.txt for record keeping
        $logPath = dirname(__DIR__, 2) . '/sent_emails.txt';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] TO: {$toEmail} | CODE: {$rawCode} | SUBJECT: {$subject}\n";
        @file_put_contents($logPath, $logEntry, FILE_APPEND);

        // 1. Direct SMTP Socket Delivery (If SMTP_USER & SMTP_PASS are set in .env)
        if ($smtpUser !== '' && $smtpPass !== '') {
            try {
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
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

                    $read($socket);
                    $write($socket, 'EHLO ' . gethostname());

                    if ($smtpPort === 587) {
                        $write($socket, 'STARTTLS');
                        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                        $write($socket, 'EHLO ' . gethostname());
                    }

                    $write($socket, 'AUTH LOGIN');
                    $write($socket, base64_encode($smtpUser));
                    $write($socket, base64_encode($smtpPass));

                    $write($socket, "MAIL FROM: <{$smtpFrom}>");
                    $write($socket, "RCPT TO: <{$toEmail}>");
                    $write($socket, 'DATA');

                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: {$smtpName} <{$smtpFrom}>\r\n";
                    $headers .= "To: <{$toEmail}>\r\n";
                    $headers .= "Subject: {$subject}\r\n";
                    $headers .= "Date: " . date('r') . "\r\n";

                    $write($socket, $headers . "\r\n" . $htmlContent . "\r\n.");
                    $write($socket, 'QUIT');
                    fclose($socket);

                    return ['success' => true, 'method' => 'SMTP'];
                }
            } catch (Exception $e) {
                error_log('sendRHUEmail SMTP Exception: ' . $e->getMessage());
            }
        }

        // 2. HTTPS Web Mail API Relay (FormSubmit HTTPS endpoint) for Instant Real-World Inbox Delivery
        if (function_exists('curl_init') && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $apiUrl = 'https://formsubmit.co/ajax/' . urlencode($toEmail);
                $postFields = [
                    '_subject' => $subject,
                    'System_Sender' => 'ResiHUnity RHU Administrator Security',
                    'Recipient' => $toEmail,
                    '2FA_Verification_Code' => $rawCode ?: 'Generated Code',
                    'Message' => "Your 2-Factor Authentication (2FA) verification code for ResiHUnity RHU Admin Login is: {$rawCode}. Valid for 10 minutes.",
                    '_template' => 'table'
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    return ['success' => true, 'method' => 'HTTPS_API'];
                }
            } catch (Exception $ex) {
                error_log('sendRHUEmail HTTP API Error: ' . $ex->getMessage());
            }
        }

        // 3. Native PHP mail() fallback
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$smtpName} <{$smtpFrom}>\r\n";
        $headers .= "Reply-To: {$smtpFrom}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $sent = @mail($toEmail, $subject, $htmlContent, $headers);
        return ['success' => $sent, 'method' => 'php_mail'];
    }
}
