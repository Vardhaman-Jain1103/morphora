<?php
/** Contact section + footer on every page. @var string $label  @var string $title (HTML) */
$tel = setting('phone_e164', '+917719155155');
$wa = setting('whatsapp', '917719155155');
$mail = setting('email', 'hello@morphoraspaces.com');
?>
<section class="cta" id="contact">
  <div class="wrap">
    <div class="cta-grid">
      <div class="cta-side">
      <div class="cta-intro">
        <span class="mono"><?= e($label ?: '( Contact us )') ?></span>
        <h2 class="disp"><?= $title ?: 'Let’s plan <em>your office.</em>' ?></h2>
        <p class="cta-lead">Tell us a little about your space. Our team will call you back within one working day with next steps and a rough estimate.</p>
      </div>
      <div class="cinfo">
        <div><span class="mono">Call us</span><a class="big" href="tel:<?= e($tel) ?>"><?= e(setting('phone', '+91 77191 55155')) ?></a></div>
        <div><span class="mono">Email</span><a class="big" href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></div>
        <div><span class="mono">WhatsApp</span><a class="big" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">Chat with us ↗</a></div>
        <div class="offices">
          <div><span class="mono">Head office</span><address><?= e(setting('address_head')) ?></address><a class="map" href="https://www.google.com/maps/place/Morphora+Spaces/@19.2035213,72.9539775,17z" target="_blank" rel="noopener">Get directions ↗</a></div>
          <div><span class="mono">Registered office</span><address><?= e(setting('address_reg')) ?></address><a class="map" href="https://maps.google.com/?q=Dev+Corpora+Cadbury+junction+Eastern+Express+Hwy+Thane+400601" target="_blank" rel="noopener">Get directions ↗</a></div>
        </div>
        <div><span class="mono">We work in</span><p class="cities">Mumbai · Thane · Pune · Ahmedabad · Bengaluru · Delhi · Hyderabad · <a href="/locations/" style="color:var(--plum-l)">all locations</a></p></div>
      </div>
      </div>
      <form class="cform" id="cform" method="post" action="/api/enquiry/" novalidate>
        <h3>Send us an enquiry</h3>
        <label>Your name<input name="name" type="text" autocomplete="name" placeholder="Full name" required maxlength="150"></label>
        <label>Company<input name="company" type="text" autocomplete="organization" placeholder="Company name" maxlength="150"></label>
        <label>Phone<input name="phone" type="tel" autocomplete="tel" placeholder="+91" required maxlength="40"></label>
        <label>Email<input name="email" type="email" autocomplete="email" placeholder="you@company.com" maxlength="190"></label>
        <label>City<input name="city" type="text" placeholder="e.g. Mumbai" maxlength="100"></label>
        <label>Office size
          <select name="size">
            <option value="">Select size</option>
            <option>Under 2,000 sq ft</option>
            <option>2,000 – 5,000 sq ft</option>
            <option>5,000 – 10,000 sq ft</option>
            <option>10,000 – 25,000 sq ft</option>
            <option>Over 25,000 sq ft</option>
          </select>
        </label>
        <label class="full">What do you need?
          <select name="service">
            <option value="">Select a service</option>
            <option>Office design &amp; build</option>
            <option>Finding office space</option>
            <option>Project management</option>
            <option>Not sure yet</option>
          </select>
        </label>
        <label class="full">Message<textarea name="msg" placeholder="Tell us about your project, timeline or budget" maxlength="4000"></textarea></label>
        <!-- spam trap: real visitors never see or fill this -->
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
        <div class="send"><button type="submit">Send enquiry →</button><span class="note" id="cnote">We reply within one working day.</span></div>
      </form>
    </div>
    <div class="ft">
      <div><span class="mono">Company</span><a href="/about/">About us</a><a href="/portfolio/">Portfolio</a><a href="/resources/">Resources</a><a href="/careers/">Careers</a><a href="/contact/">Contact us</a></div>
      <div><span class="mono">Services</span><a href="/design-and-build/">Design &amp; Build</a><a href="/real-estate/">Real Estate Advisory</a><a href="/pmc/">Project Management</a></div>
      <div><span class="mono">Locations</span><a href="/office-interior-designer-in-mumbai/">Mumbai</a><a href="/office-interior-designer-in-thane/">Thane</a><a href="/office-interior-designer-in-pune/">Pune</a><a href="/office-interior-designer-in-bengaluru/">Bengaluru</a></div>
      <div><span class="mono">&nbsp;</span><a href="/office-interior-designer-in-ahmedabad/">Ahmedabad</a><a href="/office-interior-designer-in-delhi/">Delhi</a><a href="/office-interior-designer-in-hyderabad/">Hyderabad</a><a href="/locations/">All locations →</a></div>
    </div>
    <div class="legal mono"><span>© <span id="yr"><?= date('Y') ?></span> <?= e(setting('site_name', 'Morphora Spaces')) ?></span><span><a href="/privacy-policy/">Privacy</a> · <a href="/terms-and-conditions/">Terms</a></span></div>
  </div>
</section>
