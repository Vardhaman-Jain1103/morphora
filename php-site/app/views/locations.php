<?php /** @var array $cities */ ?>
<?= phero(['crumb' => [['Locations', null]], 'h1' => 'Office interiors <em>across India.</em>', 'lead' => 'We design, build and manage offices for clients across India. Our team works on-site or remotely, depending on the location and size of your project.']) ?>

<?= sc_map(['label' => '( Where we work )']) ?>

<section class="sec" style="padding-top:20px">
  <div class="wrap">
    <div class="loc-top">
<?php foreach (array_filter($cities, fn($c) => $c['top']) as $c): ?>
      <a href="/office-interior-designer-in-<?= e($c['slug']) ?>/"><?= e($c['name']) ?><span>View ↗</span></a>
<?php endforeach ?>
    </div>
    <div class="sec-head" style="margin-bottom:24px"><div><span class="mono lbl">( All cities )</span></div></div>
    <label class="loc-search"><span class="mono" style="color:var(--plum)">Find</span><input id="locq" type="search" placeholder="Type your city…" autocomplete="off" aria-label="Search cities"><span class="loc-count mono" id="locn"><?= count($cities) ?> cities</span></label>
    <p class="loc-empty" id="loce" hidden>No match — we still work across India. <a href="#contact" style="color:var(--plum)">Ask us about your city ↗</a></p>
    <div class="loc-grid">
<?php foreach ($cities as $c): ?>
      <a href="/office-interior-designer-in-<?= e($c['slug']) ?>/"><?= e($c['name']) ?></a>
<?php endforeach ?>
    </div>
  </div>
</section>
