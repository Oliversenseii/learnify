<?php
require_once './sessions/session_student.php';
require_once '../../config/db_connection.php';

$file = isset($_GET['file']) ? basename(urldecode($_GET['file'])) : '';
if (empty($file) || !file_exists("../../uploads/modules/$file")) {
    die("Invalid or missing file.");
}

// Determine file type based on extension
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$supported_extensions = [
    'pdf', 'docx', 'pptx', 'xlsx', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'avif',
    'gif', 'svg', 'mp4', 'mp3', 'wav', 'zip', 'txt'
];
$is_supported = in_array($extension, $supported_extensions);
$is_pdf = $extension === 'pdf';
$is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg']);
$is_video = $extension === 'mp4';
$is_audio = in_array($extension, ['mp3', 'wav']);
$is_office = in_array($extension, ['docx', 'pptx', 'xlsx']);
$is_text = $extension === 'txt';
$is_table = in_array($extension, ['csv', 'xlsx']);

// MIME types for validation and embedding
$mime_types = [
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv' => 'text/csv',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'mp4' => 'video/mp4',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'zip' => 'application/zip',
    'txt' => 'text/plain'
];

// Construct the full file URL
$file_url = 'https://dihslearnify.wuaze.com/uploads/modules/' . $file;


// Function to parse CSV and generate HTML table
function renderCsvTable($file_path) {
    $table = '<table class="excel-table">';
    if (($handle = fopen($file_path, 'r')) !== false) {
        $header = fgetcsv($handle); // Get header row
        if ($header !== false) {
            $table .= '<thead><tr>';
            foreach ($header as $cell) {
                $table .= '<th>' . htmlspecialchars($cell) . '</th>';
            }
            $table .= '</tr></thead><tbody>';
            while (($row = fgetcsv($handle)) !== false) {
                $table .= '<tr>';
                foreach ($row as $cell) {
                    $table .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $table .= '</tr>';
            }
            $table .= '</tbody>';
        }
        fclose($handle);
    }
    $table .= '</table>';
    return $table;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Module File</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/learnify-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="./css/scrollbar.css">
    <script src="https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
    <style>
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #202124;
            overflow: auto;
            position: relative;
        }
        .modal-container {
            max-width: 1500px;
            width: 90vw;
            background-color: #F9F9F9;
            border-radius: 8px;
            box-shadow: 0 3px 7px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            position: relative;
            z-index: 10;
        }
        .modal-container.fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            max-width: none;
            border-radius: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            background-color: #fff;
            border-bottom: 1px solid #dadce0;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            z-index: 11;
        }
        .modal-header .file-title {
            font-size: clamp(1.5rem, 3.5vw, 1.8rem);
            font-weight: 500;
            color: #202124;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 50%;
        }
        .modal-header .button-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .modal-header .close-button, .modal-header .btn-copy, .modal-header .btn-fullscreen {
            color: #5f6368;
            text-decoration: none;
            font-size: 26px;
            line-height: 1;
            padding: 8px;
            border-radius: 4px;
            border: none;
            background: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .modal-header .btn-copy, .modal-header .btn-fullscreen {
            font-size: 16px;
            color: #1a73e8;
            padding: 8px 12px;
            display: flex;
            align-items: center;
        }
        .modal-header .close-button:hover, .modal-header .btn-copy:hover, .modal-header .btn-fullscreen:hover {
            background-color: #f1f3f4;
        }
        .modal-header .btn-copy i, .modal-header .btn-fullscreen i {
            margin-right: 6px;
        }
        .modal-content {
            padding: 16px;
            flex: 1;
            overflow: auto;
            font-size: 16px;
        }
        .modal-content embed, .modal-content img, .modal-content video, .modal-content audio {
            width: 100%;
            height: 75vh;
            border: none;
            display: block;
            z-index: 10;
        }
        .modal-content iframe {
            width: 100%;
            height: 85vh;
            border: none;
            display: block;
            z-index: 10;
        }
        .modal-content .docx-preview {
            width: 95%;
            max-height: 75vh;
            overflow-y: auto;
            background-color: #fff;
            padding: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .modal-content .docx-preview table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .modal-content .docx-preview table, 
        .modal-content .docx-preview th, 
        .modal-content .docx-preview td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .modal-content .docx-preview th {
            background-color: #f2f2f2;
            font-weight: 500;
        }
        .modal-content .docx-preview tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .modal-content .docx-preview tr:hover {
            background-color: #f1f3f4;
        }
        .modal-content .docx-preview p {
            margin: 8px 0;
            line-height: 1.5;
        }
        .modal-content .docx-preview hr {
            border: 0;
            border-top: 1px solid #ddd;
            margin: 16px 0;
        }
        .modal-content img, .modal-content svg {
            object-fit: contain;
            max-height: 75vh;
        }
        .modal-content video, .modal-content audio {
            max-height: 75vh;
        }
        .modal-container.fullscreen .modal-content embed,
        .modal-container.fullscreen .modal-content img,
        .modal-container.fullscreen .modal-content video,
        .modal-container.fullscreen .modal-content audio,
        .modal-container.fullscreen .modal-content .docx-preview {
            height: calc(100vh - 65px);
        }
        .modal-container.fullscreen .modal-content iframe {
            height: calc(100vh - 65px);
        }
        .modal-container.fullscreen .modal-content .excel-table {
            height: calc(100vh - 65px);
            width: 100%;
            overflow: auto;
        }
        .excel-table {
            width: 100%;
            height: 75vh;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            overflow: auto;
            font-size: 16px;
        }
        .excel-table th, .excel-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-size: 16px;
        }
        .excel-table th {
            background-color: #f2f2f2;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .excel-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .excel-table tr:hover {
            background-color: #f1f3f4;
        }
        .non-supported-message, .office-preview-error, .text-content, .table-content, .docx-preview-error {
            text-align: center;
            font-size: 16px;
            color: #5f6368;
        }
        .text-content pre {
            background-color: #f1f3f4;
            padding: 16px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 75vh;
            overflow-y: auto;
            font-size: 16px;
        }
        .non-supported-message a, .office-preview-error a, .text-content a, .table-content a, .docx-preview-error a {
            display: inline-flex;
            align-items: center;
            padding: 10px 18px;
            margin-top: 12px;
            background: linear-gradient(105deg, #007bff, #0056b3);
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        .non-supported-message a:hover, .office-preview-error a:hover, .text-content a:hover, .table-content a:hover, .docx-preview-error a:hover {
            background: linear-gradient(105deg, #0056b3, #003d80);
        }
        .non-supported-message a i, .office-preview-error a i, .text-content a i, .table-content a i, .docx-preview-error a i {
            margin-right: 6px;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #202124;
            color: #fff;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 16px;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            z-index: 3000;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
        }
        @media (max-width: 600px) {
            .modal-container {
                width: 95vw;
                margin: 10px;
            }
            .modal-container.fullscreen {
                margin: 0;
            }
            .modal-header {
                padding: 8px 12px;
                flex-direction: column;
                align-items: flex-start;
            }
            .modal-header .file-title {
                max-width: 100%;
                font-size: 16px;
                margin-bottom: 8px;
            }
            .modal-header .button-group {
                width: 100%;
                justify-content: flex-end;
            }
            .modal-header .btn-copy, .modal-header .btn-fullscreen {
                font-size: 14px;
                padding: 6px 10px;
            }
            .modal-header .close-button {
                font-size: 22px;
                padding: 6px;
            }
            .modal-content {
                padding: 12px;
                font-size: 14px;
            }
            .modal-content embed, .modal-content img, .modal-content video, .modal-content audio, .modal-content .docx-preview {
                height: 65vh;
            }
            .modal-content iframe {
                height: 75vh;
            }
            .modal-container.fullscreen .modal-content embed,
            .modal-container.fullscreen .modal-content img,
            .modal-container.fullscreen .modal-content video,
            .modal-container.fullscreen .modal-content audio,
            .modal-container.fullscreen .modal-content .docx-preview {
                height: calc(100vh - 80px);
            }
            .modal-container.fullscreen .modal-content iframe {
                height: calc(100vh - 80px);
            }
            .modal-container.fullscreen .modal-content .excel-table {
                height: calc(100vh - 80px);
            }
            .excel-table {
                height: 65vh;
                font-size: 14px;
            }
            .excel-table th, .excel-table td {
                font-size: 14px;
                padding: 8px;
            }
            .non-supported-message, .office-preview-error, .text-content, .table-content, .docx-preview-error {
                font-size: 14px;
            }
            .non-supported-message a, .office-preview-error a, .text-content a, .table-content a, .docx-preview-error a {
                padding: 6px 12px;
                font-size: 14px;
            }
            .text-content pre {
                font-size: 14px;
                padding: 12px;
            }
            .toast {
                width: 90%;
                text-align: center;
                font-size: 14px;
                bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="modal-container" id="modal-container">
        <div class="modal-header">
            <span class="file-title"><?php echo htmlspecialchars($file); ?></span>
            <div class="button-group">
                <!-- <button class="btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($file_url); ?>')">
                    <i class='bx bx-link'></i> Copy Link
                </button> -->
                <?php if ($is_pdf || $is_image || $is_video || $is_audio || $is_table || $extension === 'docx'): ?>
                    <button class="btn-fullscreen" onclick="toggleFullScreen()">
                        <i class='bx bx-fullscreen'></i> Full Screen
                    </button>
                <?php endif; ?>
                <a href="javascript:void(0);" class="close-button" onclick="history.back()">
                    <i class='bx bx-x'></i>
                </a>
            </div>
        </div>
        <div class="modal-content">
            <?php if ($is_pdf): ?>
                <embed id="file-embed" src="../../uploads/modules/<?php echo htmlspecialchars($file); ?>" type="application/pdf">
            <?php elseif ($is_image): ?>
                <img id="file-embed" src="../../uploads/modules/<?php echo htmlspecialchars($file); ?>" alt="Image Preview">
            <?php elseif ($is_video): ?>
                <video id="file-embed" controls>
                    <source src="../../uploads/modules/<?php echo htmlspecialchars($file); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php elseif ($is_audio): ?>
                <audio id="file-embed" controls>
                    <source src="../../uploads/modules/<?php echo htmlspecialchars($file); ?>" type="<?php echo htmlspecialchars($mime_types[$extension]); ?>">
                    Your browser does not support the audio tag.
                </audio>
            <?php elseif ($extension === 'csv'): ?>
                <div class="table-content">
                    <?php echo renderCsvTable("../../uploads/modules/$file"); ?>
                    <a href="javascript:void(0);" onclick="downloadFile('<?php echo htmlspecialchars($file_url); ?>', '<?php echo htmlspecialchars($file); ?>')" class="download-link">
                        <i class='bx bxs-download'></i> Download File
                    </a>
                </div>
            <?php elseif ($extension === 'xlsx'): ?>
                <iframe id="file-embed" src="https://docs.google.com/viewer?url=<?php echo urlencode($file_url); ?>&embedded=true" onload="checkIframeLoad(this)"></iframe>
                <div class="office-preview-error" style="display: none;">
                    <p>Unable to preview this file (<?php echo htmlspecialchars($extension); ?>). The file may be too complex or not supported for preview. Please download to view in Excel.</p>
                    <a href="javascript:void(0);" onclick="downloadFile('<?php echo htmlspecialchars($file_url); ?>', '<?php echo htmlspecialchars($file); ?>')" class="download-link">
                        <i class='bx bxs-download'></i> Download File
                    </a>
                </div>
            <?php elseif ($extension === 'docx'): ?>
                <div id="docx-preview" class="docx-preview"></div>
                <div class="docx-preview-error" style="display: none;">
                    <p>Unable to preview this file (<?php echo htmlspecialchars($extension); ?>). The file may be too complex or not supported for preview. Please download to view in Word.</p>
                    <a href="javascript:void(0);" onclick="downloadFile('<?php echo htmlspecialchars($file_url); ?>', '<?php echo htmlspecialchars($file); ?>')" class="download-link">
                        <i class='bx bxs-download'></i> Download File
                    </a>
                </div>
            <?php elseif ($is_text): ?>
                <div class="text-content">
                    <pre><?php echo htmlspecialchars(file_get_contents("../../uploads/modules/$file")); ?></pre>
                    <a href="javascript:void(0);" onclick="downloadFile('<?php echo htmlspecialchars($file_url); ?>', '<?php echo htmlspecialchars($file); ?>')" class="download-link">
                        <i class='bx bxs-download'></i> Download File
                    </a>
                </div>
            <?php else: ?>
                <div class="non-supported-message">
                    <p>Preview is not available for this file type (<?php echo htmlspecialchars($extension); ?>).</p>
                    <a href="javascript:void(0);" onclick="downloadFile('<?php echo htmlspecialchars($file_url); ?>', '<?php echo htmlspecialchars($file); ?>')" class="download-link">
                        <i class='bx bxs-download'></i> Download File
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div id="toast" class="toast">Link copied to clipboard!</div>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        function toggleFullScreen() {
            const modalContainer = document.getElementById('modal-container');
            const button = document.querySelector('.btn-fullscreen');
            if (!document.fullscreenElement) {
                modalContainer.requestFullscreen().catch(err => {
                    console.error('Failed to enter fullscreen: ', err);
                });
                modalContainer.classList.add('fullscreen');
                button.innerHTML = "<i class='bx bx-exit-fullscreen'></i> Exit Full Screen";
            } else {
                document.exitFullscreen();
                modalContainer.classList.remove('fullscreen');
                button.innerHTML = "<i class='bx bx-fullscreen'></i> Full Screen";
            }
        }

        document.addEventListener('fullscreenchange', () => {
            const modalContainer = document.getElementById('modal-container');
            const button = document.querySelector('.btn-fullscreen');
            if (!document.fullscreenElement) {
                modalContainer.classList.remove('fullscreen');
                if (button) {
                    button.innerHTML = "<i class='bx bx-fullscreen'></i> Full Screen";
                }
            }
        });

        function checkIframeLoad(iframe) {
            try {
                const content = iframe.contentWindow.document.body.innerHTML;
                if (!content || content.includes('Access Denied') || content.includes('Sorry')) {
                    iframe.style.display = 'none';
                    iframe.nextElementSibling.style.display = 'block';
                }
            } catch (e) {
                iframe.style.display = 'none';
                iframe.nextElementSibling.style.display = 'block';
            }
        }

        function downloadFile(url, filename) {
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch file');
                    }
                    return response.blob();
                })
                .then(blob => {
                    saveAs(blob, filename);
                })
                .catch(err => {
                    console.error('Failed to download file: ', err);
                    alert('Failed to download the file. Please try again.');
                });
        }

        <?php if ($extension === 'docx'): ?>
            // Load DOCX file using mammoth.js
            fetch("../../uploads/modules/<?php echo htmlspecialchars($file); ?>")
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch DOCX file');
                    }
                    return response.arrayBuffer();
                })
                .then(buffer => {
                    mammoth.convertToHtml({ arrayBuffer: buffer })
                        .then(result => {
                            document.getElementById('docx-preview').innerHTML = result.value;
                        })
                        .catch(err => {
                            console.error('Failed to render DOCX: ', err);
                            document.getElementById('docx-preview').style.display = 'none';
                            document.querySelector('.docx-preview-error').style.display = 'block';
                        });
                })
                .catch(err => {
                    console.error('Failed to fetch DOCX: ', err);
                    document.getElementById('docx-preview').style.display = 'none';
                    document.querySelector('.docx-preview-error').style.display = 'block';
                });
        <?php endif; ?>
    </script>
</body>
</html>