<?php
    // Fetch current (non-archived) branding data
    $stmt = $dbConnection->prepare("SELECT logo_image_path, logo_text FROM branding WHERE archived = 0 ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $branding = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set default branding values if none exist
    $currentLogoImage = $branding['logo_image_path'] ?? './img/darky-1.png';
    $currentLogoText = $branding['logo_text'] ?? 'Learnify';
?>

<a href="./studentDash.php" class="brand">
    <img class="logo_img" src="<?php echo htmlspecialchars($currentLogoImage); ?>" alt="Logo">
    <span class="text" id="logo_text"><?php echo htmlspecialchars($currentLogoText); ?></span>
</a>