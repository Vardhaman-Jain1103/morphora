<?php
/** @var string $active */
$nav = [
    ['about', 'About', 'About us', 'Our story, team and way of working.', 'Our story and team', '/media/2025/10/slidebean-TpGIpUF67po-unsplash-scaled.jpg'],
    // ['design-and-build', 'Design &amp; Build', 'Design &amp; Build', 'Complete office interiors, from layout to handover.', 'Complete office interiors', '/images/ADI8340-scaled.jpg'],
    ['portfolio', 'Portfolio', 'Portfolio', '150+ offices we have designed and built.', 'Offices we have delivered', '/media/2025/10/ADI8265-1024x692.jpg'],
    ['resources', 'Resources', 'Resources', 'Guides and articles on office design.', 'Guides and articles', '/media/2025/10/MicrosoftTeams-image-51-1024x731.jpg'],
    ['contact', 'Contact Us', 'Contact us', 'Visit our Thane office or call our team.', 'Talk to our team', '/media/2025/10/asia-culturecenter-5h9ex_RrmhY-unsplash-scaled.jpg'],
];
$tel = setting('phone_e164', '+917719155155');
?>
<header>
  <div class="wrap">
    <a class="logo" href="/" aria-label="<?= e(setting('site_name', 'Morphora Spaces')) ?> — home"><img src="/images/morphora-spaces-logo-header.png" alt="<?= e(setting('site_name', 'Morphora Spaces')) ?>" width="155" height="56"></a>
    <nav aria-label="Main">
      <ul class="nav">
<?php foreach ($nav as $n): ?>
        <li><a href="/<?= $n[0] ?>/"<?= $active === $n[0] ? ' class="on" aria-current="page"' : '' ?>><?= $n[1] ?></a><span class="prev"><img src="<?= $n[5] ?>" alt="" loading="lazy"><span><b><?= $n[2] ?></b><?= $n[3] ?></span></span></li>
<?php endforeach ?>
      </ul>
    </nav>
    <div class="right">
      <a class="hbtn" href="#contact">Let’s talk</a>
      <button class="menu-t mono" id="menuT" aria-label="Open menu" aria-expanded="false" aria-controls="mnav"><span id="menuL">Menu</span><b><i></i><i></i></b></button>
    </div>
  </div>
</header>
<div class="mnav" id="mnav">
  <ul>
<?php foreach ($nav as $i => $n): ?>
    <li><a href="/<?= $n[0] ?>/"><b>0<?= $i + 1 ?></b><span><strong><?= $n[1] ?></strong><small><?= $n[4] ?></small></span><img src="<?= $n[5] ?>" alt="" loading="lazy"></a></li>
<?php endforeach ?>
  </ul>
  <div class="mfoot">
    <a class="pri" href="tel:<?= e($tel) ?>">Call us</a>
    <a href="https://wa.me/<?= e(setting('whatsapp', '917719155155')) ?>" target="_blank" rel="noopener">WhatsApp</a>
    <p><?= e(setting('email', 'hello@morphoraspaces.com')) ?> · Thane, Maharashtra</p>
  </div>
</div>
