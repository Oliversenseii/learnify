<?php
if (isset($_GET['data'])) {
    $data = $_GET['data'];
    $filename = "QR_Code_" . urlencode($data) . ".png";
    
    // Generate QR code using an external API
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($data);
    
    // Fetch QR code image
    $qrImage = file_get_contents($qrCodeUrl);
    
    if ($qrImage) {
        // Send headers for file download
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $qrImage;
        exit;
    } else {
        echo "Failed to generate QR code.";
    }
} else {
    echo "No QR code data provided.";
}
?>
