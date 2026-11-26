<?php /** @var array $p  @var array $related */ ?>
<section class="art-head">
  <div class="wrap"><div class="in">
    <?= crumbs_html([['Resources', '/resources/'], [$p['category'], null]]) ?>

    <h1 class="disp"><?= e($p['title']) ?></h1>
    <div class="art-meta"><span><time datetime="<?= e($p['published_at']) ?>"><?= e(fmt_date($p['published_at'])) ?></time></span><span><?= read_minutes((string)$p['body']) ?> min read</span><span>By <?= e($p['author']) ?></span></div>
  </div></div>
</section>
<?php if ($p['cover']): ?>
<section class="art-img"><div class="wrap"><div><img src="<?= e($p['cover']) ?>" alt="<?= e($p['title']) ?>" fetchpriority="high"></div></div></section>
<?php endif ?>
<section class="art-body">
  <div class="wrap">
    <article class="prose">
<?= render_shortcodes((string)$p['body']) ?>

    </article>
    <aside class="art-side">
      <span class="mono">Planning a new office?</span>
      <p>Talk to our team about design, build or project management. We deliver complete offices in 60 days.</p>
      <div class="btns"><a class="btn" href="#contact">Get a free consultation</a><a class="btn ghost" href="/resources/">More articles</a></div>
    </aside>
  </div>
</section>

<?= sc_posts(['label' => '( Keep reading )'], $related) ?>
