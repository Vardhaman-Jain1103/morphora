<?php
/*
 * Database updates for sites installed before a change. migrate() runs on every request but only does work
 * once per version: settings.db_version records the last version applied. Fresh installs set it directly.
 *
 *   2 - About page: team and testimonials tables, vision & mission settings, new About page sections.
 *   3 - Move client testimonials from About to the homepage.
 *   4 - Add editable SEO metadata to public content.
 *   5 - User roles (users.role) and article ownership (posts.created_by). Existing users become administrators.
 *   6 - Add vector icons and align numbers in the About page values section.
 */
const DB_VERSION = 6;

function migrate(): void
{
    if ((int)setting('db_version', '1') >= DB_VERSION) return;
    try {
        if (!val("SHOW TABLES LIKE 'settings'")) return;           // not installed yet: /install/ does everything
        schema_apply();
        seo_columns();
        role_columns();
        $seed = seed_data();
        about_seed($seed);
        $about = row('SELECT id, body FROM pages WHERE slug = "about"');
        if ($about) {
            $body = about_body_v2((string)$about['body']);
            $body = about_body_v3($body);
            $body = about_values_v6($body);
            q('UPDATE pages SET body = ? WHERE id = ?', [$body, $about['id']]);
        }
        q('INSERT INTO settings (k, v) VALUES ("db_version", ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [(string)DB_VERSION]);
        cache_purge_all();
    } catch (Throwable $e) {
        error_log('Morphora migration failed: ' . $e->getMessage());
        if (cfg('debug')) throw $e;
    }
}

/** Add Rank Math-style fields to existing content tables without affecting existing metadata. */
function seo_columns(): void
{
    $shared = [
        'focus_keyword' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'canonical_url' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'og_title' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'og_desc' => "VARCHAR(400) NOT NULL DEFAULT ''",
        'og_image' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'schema_type' => "VARCHAR(32) NOT NULL DEFAULT 'auto'",
        'noindex' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'nofollow' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach (['pages', 'posts', 'projects', 'cities', 'jobs'] as $table) {
        $columns = $shared;
        if ($table === 'jobs') {
            $columns['seo_title'] = "VARCHAR(255) NOT NULL DEFAULT ''";
            $columns['seo_desc'] = "VARCHAR(400) NOT NULL DEFAULT ''";
        }
        foreach ($columns as $column => $definition) {
            $exists = (int)val(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            if (!$exists) db()->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}

/** Roles: everyone who could sign in before roles existed had full access, so they become administrators. */
function role_columns(): void
{
    $has = fn(string $table, string $column) => (int)val(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );
    if (!$has('users', 'role')) {
        db()->exec("ALTER TABLE users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'subscriber' AFTER pass_hash");
        db()->exec("UPDATE users SET role = 'administrator'");
    }
    if (!$has('posts', 'created_by')) {
        db()->exec('ALTER TABLE posts ADD COLUMN created_by INT UNSIGNED NULL AFTER nofollow, ADD KEY created_by (created_by)');
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
    if (!is_array($seed)) throw new RuntimeException('app/database/seed.json is missing or invalid - upload it from the repository.');
    return $seed;
}

/** Draft team members, draft testimonials and the vision / mission text - only where nothing exists yet. */
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
    $body = strtr($body, ['( 02 - Our values )' => '( 04 - Our values )', '( 03 - What we do )' => '( 06 - What we do )']);
    $top = "<!--sc:founder {\"label\":\"( 02 - Founder’s message )\"}-->\n\n<!--sc:vision {\"label\":\"( 03 - Vision &amp; mission )\"}-->\n\n";
    $team = "<!--sc:team {\"label\":\"( 05 - Our team )\"}-->\n\n";

    $at = strpos($body, '<section class="why');
    $body = $at !== false ? substr_replace($body, $top, $at, 0) : $body . "\n\n" . $top;
    $at = strpos($body, '<!--sc:nums');
    if ($at !== false && ($end = strpos($body, '-->', $at)) !== false) $body = substr_replace($body, "\n\n" . rtrim($team), $end + 3, 0);
    else $body .= "\n\n" . $team;
    return rtrim($body);
}

/** Remove the old testimonial section from stored About page content. */
function about_body_v3(string $body): string
{
    $clean = preg_replace('/<!--sc:testimonials\s+\{.*?\}\s*-->\s*/s', '', $body);
    if ($clean === null) throw new RuntimeException('Could not remove the legacy About testimonials section.');
    return $clean;
}

/** Add homepage-style value icons to the About page while preserving its existing copy. */
function about_values_v6(string $body): string
{
    $icons = [
        '01' => '<path d="M24 39S9 30.3 9 19.7a8.2 8.2 0 0 1 15-4.8 8.2 8.2 0 0 1 15 4.8C39 30.3 24 39 24 39Z"/><path d="M17 24c2.2 2.2 4.8 2.2 7 0s4.8-2.2 7 0"/>',
        '02' => '<circle cx="24" cy="24" r="16"/><circle cx="24" cy="24" r="7"/><path d="M24 3v7M24 38v7M3 24h7M38 24h7M24 20v4l3 2"/>',
        '03' => '<path d="m24 5 14 5v12c0 10-5.8 16-14 21-8.2-5-14-11-14-21V10l14-5Z"/><path d="m17 23 5 5 10-11"/>',
        '04' => '<rect x="7" y="8" width="34" height="32" rx="2"/><path d="M7 20h16v20M23 8v12M23 20h18M32 20v20M12 14h5M28 14h8"/>',
        '05' => '<path d="M17 29c-3-2.2-5-5.6-5-9.5a12 12 0 1 1 24 0c0 3.9-2 7.3-5 9.5-1.5 1.1-2 2.7-2 4.5H19c0-1.8-.5-3.4-2-4.5Z"/><path d="M19 38h10M21 42h6M24 2v3M5 20h4M39 20h4M10 7l3 3M38 7l-3 3"/>',
    ];

    $updated = preg_replace_callback('~<section class="why sec alt">.*?</section>~s', function ($section) use ($icons) {
        if (stripos($section[0], 'stand for') === false) return $section[0];
        $html = preg_replace_callback('~<li class="rv">\s*<span class="mono">(\d{2})</span>(.*?)</li>~s', function ($card) use ($icons) {
            if (!isset($icons[$card[1]])) return $card[0];
            $icon = '<span class="vector-icon-wrap"><svg class="vector-icon" viewBox="0 0 48 48" aria-hidden="true" focusable="false">' . $icons[$card[1]] . '</svg></span>';
            return '<li class="rv"><div class="vals-mark">' . $icon . '<span class="mono">' . $card[1] . '</span></div>' . $card[2] . '</li>';
        }, $section[0]);
        if ($html === null) throw new RuntimeException('Could not add icons to the About page values section.');
        return $html;
    }, $body);
    if ($updated === null) throw new RuntimeException('Could not find the About page values section.');
    return $updated;
}
