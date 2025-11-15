<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/email_config.php';

function sendHtmlMail($toEmail, $toName, $subject, $htmlBody)
{

    if (EMAIL_TO_LOG_ONLY) {
        append_email_log("TO: {$toEmail}, NAME: {$toName}, SUBJECT: {$subject}");
        return true;
    }

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();
        append_email_log("Mengirim ke {$toEmail}");
        
        return true;

    } catch (Exception $e) {
        append_email_log("Gagal kirim ke {$toEmail}: {$e->getMessage()}");
        return false;
    }

}

function sendBulkMails($users, $subject, $template)
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->isHTML(true);
    $mail->SMTPKeepAlive = true; // penting

    $sent = 0;
    foreach ($users as $u) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($u['email'], $u['name']);
            $mail->Subject = $subject;
            $mail->Body = str_replace('{{name}}', $u['name'], $template);
            if ($mail->send()) {
                $sent++;
            }
            append_email_log("SENT {$u['email']}");
            usleep(300000); // 0.3 detik
        } catch (Exception $e) {
            append_email_log("FAILED {$u['email']}: " . $e->getMessage());
        }
    }
    $mail->smtpClose(); // tutup koneksi setelah selesai
    return $sent;
}

function append_email_log($message)
{
    file_put_contents(
        EMAIL_LOG_FILE,
        "[" . date('Y-m-d H:i:s') . "] " . $message . "\n",
        FILE_APPEND
    );
}