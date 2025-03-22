<?php

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$config = require 'config.php';

// Logging function (prints to terminal and writes to file)
function logMessage($message) {
    $logFile = __DIR__ . '/logs/project_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

$nextDay = date('Y/m-d', strtotime('+1 day'));
$apiUrl = $config['api_base_url'] . "$nextDay" . "_" . $config['region'] . ".json";

// Fetch API Data
$jsonData = file_get_contents($apiUrl);
if ($jsonData === false) {
    logMessage("Failed to fetch API data from $apiUrl");
    die("Failed to fetch the API data.");
}

// Decode and Process API Data
$data = json_decode($jsonData, true);
if ($data === null) {
    logMessage("Failed to decode JSON data from $apiUrl.");
    die("Failed to decode JSON data.");
}

$highPrices = [];
foreach ($data as $priceInfo) {
    $hour = date('H:i', strtotime($priceInfo['time_start']));
    $price = $priceInfo['SEK_per_kWh'];

    if ($price > $config['price_threshold']) {
        $highPrices[$hour] = $price;
    }
}

$nextDayMail = date('Y-m-d', strtotime('+1 day'));
$priceThreshold = $config['price_threshold'];

if (!empty($highPrices)) {
    logMessage("High prices detected, preparing email notification.");

    $message = "
<html>
<head>
<title>Varning höga elpriser</title>
</head>
<body>
<h2>Höga elpriser imorgon ($nextDayMail)</h2>
<p>Följande timmar har priser över $priceThreshold kr/kWh:</p>
<ul>
";

foreach ($highPrices as $hour => $price) {
    $message .= "<li><strong>Timme: $hour</strong> - Pris: $price SEK/kWh</li>";
}

$message .= "
</ul>
<p>Vänligen försök att reducera elanvändningen under timmarna ovan för att spara kostnader.</p>
</body>
</html>
";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['EMAIL_USERNAME'];
    $mail->Password = $_ENV['EMAIL_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['SMTP_PORT'];

    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->setFrom($_ENV['EMAIL_USERNAME'], $_ENV['EMAIL_FROM_NAME']);

    $emailRecipientsArray = explode(',', $_ENV['EMAIL_RECIPIENTS']);

    foreach ($emailRecipientsArray as $recipient) {
        $mail->addAddress(trim($recipient));
    }
    
    $mail->isHTML(true);
    $mail->Subject = "Varning för höga elpriser för $nextDayMail";
    $mail->Body    = $message;

    $mail->send();
    echo "Alert emails sent.\n";
    $emailRecipientsString = implode(',', $emailRecipientsArray);
    logMessage("Alert emails sent to receivers: $emailRecipientsString");
} catch (Exception $e) {
    $errorMessage = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    logMessage($errorMessage);
    die($errorMessage);
}
} else {
    logMessage("No high prices detected for $nextDay.");
}
