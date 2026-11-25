<?php /** @var array $j  @var bool $open */ ?>
<?= phero(['crumb' => [['Careers', '/careers/'], [$j['title'], null]], 'h1' => e($j['title']) . ($open ? '' : ' <em>(closed)</em>'), 'lead' => e($j['location'] . ' · ' . $j['type'] . ($j['experience'] ? ' · ' . $j['experience'] . ' experience' : '')), 'btns' => false]) ?>

<section class="sec" style="padding-top:10px">
  <div class="wrap split">
    <div class="prose"><?= $j['description'] ?></div>
<?php if ($open): ?>
    <?= view('partials/apply-form', ['job' => $j, 'back' => '/careers/' . $j['slug'] . '/']) ?>
<?php else: ?>
    <div class="prose"><p>This role is no longer open. <a href="/careers/">See current openings</a> or send a general application.</p></div>
<?php endif ?>
  </div>
</section>
