<?php /** Fixed page from the `pages` table. @var array $page */
// Section labels are numbered "( 01 — … )"; renumber them in order so hidden (empty) sections leave no gaps.
$n = 0;
echo preg_replace_callback('/\( \d{2} — /u', function () use (&$n) { return sprintf('( %02d — ', ++$n); }, render_shortcodes((string)$page['body']));
