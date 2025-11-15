<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/email_config.php';

function sendHtmlMail($toEmail, $toName, $subject, $htmlBody)
{

    if (EMAIL_TO_LOG_ONLY) {
        file_put_contents(
            EMAIL_LOG_FILE,
            "[" . date('Y-m-d H:i:s') . "] Kepada {$toEmail} ({$toName}):\n" .
            $htmlBody . "\n--------------------\n",
            FILE_APPEND
        );
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
        return true;

    } catch (Exception $e) {
        return false;
    }
}
