<?php
/** @var string $body  @var array $meta */
$ver = @filemtime(ROOT . '/assets/site.css') ?: 1;   // cache-busting for CSS/JS after deploys
?><!doctype html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= seo_head($meta) ?>

<meta name="theme-color" content="#151215">
<?php if (is_file(ROOT . '/images/favicon.png')): ?><link rel="icon" href="/images/favicon.png" type="image/png"><?php endif ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600&family=Inter:wght@200;300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/site.css?v=<?= $ver ?>">
</head>
<body data-api="/api/enquiry/" data-wa="<?= e(setting('whatsapp', '917719155155')) ?>">
<div class="cur" id="cur"><span>VIEW</span></div>
<?= view('partials/header', ['active' => $meta['nav'] ?? '']) ?>

<main>
<?= $body ?>

</main>

<?= view('partials/footer', ['label' => $meta['foot_label'] ?? '', 'title' => $meta['foot_title'] ?? '']) ?>

<script src="/assets/site.js?v=<?= @filemtime(ROOT . '/assets/site.js') ?: 1 ?>"></script>
<?= $meta['scripts'] ?? '' ?>
</body>
</html>
