<?php
function smtpReadResponse($socket) {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtpExpect($socket, $expectedCodes) {
    $response = smtpReadResponse($socket);
    $code = (int)substr($response, 0, 3);
    return in_array($code, (array)$expectedCodes, true);
}

function smtpCommand($socket, $command, $expectedCodes) {
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCodes);
}

function taskqSendResetEmail($to, $displayName, $code) {
    $smtpHost = 'ssl://smtp.gmail.com';
    $smtpPort = 465;
    $smtpUser = 'jawad.alluses@gmail.com';
    $smtpPass = 'rvnbdafzckvltmls';
    $fromEmail = 'jawad.alluses@gmail.com';
    $fromName = 'Task-Q';

    $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $subject = 'Task-Q password reset code';
    $boundary = 'taskq_' . bin2hex(random_bytes(8));

    $html = "<!DOCTYPE html>
<html>
<body style='margin:0;padding:0;background:#eaf4ff;font-family:Arial,sans-serif;'>
  <div style='max-width:520px;margin:30px auto;background:#1358ec;color:#ffffff;border-radius:18px;padding:34px;text-align:center;'>
    <h1 style='margin:0 0 8px;font-size:30px;'>Task-Q</h1>
    <p style='margin:0 0 24px;color:#dbeafe;'>Password reset code</p>
    <p style='margin:0 0 16px;'>Hello {$safeName},</p>
    <p style='margin:0 0 16px;'>Your password digit is</p>
    <div style='display:inline-block;padding:16px 24px;margin:8px 0 20px;border-radius:14px;background:rgba(255,255,255,0.18);color:#ffffff;font-size:36px;font-weight:800;letter-spacing:8px;'>{$safeCode}</div>
    <p style='margin:0;color:#dbeafe;font-size:13px;'>This code expires in 10 minutes. If you did not request it, ignore this email.</p>
  </div>
</body>
</html>";

    $plain = "Task-Q password reset\n\nHello {$displayName},\n\nYour password digit is: {$code}\n\nThis code expires in 10 minutes.";

    $headers = [];
    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $message = implode("\r\n", $headers) . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain . "\r\n\r\n";
    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n\r\n";
    $message .= '--' . $boundary . "--\r\n";
    $message = preg_replace('/^\./m', '..', $message);

    $socket = @stream_socket_client($smtpHost . ':' . $smtpPort, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 20);

    $ok = smtpExpect($socket, 220)
        && smtpCommand($socket, 'EHLO localhost', 250)
        && smtpCommand($socket, 'AUTH LOGIN', 334)
        && smtpCommand($socket, base64_encode($smtpUser), 334)
        && smtpCommand($socket, base64_encode($smtpPass), 235)
        && smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250)
        && smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251])
        && smtpCommand($socket, 'DATA', 354);

    if ($ok) {
        fwrite($socket, $message . "\r\n.\r\n");
        $ok = smtpExpect($socket, 250);
    }

    smtpCommand($socket, 'QUIT', 221);
    fclose($socket);

    return $ok;
}
?>