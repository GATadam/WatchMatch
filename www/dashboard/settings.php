<?php
$letters = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));

$stmt = $pdo->prepare('SELECT region_id FROM ' . getenv('DB_TABLE_U') . ' WHERE id = ?');
$stmt->execute([$user['id']]);
$profileRegionId = (int) $stmt->fetchColumn();

$stmt = $pdo->query('SELECT id, name FROM ' . getenv('DB_TABLE_R') . ' ORDER BY name');
$regions = $stmt->fetchAll();

$selectedRegionName = '';
foreach ($regions as $region) {
    if ((int) $region['id'] === $profileRegionId) {
        $selectedRegionName = htmlspecialchars((string) $region['name'], ENT_QUOTES, 'UTF-8');
        break;
    }
}
?>

<div id="content">
    <div class="page_intro">
        <h2>Profile</h2>
        <p class="match_online_helper">Customize how you appear across the dashboard, friends list, and online matching rooms.</p>
    </div>

    <form method="POST" action="dashboard/update_profile.php" class="settings_form">
        <div class="settings_layout">
            <section class="settings_panel settings_preview_panel">
                <p class="home_card_label">Live preview</p>
                <div class="settings_preview_card">
                    <span
                        id="settings_preview_icon"
                        class="settings_preview_icon"
                        style="color: #<?php echo $profile_icon_color; ?>; background-color: #<?php echo $profile_icon_bg_color; ?>;"
                    ><?php echo $profile_icon; ?></span>
                    <div>
                        <h3><?php echo $username; ?></h3>
                        <p id="settings_preview_region" class="match_online_helper"><?php echo $selectedRegionName ? 'Region: ' . $selectedRegionName : 'Choose your region'; ?></p>
                    </div>
                </div>
            </section>

            <section class="settings_panel">
                <p class="home_card_label">Basics</p>
                <div class="settings_field">
                    <label for="region">Choose your region</label>
                    <select name="region" id="region">
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo (int) $region['id']; ?>" <?php echo (int) $region['id'] === $profileRegionId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $region['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="settings_color_grid">
                    <div class="settings_field">
                        <label for="pf_bg_color">Profile background color</label>
                        <input type="color" name="bg_color" id="pf_bg_color" value="#<?php echo $profile_icon_bg_color; ?>">
                    </div>

                    <div class="settings_field">
                        <label for="pf_icon_color">Profile icon color</label>
                        <input type="color" name="icon_color" id="pf_icon_color" value="#<?php echo $profile_icon_color; ?>">
                    </div>
                </div>
            </section>

            <section class="settings_panel settings_icon_panel">
                <div class="settings_panel_header">
                    <div>
                        <p class="home_card_label">Icon picker</p>
                        <h3>Choose your icon</h3>
                    </div>
                </div>

                <input type="hidden" name="icon" id="selected_icon" value="<?php echo $profile_icon; ?>">

                <div id="icon_picker_grid" class="icon_picker_grid" role="listbox" aria-label="Choose your icon">
                    <?php foreach ($letters as $letter): ?>
                        <?php $isSelected = $letter === html_entity_decode($profile_icon, ENT_QUOTES, 'UTF-8'); ?>
                        <button
                            type="button"
                            class="icon_picker_button<?php echo $isSelected ? ' is-selected' : ''; ?>"
                            data-icon="<?php echo htmlspecialchars($letter, ENT_QUOTES, 'UTF-8'); ?>"
                            aria-pressed="<?php echo $isSelected ? 'true' : 'false'; ?>"
                        ><?php echo htmlspecialchars($letter, ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <button type="submit" id="save_profile_btn" class="btn settings_submit">Save Changes</button>
    </form>
</div>

<script>
(() => {
    const previewIcon = document.getElementById('settings_preview_icon');
    const previewRegion = document.getElementById('settings_preview_region');
    const iconGrid = document.getElementById('icon_picker_grid');
    const hiddenIconInput = document.getElementById('selected_icon');
    const regionSelect = document.getElementById('region');
    const bgColorInput = document.getElementById('pf_bg_color');
    const iconColorInput = document.getElementById('pf_icon_color');

    function syncPreview() {
        previewIcon.textContent = hiddenIconInput.value || 'a';
        previewIcon.style.backgroundColor = bgColorInput.value;
        previewIcon.style.color = iconColorInput.value;

        const regionLabel = regionSelect.options[regionSelect.selectedIndex]
            ? regionSelect.options[regionSelect.selectedIndex].text
            : 'Choose your region';
        previewRegion.textContent = 'Region: ' + regionLabel;
    }

    iconGrid.addEventListener('click', (event) => {
        const button = event.target.closest('.icon_picker_button');
        if (!button) {
            return;
        }

        hiddenIconInput.value = button.getAttribute('data-icon') || 'a';

        iconGrid.querySelectorAll('.icon_picker_button').forEach((iconButton) => {
            iconButton.classList.toggle('is-selected', iconButton === button);
            iconButton.setAttribute('aria-pressed', iconButton === button ? 'true' : 'false');
        });

        syncPreview();
    });

    regionSelect.addEventListener('change', syncPreview);
    bgColorInput.addEventListener('input', syncPreview);
    iconColorInput.addEventListener('input', syncPreview);

    syncPreview();
})();
</script>
