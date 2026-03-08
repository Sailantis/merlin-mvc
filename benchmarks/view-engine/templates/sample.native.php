<?php ob_start();
// Native PHP sample: build the page content and insert into the layout
echo $this->renderPartial('partials/header', ['title' => $title, 'items' => $items]);
?>
<ul>
    <?php foreach ($items as $i => $item): ?>
        <li<?php if ($i % 2 === 0)
            echo ' class="even"'; ?>><?php echo esc_html($item); ?></li>
        <?php endforeach; ?>
</ul>
<?php $content = ob_get_clean();
echo $this->renderPartial('layouts/main', ['title' => $title, 'content' => $content]);
