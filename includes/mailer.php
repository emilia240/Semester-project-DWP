<?php
/*
mailer.php  =  one function, send_mail(), that works in two modes (MAIL_MODE in constants.php):

  'log'   Nothing is sent. The e-mail is written to logs/mail.log.
          Use on your own computer: open the file, copy the link, paste it in the browser.
  'smtp'  Really sends the e-mail through the Simply.com mail server.
          Only works when the code runs ON Simply's web servers (they do not accept
          connections from home computers). Needs a mailbox from Simply's Mail Administration.

SMTP = Simple Mail Transfer Protocol = the language mail servers use to send e-mail.
PHPMailer is a well-known library that speaks SMTP for us (PHP's own mail() is unreliable and
Simply recommends not using it). It sits in libs/PHPMailer.
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function send_mail(string $to, string $subject, string $body): bool
{
    // Safety: a line break in these would let an attacker add extra e-mail headers ("header injection").
    // $to was checked with filter_var already, but we check again here because this is the last stop.
    if (preg_match('/[\r\n]/', $to . $subject) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        error_log('send_mail: refused suspicious address/subject');
        return false;
    }

    // ---------- mode 1: log ----------
    if (MAIL_MODE === 'log') {
        $entry = "==================== " . date('Y-m-d H:i:s') . " ====================\n"
               . "To:      $to\n"
               . "Subject: $subject\n\n"
               . $body . "\n\n";
        // FILE_APPEND = add to the end. LOCK_EX = two requests cannot write at the same moment.
        return file_put_contents(ROOT_PATH . '/logs/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    // ---------- mode 2: smtp ----------
    require_once ROOT_PATH . '/libs/PHPMailer/src/Exception.php';
    require_once ROOT_PATH . '/libs/PHPMailer/src/PHPMailer.php';
    require_once ROOT_PATH . '/libs/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer(true);        // true = throw exceptions when something fails
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;                          // websmtp.simply.com
        $mail->Port       = SMTP_PORT;                          // 587
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;     // start plain, then upgrade to an encrypted connection
        $mail->Timeout    = 10;                                 // give up after 10 seconds
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(false);                                   // plain text
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (MailException $ex) {
        // Details go to the log, never to the visitor.
        error_log('send_mail failed: ' . $mail->ErrorInfo);
        return false;
    }
}
