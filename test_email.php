<?php

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();



// Create PHPMailer instance
$mail = new PHPMailer(true);

$mail->SMTPDebug = 2;  // 2 for detailed debug output, 0 for silent
$mail->Debugoutput = 'html';  // Show debug output in the browser

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['EMAIL_USERNAME'];
    $mail->Password = $_ENV['EMAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['SMTP_PORT'];

    // Email details
    $mail->setFrom($_ENV['EMAIL_USERNAME'], $_ENV['EMAIL_FROM_NAME']);
    $mail->addAddress('lindskold.daniel@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = "Test Email from Hostinger Setup";
    $mail->Body    = "<h1>Success!</h1><p>This is a test email sent using PHPMailer with your new Hostinger setup.</p>";

    $mail->send();
    echo "Test email sent successfully!\n";
} catch (Exception $e) {
    error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    die("Email could not be sent.");
}
