<?php
/*
 * Database updates for sites installed before a change. migrate() runs on every request but only does work
 * once per version: settings.db_version records the last version applied. Fresh installs set it directly.
 *
 *   2 — About page: team and testimonials tables, vision & mission settings, new About page sections.
 */
const DB_VERSION = 2;

function migrate(): void
{
    if ((int)setting('db_version', '1') >= DB_VERSION) return;
    try {
        if (!val("SHOW TABLES LIKE 'settings'")) return;           // not installed yet: /install/ does everything
        schema_apply();
        $seed = seed_data();
        about_seed($seed);
        $about = row('SELECT id, body FROM pages WHERE slug = "about"');
        if ($about) q('UPDATE pages SET body = ? WHERE id = ?', [about_body_v2((string)$about['body']), $about['id']]);
        q('INSERT INTO settings (k, v) VALUES ("db_version", ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [(string)DB_VERSION]);
        cache_purge_all();
    } catch (Throwable $e) {
        error_log('Morphora migration failed: ' . $e->getMessage());
        if (cfg('debug')) throw $e;
    }
}

/** Create any missing tables (every statement in schema.sql is CREATE TABLE IF NOT EXISTS). */
function schema_apply(): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents(APP . '/database/schema.sql'));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) db()->exec($stmt);
}

function seed_data(): array
{
    $seed = json_decode((string)@file_get_contents(APP . '/database/seed.json'), true);
    if (!is_array($seed)) throw new RuntimeException('app/database/seed.json is missing or invalid — upload it from the repository.');
    return $seed;
}

/** Draft team members, draft testimonials and the vision / mission text — only where nothing exists yet. */
function about_seed(array $seed): void
{
    foreach (['team', 'testimonials'] as $table) {
        if ((int)val("SELECT COUNT(*) FROM {$table}")) continue;
        foreach ($seed[$table] ?? [] as $r) {
            $cols = array_keys($r);
            q("INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($r));
        }
    }
    foreach (['about_vision', 'about_mission'] as $k) {
        if (isset($seed['settings'][$k])) q('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)', [$k, $seed['settings'][$k]]);
    }
}

/** Add the founder, vision, team and testimonial sections to an existing About page body (keeps any edits). */
function about_body_v2(string $body): string
{
    if (strpos($body, '<!--sc:team') !== false) return $body;
    $body = strtr($body, ['( 02 — Our values )' => '( 04 — Our values )', '( 03 — What we do )' => '( 06 — What we do )']);
    $top = "<!--sc:founder {\"label\":\"( 02 — Founder’s message )\"}-->\n\n<!--sc:vision {\"label\":\"( 03 — Vision &amp; mission )\"}-->\n\n";
    $team = "<!--sc:team {\"label\":\"( 05 — Our team )\"}-->\n\n";
    $quotes = "\n\n<!--sc:testimonials {\"label\":\"( 07 — Client stories )\"}-->\n";

    $at = strpos($body, '<section class="why');
    $body = $at !== false ? substr_replace($body, $top, $at, 0) : $body . "\n\n" . $top;
    $at = strpos($body, '<!--sc:nums');
    if ($at !== false && ($end = strpos($body, '-->', $at)) !== false) $body = substr_replace($body, "\n\n" . rtrim($team), $end + 3, 0);
    else $body .= "\n\n" . $team;
    return rtrim($body) . $quotes;
}
