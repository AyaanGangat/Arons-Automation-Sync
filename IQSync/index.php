<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>IQSync - Available Files</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .file-list { list-style: none; padding: 0; }
        .file-list li { margin: 10px 0; }
        .file-list a { color: #0066cc; text-decoration: none; }
        .file-list a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Available Files</h1>
    <ul class="file-list">
        <?php
        $files = glob('*.{php,html,htm}', GLOB_BRACE);
        foreach ($files as $file) {
            echo '<li><a href="' . htmlspecialchars($file) . '">' . htmlspecialchars($file) . '</a></li>';
        }
        ?>
    </ul>
</body>
</html> 