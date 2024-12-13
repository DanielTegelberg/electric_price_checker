<?php

require 'vendor/autoload.php';

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

logMessage("Script execution started.");

$nextDay = date('Y/m-d', strtotime('+1 day'));
$apiUrl = $config['api_base_url'] . "$nextDay" . "_" . $config['region'] . ".json";
logMessage("Generated API URL: $apiUrl");

// Fetch API Data
$jsonData = file_get_contents($apiUrl);
if ($jsonData === false) {
    logMessage("Failed to fetch API data from $apiUrl");
    die("Failed to fetch the API data.");
}

logMessage("API data fetched successfully.");

// Decode and Process API Data
$data = json_decode($jsonData, true);
if ($data === null) {
    logMessage("Failed to decode JSON data from $apiUrl.");
    die("Failed to decode JSON data.");
}

logMessage("API data decoded successfully.");

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
<title>Varning h�ga elpriser</title>
</head>
<body>
<h2>H�ga elpriser imorgon ($nextDayMail)</h2>
<p>F�ljande timmar har priser �ver $priceThreshold kr/kWh:</p>
<ul>
";

foreach ($highPrices as $hour => $price) {
    $message .= "<li><strong>Timme: $hour</strong> - Pris: $price SEK/kWh</li>";
}

$message .= "
</ul>
<p>V�nligen f�rs�k att reducera anv�ndningen av laddstolpen under timmarna ovan f�r att spara kostnader.</p>
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

    $mail->setFrom($_ENV['EMAIL_USERNAME'], $_ENV['EMAIL_FROM_NAME']);

    foreach ($_ENV['EMAIL_RECIPIENTS'] as $recipient) {
        $mail->addAddress($recipient);
    }
    
    $mail->isHTML(true);
    $mail->Subject = "Varning f�r h�ga elpriser f�r $nextDayMail";
    $mail->Body    = $message;

    $mail->send();
    echo "Alert emails sent.\n";
    $emailRecipientsString = implode(',', $config['email_recipients']);
    logMessage("Alert emails sent to receivers: $emailRecipientsString");
} catch (Exception $e) {
    $errorMessage = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
    logMessage($errorMessage);
    die($errorMessage);
}
} else {
    logMessage("No high prices detected for $nextDay.");
}
