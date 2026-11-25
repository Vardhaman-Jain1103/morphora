<?php /** @var array $projects */ ?>
<?= phero(['btns' => false, 'crumb' => [['Portfolio', null]], 'h1' => 'Our work, <em>delivered.</em>', 'lead' => 'A selection of offices we have designed, built and managed — from compact studios to 18,000 sq ft corporate floors.']) ?>

<section class="sec" style="padding-top:20px">
  <div class="wrap">
    <div class="work-grid">
<?php foreach ($projects as $p): ?>
      <?= wcard($p) ?>

<?php endforeach ?>
    </div>
    <p class="note"><?= e(setting('disclaimer')) ?></p>
  </div>
</section>

<?= sc_nums() ?>
