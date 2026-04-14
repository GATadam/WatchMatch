<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $plainPassword = $_POST['password'] ?? '';
    $region = $_POST['region'] ?? '';

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
    $usersTable = getenv('DB_TABLE_U');

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        die("Database connection failed, try again later.");
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email address.");
    }

    if ($username === '' || $plainPassword === '' || $region === '') {
        die("Missing required registration data.");
    }

    $password = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . $usersTable . " WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        die("Email already registered.");
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . $usersTable . " WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        die("Username already taken.");
    }

    do {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . $usersTable . " WHERE verification_token = ?");
        $stmt->execute([$token]);
    } while ($stmt->fetchColumn() > 0);

    $to = $email;
    $subject = "Please verify your email address";
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
            <a style="background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 15px; border-radius: 5px;" href="https://kosmicdoom.com/watchmatch/verify_email.php?token=' . $token . '">Verify registration</a>
            <br>
            <img src="https://kosmicdoom.com/watchmatch_media/logo.png" alt="WatchMatch Logo" style="max-width: 200px; height: auto;" />
            </div>
        </body>
        </html>
        ';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: watchmatch@kosmicdoom.com\r\n";
    $headers .= "Reply-To: watchmatch@kosmicdoom.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO " . $usersTable . " (email, verification_token, email_verified, username, password_hash, region_id, profil_icon, icon_color, icon_bg_color) VALUES (?, ?, 0, ?, ?, ?, 'a', 'ffffff', '000000')");
        $stmt->execute([$email, $token, $username, $password, $region]);

        if (!mail($to, $subject, $message, $headers)) {
            $pdo->rollBack();
            echo "Error occurred while sending email.";
            exit;
        }

        $pdo->commit();
        echo "Successfully created an account! Please verify your email.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo "Registration failed. Please try again later.";
    }
}
?>
