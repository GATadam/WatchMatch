<?php

function watchmatchRenderAuthFeedbackPage(array $config): void
{
    $tone = $config['tone'] ?? 'info';
    $eyebrow = $config['eyebrow'] ?? 'Watchmatch';
    $title = $config['title'] ?? 'Status update';
    $message = $config['message'] ?? '';
    $detail = $config['detail'] ?? '';
    $actions = $config['actions'] ?? [];
    $statusCode = (int) ($config['status_code'] ?? 200);

    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');

    $safeEyebrow = htmlspecialchars((string) $eyebrow, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
    $safeDetail = htmlspecialchars((string) $detail, ENT_QUOTES, 'UTF-8');

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $safeTitle; ?> - WATCHMATCH</title>
    <link rel="icon" type="image/x-icon" href="https://www.kosmicdoom.com/watchmatch_media/logo.png">
    <link rel="stylesheet" href="styles/main_style.css">
    <link rel="stylesheet" href="styles/login_style.css">
    <link rel="stylesheet" href="styles/feedback_style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body class="auth_body auth_feedback_body">
    <main class="auth_feedback_shell">
        <section class="auth_card auth_feedback_card">
            <a class="auth_brand auth_brand_compact auth_feedback_brand" href="index.php">
                <img src="https://www.kosmicdoom.com/watchmatch_media/logo.png" alt="Watchmatch">
                <span>WATCHMATCH</span>
            </a>

            <span class="auth_feedback_badge tone-<?php echo htmlspecialchars((string) $tone, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $safeEyebrow; ?>
            </span>

            <h1><?php echo $safeTitle; ?></h1>
            <p class="auth_feedback_message"><?php echo $safeMessage; ?></p>

            <?php if ($safeDetail !== ''): ?>
                <p class="auth_feedback_detail"><?php echo $safeDetail; ?></p>
            <?php endif; ?>

            <?php if ($actions): ?>
                <div class="auth_feedback_actions">
                    <?php foreach ($actions as $action): ?>
                        <?php
                        $href = htmlspecialchars((string) ($action['href'] ?? 'login.php'), ENT_QUOTES, 'UTF-8');
                        $label = htmlspecialchars((string) ($action['label'] ?? 'Continue'), ENT_QUOTES, 'UTF-8');
                        $isSecondary = ($action['variant'] ?? 'primary') === 'secondary';
                        $variant = $isSecondary ? 'secondary_action auth_feedback_action_secondary' : 'auth_feedback_action_primary';
                        ?>
                        <a class="action_button auth_feedback_action <?php echo $variant; ?>" href="<?php echo $href; ?>"><span><?php echo $label; ?></span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}
