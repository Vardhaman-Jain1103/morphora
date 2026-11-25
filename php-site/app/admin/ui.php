<?php
/* Admin UI helpers: page frame, form fields, pagination, small formatters. */

const ENQ_STATUS = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost', 'spam' => 'Spam'];
const APP_STATUS = ['new' => 'New', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview', 'rejected' => 'Rejected', 'hired' => 'Hired'];

function admin_page(string $title, string $content, string $active): void
{
    $me = admin_user();
    $newEnq = (int)val('SELECT COUNT(*) FROM enquiries WHERE status = "new"');
    $newApp = (int)val('SELECT COUNT(*) FROM applications WHERE status = "new"');
    $nav = [
        ['dashboard', '/admin/', 'Dashboard', 0],
        ['enquiries', '/admin/?r=enquiries', 'Enquiries', $newEnq],
        ['posts', '/admin/?r=list&t=posts', 'Articles', 0],
        ['projects', '/admin/?r=list&t=projects', 'Projects', 0],
        ['cities', '/admin/?r=list&t=cities', 'Cities', 0],
        ['pages', '/admin/?r=list&t=pages', 'Pages', 0],
        ['jobs', '/admin/?r=list&t=jobs', 'Jobs', 0],
        ['applications', '/admin/?r=applications', 'Applications', $newApp],
        ['redirects', '/admin/?r=list&t=redirects', 'Redirects', 0],
        ['settings', '/admin/?r=settings', 'Settings', 0],
    ];
    $ver = @filemtime(ROOT . '/admin/admin.js') ?: 1;
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title) ?> — Admin</title>
<link rel="stylesheet" href="/admin/admin.css?v=<?= @filemtime(ROOT . '/admin/admin.css') ?: 1 ?>">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="/images/favicon.ico" sizes="any"><link rel="icon" href="/images/favicon-32.png" type="image/png" sizes="32x32"></head>
<body>
<aside class="side">
  <a class="brand" href="/admin/"><img src="/images/morphora-spaces-logo-header.png" alt="Morphora Spaces"></a>
  <nav>
<?php foreach ($nav as [$k, $u, $l, $n]): ?>
    <a href="<?= e($u) ?>"<?= $active === $k ? ' class="on"' : '' ?>><?= e($l) ?><?= $n ? '<b>' . $n . '</b>' : '' ?></a>
<?php endforeach ?>
  </nav>
  <div class="side-foot">
    <a href="/" target="_blank">View website ↗</a>
    <a href="/admin/?r=account"<?= $active === 'account' ? ' class="on"' : '' ?>><?= e($me['name'] ?? 'Account') ?></a>
    <form method="post" action="/admin/?r=logout"><?= csrf_field() ?><button type="submit" class="link">Sign out</button></form>
  </div>
</aside>
<main class="main">
  <header class="top"><button class="menu" type="button" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button><h1><?= e($title) ?></h1></header>
<?php foreach (flash() as [$type, $msg]): ?>
  <p class="msg <?= e($type) ?>"><?= e($msg) ?></p>
<?php endforeach ?>
  <?= $content ?>
</main>
<script src="/admin/admin.js?v=<?= $ver ?>"></script>
</body></html><?php
    exit;
}

function status_pill(string $s, array $labels): string { return '<span class="pill ' . e($s) . '">' . e($labels[$s] ?? ucfirst($s)) . '</span>'; }

function ago(?string $dt): string
{
    if (!$dt) return '';
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 86400 * 7) return floor($d / 86400) . ' d ago';
    return date('j M Y', strtotime($dt));
}

/** Returns [rows, pager HTML]. */
function paginate(string $sql, string $countSql, array $args = [], int $per = 30): array
{
    $total = (int)val($countSql, $args);
    $pages = max(1, (int)ceil($total / $per));
    $p = min($pages, max(1, (int)($_GET['p'] ?? 1)));
    $list = rows($sql . ' LIMIT ' . $per . ' OFFSET ' . (($p - 1) * $per), $args);
    $pager = '';
    if ($pages > 1) {
        $qs = $_GET; $pager = '<nav class="pager">';
        for ($i = 1; $i <= $pages; $i++) { $qs['p'] = $i; $pager .= $i === $p ? "<b>{$i}</b>" : '<a href="/admin/?' . e(http_build_query($qs)) . "\">{$i}</a>"; }
        $pager .= '<span>' . $total . ' total</span></nav>';
    }
    return [$list, $pager];
}

/** One form field. $f = [label, type, …options] (see app/admin/resources.php). */
function field_html(string $k, array $f, $v): string
{
    [$label, $type] = $f;
    $req = in_array('required', $f, true) ? ' required' : '';
    $ro = in_array('readonly', $f, true) ? ' readonly' : '';
    $help = !empty($f['help']) ? '<small class="help">' . e($f['help']) . '</small>' : '';
    $max = !empty($f['max']) ? ' data-max="' . (int)$f['max'] . '"' : '';
    $id = 'f_' . $k;
    $v = (string)$v;
    switch ($type) {
        case 'textarea':
            $in = "<textarea id=\"{$id}\" name=\"{$k}\" rows=\"3\"{$req}{$max}>" . e($v) . '</textarea>';
            break;
        case 'html':
            $in = '<div class="ed"><div class="ed-bar" data-for="' . $id . '">' .
                '<button type="button" data-cmd="h2">H2</button><button type="button" data-cmd="h3">H3</button><button type="button" data-cmd="p">¶</button>' .
                '<button type="button" data-cmd="b"><b>B</b></button><button type="button" data-cmd="i"><i>I</i></button>' .
                '<button type="button" data-cmd="ul">• List</button><button type="button" data-cmd="ol">1. List</button>' .
                '<button type="button" data-cmd="a">Link</button><button type="button" data-cmd="img">Image</button>' .
                '<span class="sp"></span><button type="button" data-cmd="preview">Preview</button></div>' .
                "<textarea id=\"{$id}\" name=\"{$k}\" class=\"html\" rows=\"22\" spellcheck=\"true\">" . e($v) . '</textarea><div class="ed-prev prose" hidden></div></div>';
            break;
        case 'select':
            $in = "<select id=\"{$id}\" name=\"{$k}\">";
            foreach ($f['options'] as $ov => $ol) $in .= '<option value="' . e($ov) . '"' . ((string)$ov === $v ? ' selected' : '') . '>' . e($ol) . '</option>';
            $in .= '</select>';
            break;
        case 'check':
            return "<label class=\"check\"><input type=\"checkbox\" name=\"{$k}\" value=\"1\"" . ($v ? ' checked' : '') . "> " . e($label) . "</label>{$help}";
        case 'date':
            $in = "<input id=\"{$id}\" type=\"date\" name=\"{$k}\" value=\"" . e(substr($v, 0, 10)) . "\"{$req}>";
            break;
        case 'number':
            $in = "<input id=\"{$id}\" type=\"number\" name=\"{$k}\" value=\"" . e($v) . "\"{$req}>";
            break;
        case 'slug':
            $in = '<div class="slug"><span>' . e($f['prefix'] ?? '/') . "</span><input id=\"{$id}\" name=\"{$k}\" value=\"" . e($v) . '"' . $ro . (!empty($f['from']) ? ' data-from="f_' . e($f['from']) . '"' : '') . ' pattern="[a-z0-9-]*"></div>';
            break;
        case 'image':
            $in = '<div class="img-field"><img src="' . e($v) . '" alt=""' . ($v === '' ? ' hidden' : '') . '>' .
                "<input id=\"{$id}\" name=\"{$k}\" value=\"" . e($v) . "\" placeholder=\"/uploads/… or /images/…\"{$req}>" .
                '<label class="btn ghost small">Upload<input type="file" accept="image/*" data-upload="' . $id . '" hidden></label></div>';
            break;
        case 'gallery':
            $in = '<div class="gal-field" data-for="' . $id . '"><div class="gal-items"></div><label class="btn ghost small">Add photos<input type="file" accept="image/*" multiple data-gallery="' . $id . '" hidden></label></div>' .
                "<input type=\"hidden\" id=\"{$id}\" name=\"{$k}\" value=\"" . e($v ?: '[]') . '">';
            break;
        default:
            $dl = '';
            if (!empty($f['datalist'])) {
                $opts = array_map('current', rows($f['datalist']));
                $dl = '<datalist id="dl_' . $k . '">' . implode('', array_map(fn($o) => '<option value="' . e($o) . '">', $opts)) . '</datalist>';
            }
            $in = "<input id=\"{$id}\" name=\"{$k}\" value=\"" . e($v) . "\"{$req}{$ro}{$max}" . ($dl ? " list=\"dl_{$k}\"" : '') . ">{$dl}";
    }
    return "<div class=\"field field-{$type}\"><label for=\"{$id}\">" . e($label) . ($req ? ' <i>*</i>' : '') . "</label>{$in}{$help}</div>";
}
