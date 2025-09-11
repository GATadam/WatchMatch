<div id="content">
    <h2>Settings</h2>
    <form method="POST" action="dashboard/update_profile.php">
        <div class="login_field">
            <label for="pf_bg_color">Choose your region!</label>
            <select name="region" id="region">
                <?php
                $stmt = $pdo->prepare("SELECT region_id FROM " . getenv('DB_TABLE_U') . " WHERE id = ?");
                $stmt->execute([$user['id']]);
                $profile_region = $stmt->fetchColumn();
                $stmt = $pdo->query("SELECT id, name FROM " . getenv('DB_TABLE_R') . " ORDER BY name");
                while ($region = $stmt->fetch()) {
                    $selected = ($region['id'] == $profile_region) ? 'selected' : '';
                    echo "<option value=\"{$region['id']}\" $selected>{$region['name']}</option>";
                }
                ?>
            </select>
        </div>
        <br>
        <div class="login_field">
            <label for="pf_icon">Choose your icon</label>
            <select name="icon" id="pf_icon">
            <?php
            $letters = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
            $startIndex = array_search($profile_icon, $letters);
            if ($startIndex === false) $startIndex = 0;
            for ($i = $startIndex; $i < count($letters); $i++) {
                $selected = ($letters[$i] === $profile_icon) ? 'selected' : '';
                echo "<option value=\"{$letters[$i]}\" $selected>{$letters[$i]}</option>";
            }
            for ($i = 0; $i < $startIndex; $i++) {
                echo "<option value=\"{$letters[$i]}\">{$letters[$i]}</option>";
            }
            ?>
        </select>
        </div>
        <br>
        <div class="login_field">
            <label for="pf_bg_color">Profile Background Color:</label>
            <input type="color" name="bg_color" id="pf_bg_color" value="#<?php echo $profile_icon_bg_color; ?>">
        </div>
        <br>
        <div class="login_field">
            <label for="pf_icon_color">Profile Icon Color:</label>
            <input type="color" name="icon_color" id="pf_icon_color" value="#<?php echo $profile_icon_color; ?>">
        </div>
        <br>
        <button type="submit" id="save_profile_btn" class="btn">Save Changes</button>
    </form>
</div>