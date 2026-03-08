<?php ob_start(); ?>
<?php echo $this->insert('partials/header', ['title' => $title, 'items' => $items]); ?>
<ul>
    <?php foreach ($items as $i => $item): ?>
        <li<?php if ($i % 2 === 0)
            echo ' class="even"'; ?>><?php echo $this->e($item); ?></li>
        <?php endforeach; ?>
</ul>
<?php $content = ob_get_clean();
echo $this->insert('layouts/main', ['title' => $title, 'content' => $content]); ?>