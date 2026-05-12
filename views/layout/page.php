<?php
/**
 * @var string   $title
 * @var callable $content
 */
?>
<div class="wrap">
    <div class="seocp-app">
        <div class="seocp-page">
            <div class="fl-toolbar">
                <h1 class="fl-toolbar__title"><?php echo esc_html($title); ?></h1>
            </div>
            <?php if (is_callable($content)) { call_user_func($content); } ?>
        </div>
    </div>
</div>
