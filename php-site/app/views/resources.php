<?php /** @var array $posts */
$total = count($posts);
$first = array_shift($posts);
$cats = array_values(array_unique(array_column($first ? array_merge([$first], $posts) : $posts, 'category')));
sort($cats);
?>
<?= phero(['crumb' => [['Resources', null]], 'h1' => 'Ideas for <em>better offices.</em>', 'lead' => "Guides, tips and articles to help you plan, design and build a better office. {$total} articles and counting.", 'btns' => false]) ?>

<section class="sec" style="padding-top:20px">
  <div class="wrap">
<?php if ($first): ?>
    <a class="feat rv" href="/resources/<?= e($first['slug']) ?>/"><div class="ph" data-tilt><img src="<?= e(post_thumb($first)) ?>" alt=""></div><div><span class="mono" style="color:var(--plum)">Latest · <?= e($first['category']) ?></span><h2><?= e($first['title']) ?></h2><p><?= e($first['excerpt']) ?></p><span class="post"><span class="date"><?= e(fmt_date($first['published_at'])) ?></span></span></div></a>
<?php endif ?>
<?php if (count($cats) > 2): ?>
    <div class="chips filters" role="group" aria-label="Filter articles"><button class="on" data-cat="*">All</button><?php foreach ($cats as $c): ?><button data-cat="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach ?></div>
<?php endif ?>
    <div class="blog-grid all">
<?= post_cards($posts, true) ?>

    </div>
  </div>
</section>
