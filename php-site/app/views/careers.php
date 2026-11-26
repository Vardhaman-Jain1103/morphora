<?php /** Careers: intro page (editable in admin → Pages) + open positions (admin → Jobs) + application form. @var array $page  @var array $jobs */ ?>
<?= render_shortcodes((string)($page['body'] ?? '')) ?>

<section class="sec alt" id="jobs">
  <div class="wrap">
    <div class="sec-head"><div><span class="mono lbl">( Open positions )</span><h2 class="disp"><?= $jobs ? 'Roles we’re <em>hiring for.</em>' : 'No open roles <em>right now.</em>' ?></h2></div></div>
<?php if ($jobs): ?>
    <div class="jobs">
<?php foreach ($jobs as $j): ?>
      <a class="job rv" href="/careers/<?= e($j['slug']) ?>/"><div><h3><?= e($j['title']) ?></h3><span class="mono"><?= e($j['location']) ?> · <?= e($j['type']) ?><?= $j['experience'] ? ' · ' . e($j['experience']) : '' ?></span></div><span class="go">View &amp; apply ↗</span></a>
<?php endforeach ?>
    </div>
<?php else: ?>
    <p style="color:var(--mut);max-width:560px">We are always happy to hear from talented people. Send a general application below and we will contact you when a role opens up.</p>
<?php endif ?>
  </div>
</section>

<section class="sec apply-sec">
  <div class="wrap split">
    <div><span class="mono lbl">( Apply )</span><h2 class="disp">Don’t see <em>your role?</em></h2><p style="margin-top:18px;color:var(--mut)">Send us your CV anyway - we keep strong applications on file.</p></div>
    <?= view('partials/apply-form', ['job' => null, 'back' => '/careers/']) ?>
  </div>
</section>
