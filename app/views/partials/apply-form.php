<?php /** Job application form. @var ?array $job  @var string $back */
$ok = !empty($_GET['applied']);
$err = isset($_GET['error']) ? mb_substr((string)$_GET['error'], 0, 200) : '';
?>
<form class="cform apply" id="apply" method="post" action="/api/apply/" enctype="multipart/form-data">
  <h3><?= $job ? 'Apply for ' . e($job['title']) : 'Send a general application' ?></h3>
<?php if ($ok): ?>
  <p class="full note-ok" role="status">Thank you! We have received your application and will be in touch if there is a fit.</p>
<?php elseif ($err !== ''): ?>
  <p class="full note-err" role="alert"><?= e($err) ?></p>
<?php endif ?>
  <input type="hidden" name="job_id" value="<?= (int)($job['id'] ?? 0) ?>">
  <input type="hidden" name="back" value="<?= e($back) ?>">
  <label>Your name<input name="name" type="text" autocomplete="name" required maxlength="150"></label>
  <label>Email<input name="email" type="email" autocomplete="email" required maxlength="190"></label>
  <label>Phone<input name="phone" type="tel" autocomplete="tel" placeholder="+91" maxlength="40"></label>
  <label>Portfolio link<input name="portfolio" type="url" placeholder="https://" maxlength="255"></label>
  <label class="full">CV (PDF or Word, max 5 MB)<input name="cv" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></label>
  <label class="full">A little about you<textarea name="message" maxlength="4000" placeholder="Your experience, notice period and what you would like to work on"></textarea></label>
  <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
  <div class="send"><button type="submit">Send application →</button><span class="note">We read every application.</span></div>
</form>
