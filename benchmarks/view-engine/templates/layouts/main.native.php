<html>

<head>
    <meta charset="utf-8">
    <title><?php echo esc_html($title); ?></title>
</head>

<body>
    <div class="page">
        <?php echo $content ?? ''; ?>
    </div>
</body>

</html>