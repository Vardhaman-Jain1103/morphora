<?php /* Home page. Projects, testimonials, the India map, latest articles and the FAQ are live sections; the rest is edited here. */ ?>
<!-- HERO: headline with a photo on its right; the photo grows to full screen on scroll -->
<section class="hx" id="expand">
  <div class="stick">
    <div class="hx-text wrap" id="hxText">
      <div class="hx-top">
        <div class="hx-copy">
          <h1 class="disp">
            <span class="ln"><span>Better offices,</span></span>
            <span class="ln"><span>built <em>on time.</em></span></span>
          </h1>
          <p>We design and build offices for growing companies — from the first layout to the final handover, in just 60 days.</p>
          <ul class="hx-proof" aria-label="Delivery highlights">
            <li><strong>60</strong><span>days to handover</span></li>
            <li><strong>150+</strong><span>offices delivered</span></li>
          </ul>
        </div>
        <div class="hx-slot" id="xSlot" aria-hidden="true"></div>
      </div>
    </div>
    <div class="hx-process wrap" aria-label="Design and build process">
      <i class="hx-process-track" aria-hidden="true"></i>
      <i class="hx-process-dot" aria-hidden="true"></i>
      <span>Plan</span><span>Design</span><span>Build</span><span>Handover</span>
    </div>
    <div class="frame" id="xFrame"><img id="xImg" src="/images/morphora-office-8000-sqft-hero.jpg" alt="Open-plan workstations in an 8,000 sq ft office by Morphora Spaces" fetchpriority="high"><div class="shade" id="xShade"></div></div>
    <div class="cap wrap mono" id="xCap"><span>8,000 sq ft office</span><span>Designed &amp; built by Morphora</span></div>
    <div class="hx-bottom wrap">
      <span class="mono hx-services">Space planning <b aria-hidden="true">·</b> Design &amp; build <b aria-hidden="true">·</b> Turnkey delivery</span>
      <span class="mono hx-scroll">Scroll to explore <b aria-hidden="true">↓</b></span>
    </div>
  </div>
</section>

<!-- MANIFESTO -->
<section class="manifesto">
  <div class="wrap">
    <span class="mono">( Our vision )</span>
    <p id="mf">We listen. We deliver. With <em>presence and purpose.</em></p>
    <div class="vis-sub">
      <p>We don’t just design interiors — we challenge conventions, blending creativity with strategy to build spaces that go far beyond looks.</p>
      <a class="vis-link mono" href="/about/">More about us ↗</a>
    </div>
    <ol class="vals">
      <li><span class="mono">01</span><h3>Empathy</h3><p>Designed around people — how they feel and work, not just how it looks.</p></li>
      <li><span class="mono">02</span><h3>Precision</h3><p>Details are never small. The smallest choices make the biggest impression.</p></li>
      <li><span class="mono">03</span><h3>Integrity</h3><p>What we promise, we deliver — with honesty in every interaction.</p></li>
      <li><span class="mono">04</span><h3>Context</h3><p>Design with intention: spaces that work better, not just look good.</p></li>
      <li><span class="mono">05</span><h3>Innovation</h3><p>New methods for spaces that are future-ready, adaptable and sustainable.</p></li>
    </ol>
  </div>
</section>

<!-- FLOOR PLAN -->
<section class="plan" id="plan">
  <div class="wrap">
    <div class="plan-head">
      <div><span class="mono" style="color:var(--brass)">( Office zones )</span><h2 class="disp" style="margin-top:20px">Every area, <em>well planned.</em></h2></div>
      <p>The twelve areas we plan in every office. Select one to learn more.</p>
    </div>
    <div class="plan-body">
      <div>
        <svg viewBox="-96 0 1096 620" id="planSvg" role="img" aria-label="Interactive office floor plan">
          <g class="dims"><text x="0" y="14">A — 100'</text><text x="940" y="14">N ↑</text></g>
          <!-- zones (order matches Z[] in partials/home-script.html) -->
          <g class="zone" data-z="0" tabindex="0" role="button" aria-label="Reception"><rect class="z" x="20" y="440" width="220" height="160"/><text x="36" y="466">RECEPTION</text>
            <path class="furn" d="M100 528h112v28H100zM212 470v86M130 505a9 9 0 1 0 .1 0M180 505a9 9 0 1 0 .1 0"/></g>
          <g class="zone" data-z="1" tabindex="0" role="button" aria-label="Waiting / Lounge"><rect class="z" x="240" y="440" width="180" height="160"/><text x="256" y="466">WAITING / LOUNGE</text>
            <path class="furn" d="M260 486h112v24H260zM260 510h24v66h-24zM340 528a18 18 0 1 0 .1 0"/></g>
          <g class="zone" data-z="2" tabindex="0" role="button" aria-label="Workstations"><rect class="z" x="240" y="30" width="500" height="310"/><text x="256" y="56">WORKSTATIONS</text>
            <path class="furn" d="M270 78h200v36H270zM370 78v36M510 78h200v36H510zM610 78v36M270 148h200v36H270zM370 148v36M510 148h200v36H510zM610 148v36M270 218h200v36H270zM370 218v36M510 218h200v36H510zM610 218v36M270 288h200v36H270zM370 288v36M510 288h200v36H510zM610 288v36"/></g>
          <g class="zone" data-z="3" tabindex="0" role="button" aria-label="Manager Cabins"><rect class="z" x="740" y="30" width="240" height="170"/><text x="756" y="56">MANAGER CABINS</text>
            <path class="furn" d="M820 70v130M900 70v130M755 118h50v28h-50zM835 118h50v28h-50zM915 118h50v28h-50z"/></g>
          <g class="zone" data-z="4" tabindex="0" role="button" aria-label="Meeting Rooms"><rect class="z" x="740" y="200" width="240" height="180"/><text x="756" y="226">MEETING ROOMS</text>
            <path class="furn" d="M860 236v144M770 290h60v40h-60zM890 290h60v40h-60z"/></g>
          <g class="zone" data-z="5" tabindex="0" role="button" aria-label="Conference / Boardroom"><rect class="z" x="740" y="380" width="240" height="220"/><text x="756" y="406">CONFERENCE / BOARDROOM</text>
            <path class="furn" d="M780 460h150v70H780zM795 448h10M830 448h10M865 448h10M900 448h10M795 542h10M830 542h10M865 542h10M900 542h10M962 465v60"/></g>
          <g class="zone" data-z="6" tabindex="0" role="button" aria-label="Cafeteria / Pantry"><rect class="z" x="420" y="440" width="320" height="160"/><text x="436" y="466">CAFETERIA / PANTRY</text>
            <path class="furn" d="M440 482h280v24H440zM480 532a16 16 0 1 0 .1 0M560 532a16 16 0 1 0 .1 0M640 532a16 16 0 1 0 .1 0M700 532a16 16 0 1 0 .1 0"/></g>
          <g class="zone" data-z="7" tabindex="0" role="button" aria-label="Breakout"><rect class="z" x="240" y="340" width="260" height="100"/><text x="256" y="366">BREAKOUT</text>
            <path class="furn" d="M262 384h100v22H262zM262 406h22v24h-22zM430 382a18 18 0 1 0 .1 0"/></g>
          <g class="zone" data-z="8" tabindex="0" role="button" aria-label="Recreation / Games"><rect class="z" x="500" y="340" width="240" height="100"/><text x="516" y="366">RECREATION / GAMES</text>
            <path class="furn" d="M570 380h100v46H570zM620 380v46M556 403h14M670 403h14"/></g>
          <g class="zone" data-z="9" tabindex="0" role="button" aria-label="Training / Multipurpose"><rect class="z" x="20" y="140" width="220" height="180"/><text x="36" y="166">TRAINING / MULTIPURPOSE</text>
            <path class="furn" d="M50 186h160v18H50zM50 222h160v18H50zM50 258h160v18H50zM80 302h100"/></g>
          <g class="zone" data-z="10" tabindex="0" role="button" aria-label="Display / Experience"><rect class="z" x="20" y="320" width="220" height="120"/><text x="36" y="346">DISPLAY / EXPERIENCE</text>
            <path class="furn" d="M50 368h40v40H50zM110 368h40v40h-40zM170 368h40v40h-40z"/></g>
          <g class="zone" data-z="11" tabindex="0" role="button" aria-label="Washrooms"><rect class="z" x="20" y="30" width="220" height="110"/><text x="36" y="56">WASHROOMS</text>
            <path class="furn" d="M40 76h180v48H40zM85 76v48M130 76v48M175 76v48"/></g>
          <rect class="walls" x="20" y="30" width="960" height="570"/>
          <path d="M20 470v60" stroke="var(--char)" stroke-width="6"/>
          <!-- main entrance: door opening into reception, with arrow from outside -->
          <g class="entry" aria-hidden="true">
            <path class="door" d="M20 470h60"/><path class="swing" d="M80 470A60 60 0 0 1 20 530"/>
            <text x="-92" y="484">ENTRANCE</text>
            <path class="arr" d="M-90 500h96M-4 491l10 9-10 9"/>
          </g>
        </svg>
        <p class="plan-hint mono">↳ Hover to preview · click an area to see its photos</p>
      </div>
      <div class="zcard">
        <div class="ph" id="zPh"></div>
        <div class="bd"><span class="mono" id="zK">Area 01 · ~8% of floor space</span><h3 id="zT">Reception</h3><p id="zD"></p><ul id="zL"></ul><button type="button" class="zbtn mono" id="zBtn">View photos <span>↗</span></button></div>
      </div>
    </div>
  </div>
</section>

<?= sc_testimonials(['label' => '( Client stories )', 'h2' => 'What our clients <em>say.</em>']) ?>

<!-- ZONE GALLERY (opened by clicking an area) -->
<div class="zg" id="zg" role="dialog" aria-modal="true" aria-labelledby="zgT" hidden>
  <div class="zg-top"><div><span class="mono" id="zgK">Area 01</span><h3 class="disp" id="zgT">Reception</h3></div><span class="mono zg-tag" id="zgTag" hidden>Design idea · for inspiration</span><span class="mono zg-count" id="zgCount"></span><button type="button" class="zg-x" id="zgClose" aria-label="Close gallery">✕</button></div>
  <div class="zg-stage"><button type="button" class="zg-nav prev" id="zgPrev" aria-label="Previous photo">←</button><img id="zgImg" alt=""><button type="button" class="zg-nav next" id="zgNext" aria-label="Next photo">→</button></div>
  <div class="zg-thumbs" id="zgThumbs"></div>
</div>

<!-- HORIZONTAL WORK -->
<section class="hz" id="work">
  <div class="stick">
    <div class="wrap hz-head"><h2 class="disp">Our <em>Projects</em></h2><span class="mono">( 150+ offices delivered )</span></div>
    <div class="hz-track" id="hzTrack">
<?= home_project_cards() ?>
      <div class="hz-end"><span class="mono" style="color:var(--mut);margin-bottom:20px">( Full portfolio )</span><a href="/portfolio/">See all <span>projects</span> ↗</a></div>
    </div>
    <div class="wrap"><div class="hz-prog"><i id="hzProg"></i></div></div>
  </div>
</section>

<!-- NUMBERS -->
<section class="nums">
  <div class="wrap nums-grid">
    <div class="n rv"><b><span data-c="60">0</span><sup>d</sup></b><span class="mono">Days from design to handover</span></div>
    <div class="n rv"><b><span data-c="10">0</span><sup>+</sup></b><span class="mono">Cities we work in</span></div>
    <div class="n rv"><b><span data-c="150">0</span><sup>+</sup></b><span class="mono">Offices designed &amp; built</span></div>
    <div class="n rv"><b><span data-c="100">0</span><sup>%</sup></b><span class="mono">Clients who come back for their next office</span></div>
  </div>
</section>

<!-- PAN-INDIA MAP -->
<?= sc_map(['label' => '( Pan-India )']) ?>

<!-- WHY US -->
<section class="why">
  <div class="wrap why-grid">
    <div><span class="mono" style="color:var(--plum)">( Why choose us )</span><h2 class="disp">Why work <em>with us.</em></h2></div>
    <ol>
      <li class="rv"><span class="mono">01</span><div><h3>Clear process, full transparency</h3><p>From start to finish you always know where your project stands, with live progress updates on a simple online dashboard.</p></div></li>
      <li class="rv"><span class="mono">02</span><div><h3>Design that works</h3><p>Offices that look good and are easy to work in — planned around your workflow, productivity and company culture.</p></div></li>
      <li class="rv"><span class="mono">03</span><div><h3>One expert team</h3><p>Architects, interior designers and workspace planners work together, so your project fits your industry and needs.</p></div></li>
      <li class="rv"><span class="mono">04</span><div><h3>Local and relevant</h3><p>We use local materials and regional design touches, so the office feels right for your people and your visitors.</p></div></li>
      <li class="rv"><span class="mono">05</span><div><h3>Support after handover</h3><p>You get user guides, a maintenance kit and space-planning tips, so your team settles in quickly and confidently.</p></div></li>
    </ol>
  </div>
</section>

<!-- BLOG -->
<section class="blog">
  <div class="wrap">
    <div class="blog-head"><div><span class="mono" style="color:var(--plum)">( From our blog )</span><h2 class="disp">Ideas for <em>better offices.</em></h2></div><a class="mono" href="/resources/">All articles ↗</a></div>
    <div class="blog-grid">
<?= post_cards(latest_posts(3)) ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<?= sc_faq(['label' => '( FAQs )', 'items' => home_faq()]) ?>
