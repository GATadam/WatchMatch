<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $region = $_POST['region'];
    //$token = bin2hex(random_bytes(32));

    $envPath = '/web/htdocs/www.kosmicdoom.com/home/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath);
        foreach ($lines as $line) {
            if (trim($line) && strpos($line, '=') !== false) {
                putenv(trim($line));
            }
        }
    }
    $dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
    $user = getenv('DB_USER_NAME');
    $pass = getenv('DB_PASSWORD');
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        die("Database connection failed, try again later.");
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        die("Email already registered.");
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        die("Username already taken.");
    }
    // elméletileg ez korábban le lett kezelve, de a hibák elkerülése érdekében itt is megnézzük
    do {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE verification_token = ?");
        $stmt->execute([$token]);
    } while ($stmt->fetchColumn() > 0);

    $stmt = $pdo->prepare("INSERT INTO Users (email, verification_token, email_verified, username, password_hash, region_id, profil_icon, icon_color, icon_bg_color) VALUES (?, ?, 0, ?, ?, ?, 0, '#ffffff', '#000000')");
    $stmt->execute([$email, $token, $username, $password, $region]);

    $to = $email;
    $subject = "Please verify your email address";
    // TODO: szépíteni css-sel, most van placeholder
    $message = '
        <html>
        <head>
            <title>Registration Confirmation</title>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Arial, sans-serif; color: #ffffff; background-color: #0A0A14; padding: 20px;">
            <div style="max-width: 600px; margin: 50px auto; padding: 20px; background-color: #111827; border-radius: 15px; text-align: justify;">
            <p style="color: #ffffff;">Thank you for creating an account for WatchMatch, we appreciate your support!</p>
            <p style="color: #ffffff;">Please confirm your registration by clicking on the following link:</p>
            <a style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 15px; border-radius: 5px;" href="https://kosmicdoom.com/watchmatch/verify_email.php?token=' . $token . '" >Verify registration</a>
            <br>
            <img src="https://kosmicdoom.com/watchmatch_media/logo.png" alt="WatchMatch Logo" style="max-width: 200px; height: auto;" />
            </div>
        </body>
        </html>
        ';
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    // ez a két sor kell a html-hez ^
    $headers .= "From: noreply@watchmatch.com\r\n";
    $headers .= "Reply-To: noreply@watchmatch.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    if(mail($to, $subject, $message, $headers)) {
        echo "Successfully created an account! Please verify your email.";
    } else {
        echo "Error occurred while sending email.";
    }
}
?>