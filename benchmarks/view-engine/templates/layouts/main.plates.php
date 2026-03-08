<html>

<head>
    <meta charset="utf-8">
    <title><?php echo $this->e($title); ?></title>
</head>

<body>
    <div class="page">
        <?php echo $content ?? ''; ?>
    </div>
</body>

</html>