<title><?php echo htmlspecialchars($title); ?></title>
<ul>
    <?php foreach ($items as $item): ?>
        <li><?php echo htmlspecialchars($item); ?></li>
    <?php endforeach; ?>
</ul>