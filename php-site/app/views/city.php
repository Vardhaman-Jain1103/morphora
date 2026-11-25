<?php /** City landing page; text can be overridden per city in admin → Cities. @var array $c */
$n = e($c['name']);
$lead = trim((string)$c['lead']) !== '' ? e($c['lead']) : "Looking for an office interior designer in {$n}? We design and build complete offices in {$n} — from the first layout to the final handover, in just 60 days.";
$extra = trim((string)$c['body']);
?>
<?= phero(['badge' => true, 'crumb' => [['Locations', '/locations/'], [$c['name'], null]], 'h1' => "Office interior designer in <em>{$n}.</em>", 'lead' => $lead, 'img' => $c['hero'] ?: '/images/ADI8415-scaled.jpg', 'imgAlt' => "Office interior by Morphora Spaces for a client in {$c['name']}"]) ?>

<?= split_sec('( 01 — Our approach )', "Offices that work, <em>in {$n}.</em>", "<p>We don’t just design office interiors in {$n} — we rethink how your space should work. By mixing smart planning with good design, we create offices that look good and help your team do their best work.</p>
<p>With our 60-day delivery promise, your office is ready quickly without cutting corners on quality. Every space is planned around your needs today and how your company will grow.</p>
<ul class=\"tick\"><li><a href=\"/design-and-build/\">Office design &amp; build</a></li><li><a href=\"/real-estate/\">Finding office space</a></li><li><a href=\"/pmc/\">Project management</a></li><li>Handover &amp; support</li></ul>") ?>

<?php if ($extra !== ''): ?>
<?= split_sec("( Office interiors in {$n} )", "Working <em>in {$n}.</em>", render_shortcodes($extra), true) ?>

<?php endif ?>
<?= why_sec('( 02 — Why choose us )', 'Why work <em>with us.</em>', advantages(), $extra === '') ?>

<?= sc_nums() ?>

<?= sc_process(['label' => '( 03 — How we work )']) ?>

<?= sc_projects(['label' => '( 04 — Our work )', 'h2' => 'Recent <em>projects.</em>', 'limit' => 3, 'three' => true]) ?>

<?= sc_faq(['label' => '( 05 — FAQs )', 'items' => default_faq($c['name'])]) ?>

<?= sc_posts(['label' => '( 06 — From our blog )', 'limit' => 3]) ?>
