<?php
/*
 * Database updates for sites installed before a change. migrate() runs on every request but only does work
 * once per version: settings.db_version records the last version applied. Fresh installs set it directly.
 *
 *   2 - About page: team and testimonials tables, vision & mission settings, new About page sections.
 *   3 - Move client testimonials from About to the homepage.
 *   4 - Add editable SEO metadata to public content.
 *   5 - User roles (users.role) and article ownership (posts.created_by). Existing users become administrators.
 */
const DB_VERSION = 5;

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
