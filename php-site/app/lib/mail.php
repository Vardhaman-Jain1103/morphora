<?php
/*
 * Notification emails via PHP mail() — works on Hostinger once the "from" address exists on your
 * domain (hPanel → Emails). Failures are logged, never shown to visitors: the lead is already saved.
 */
function send_mail(string $to, string $subject, string $text, ?string $replyTo = null): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var(explode(',', $to)[0], FILTER_VALIDATE_EMAIL)) return false;
    $from = (string)cfg('mail_from', 'no-reply@' . (parse_url((string)cfg('base_url'), PHP_URL_HOST) ?: 'localhost'));
    $name = setting('site_name', 'Morphora Spaces');
    $headers = [
        'From: ' . mb_encode_mimeheader($name . ' Website') . " <{$from}>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: Morphora',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = "Reply-To: {$replyTo}";
    $ok = @mail($to, mb_encode_mimeheader($subject), $text, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) error_log("Morphora: could not send mail to {$to}: {$subject}");
    return $ok;
}

function notify_enquiry(array $d, int $id): void
{
    $lines = [];
    foreach (['Name' => 'name', 'Company' => 'company', 'Phone' => 'phone', 'Email' => 'email', 'City' => 'city', 'Office size' => 'size', 'Service' => 'service', 'Message' => 'message', 'From page' => 'page', 'Campaign' => 'utm'] as $label => $k) {
        if (!empty($d[$k])) $lines[] = str_pad($label . ':', 13) . $d[$k];
    }
    $src = $d['source'] === 'chat' ? 'chat assistant' : 'enquiry form';
    $text = "New enquiry from the website {$src}.\n\n" . implode("\n", $lines) .
        "\n\nOpen in admin: " . abs_url('/admin/?r=enquiries&id=' . $id) . "\n";
    send_mail(setting('notify_email', setting('email')), 'New enquiry: ' . $d['name'] . ($d['city'] ? ' (' . $d['city'] . ')' : ''), $text, $d['email'] ?: null);
}
