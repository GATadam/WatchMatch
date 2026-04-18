<?php

function watchmatchSendMultipartMail(string $to, string $subject, string $textMessage, string $htmlMessage, array $options = []): bool
{
    $to = trim($to);
    $subject = trim($subject);
    $fromName = trim((string) ($options['from_name'] ?? 'Watchmatch'));
    $fromEmail = trim((string) ($options['from_email'] ?? 'watchmatch@kosmicdoom.com'));
    $replyTo = trim((string) ($options['reply_to'] ?? $fromEmail));

    if ($to === '' || $subject === '' || $fromEmail === '') {
        return false;
    }

    $boundary = 'watchmatch_' . bin2hex(random_bytes(12));
    $domain = 'kosmicdoom.com';
    if (str_contains($fromEmail, '@')) {
        [, $emailDomain] = explode('@', $fromEmail, 2);
        if ($emailDomain !== '') {
            $domain = $emailDomain;
        }
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $escapedFromName = str_replace(['\\', '"'], ['\\\\', '\"'], $fromName);

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Date: " . date(DATE_RFC2822) . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(16)) . "@{$domain}>\r\n";
    $headers .= "From: \"{$escapedFromName}\" <{$fromEmail}>\r\n";
    $headers .= "Reply-To: \"{$escapedFromName}\" <{$replyTo}>\r\n";
    $headers .= "Sender: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Auto-Response-Suppress: All\r\n";
    $headers .= "Auto-Submitted: auto-generated\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textMessage . "\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlMessage . "\r\n";
    $message .= "--{$boundary}--\r\n";

    $extraParams = '';
    if (preg_match('/^[^\\r\\n]+@[^\\r\\n]+$/', $fromEmail)) {
        $extraParams = '-f' . $fromEmail;
    }

    if ($extraParams !== '') {
        return mail($to, $encodedSubject, $message, $headers, $extraParams);
    }

    return mail($to, $encodedSubject, $message, $headers);
}
