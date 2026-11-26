<?php /** @var array $p  @var array $gallery  @var array $others */ ?>
<?= phero(['crumb' => [['Portfolio', '/portfolio/'], [$p['name'], null]], 'h1' => e($p['name']) . ', <em>' . e($p['city']) . '.</em>', 'lead' => e("A {$p['size']} office in {$p['city']}. {$p['delivery']}."), 'cta' => 'Plan a similar office', 'img' => $p['cover'], 'imgAlt' => $p['name'] . ' office interior, ' . $p['city']]) ?>

<section class="sec" style="padding-top:60px">
  <div class="wrap">
    <div class="facts"><div><span class="mono">Client</span><b><?= e($p['name']) ?></b></div><div><span class="mono">Location</span><b><?= e($p['city']) ?></b></div><div><span class="mono">Area</span><b><?= e($p['size']) ?></b></div><div><span class="mono">Delivery</span><b><?= e($p['delivery']) ?></b></div></div>
<?php if (trim((string)$p['summary']) !== ''): ?>
    <div class="prose" style="margin-top:40px;max-width:760px"><?= $p['summary'] ?></div>
<?php endif ?>
  </div>
</section>

<?php if ($gallery): ?>
<section class="sec" style="padding-top:0">
  <div class="wrap">
    <div class="sec-head"><div><span class="mono lbl">( Gallery )</span><h2 class="disp">Inside the <em>office.</em></h2></div><span class="mono" style="color:var(--mut)"><?= count($gallery) ?> photos · click to enlarge</span></div>
    <div class="gal">
<?php foreach ($gallery as $i => $g): ?>
      <a href="<?= e($g[1] ?? $g[0]) ?>"><img src="<?= e($g[0]) ?>" alt="<?= e($p['name'] . ' office interior, ' . $p['city'] . ' - photo ' . ($i + 1)) ?>" loading="lazy"></a>
<?php endforeach ?>
    </div>
    <p class="note"><?= e(setting('disclaimer')) ?></p>
  </div>
</section>
<?php endif ?>

<?php if ($others): ?>
<?= sc_projects(['label' => '( More work )', 'h2' => 'Other <em>projects.</em>', 'slugs' => array_column($others, 'slug'), 'three' => true]) ?>
<?php endif ?>
