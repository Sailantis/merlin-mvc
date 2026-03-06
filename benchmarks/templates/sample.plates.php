<title><?php echo $this->e($title); ?></title>
<ul>
    <?php foreach ($items as $item): ?>
        <li><?php echo $this->e($item); ?></li>
    <?php endforeach; ?>
</ul>