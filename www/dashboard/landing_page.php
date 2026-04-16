<?php
$regionName = 'Unknown region';
$stmt = $pdo->prepare(
    'SELECT r.name
     FROM ' . getenv('DB_TABLE_U') . ' u
     LEFT JOIN ' . getenv('DB_TABLE_R') . ' r ON r.id = u.region_id
     WHERE u.id = ?'
);
$stmt->execute([$user['id']]);
$fetchedRegionName = $stmt->fetchColumn();
if ($fetchedRegionName) {
    $regionName = htmlspecialchars((string) $fetchedRegionName, ENT_QUOTES, 'UTF-8');
}
?>

<div id="content">
    <section class="home_hero">
        <p class="home_kicker">Welcome back</p>
        <h2>Home</h2>
        <p class="home_text">Everything important is one tap away, from updating your profile to opening a new match room with friends.</p>
    </section>

    <section class="home_summary_grid">
        <article class="home_summary_card">
            <p class="home_card_label">Current region</p>
            <h3><?php echo $regionName; ?></h3>
            <p class="home_card_text">Provider lists and rooms use this region when you create a new online match.</p>
        </article>

        <article class="home_summary_card">
            <p class="home_card_label">Quick tip</p>
            <h3>Use Match Online</h3>
            <p class="home_card_text">Pick one or more providers, invite a friend, then swipe until the first shared like becomes a match.</p>
        </article>
    </section>

    <section class="home_actions">
        <a class="home_action_card" href="?p=match_online">
            <p class="home_card_label">Start matching</p>
            <h3>Open Match Online</h3>
            <p class="home_card_text">Create a room, join a friend, and keep swiping together.</p>
        </a>

        <a class="home_action_card" href="?p=friends">
            <p class="home_card_label">Stay connected</p>
            <h3>Manage Friends</h3>
            <p class="home_card_text">Check requests, browse friends, and search for new people to add.</p>
        </a>

        <a class="home_action_card" href="?p=settings">
            <p class="home_card_label">Make it yours</p>
            <h3>Update Profile</h3>
            <p class="home_card_text">Refresh your icon, colors, and region from a cleaner profile screen.</p>
        </a>
    </section>
</div>
