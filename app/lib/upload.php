<?php
/*
 * File uploads.
 *  - Images (admin): /uploads/YYYY/MM/<name>-<random>.<ext>, publicly served; PHP is disabled in /uploads.
 *  - CVs (careers):  app/storage/cv/<random>.<ext>, never public - only downloadable from the admin.
 */
const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
const CV_TYPES = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

function upload_error(array $f): ?string
{
    if (!isset($f['error']) || is_array($f['error'])) return 'Invalid upload.';
    switch ($f['error']) {
        case UPLOAD_ERR_OK: return null;
        case UPLOAD_ERR_NO_FILE: return 'No file was chosen.';
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: return 'The file is too large.';
        default: return 'The upload failed. Please try again.';
    }
}

function detect_mime(string $tmp): string
{
    $fi = new finfo(FILEINFO_MIME_TYPE);
    return (string)$fi->file($tmp);
}

/** Saves an uploaded image; returns [url, null] or [null, error]. */
function save_image(array $f, int $maxMb = 8): array
{
    if ($err = upload_error($f)) return [null, $err];
    if ($f['size'] > $maxMb * 1048576) return [null, "Images must be under {$maxMb} MB."];
    $mime = detect_mime($f['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime])) return [null, 'Only JPG, PNG, WebP, GIF or SVG images are allowed.'];
    if ($mime === 'image/svg+xml' && preg_match('/<script|on\w+\s*=|javascript:/i', (string)file_get_contents($f['tmp_name']))) return [null, 'This SVG contains scripts and was rejected.'];
    $dir = '/uploads/' . date('Y/m');
    if (!is_dir(ROOT . $dir) && !mkdir(ROOT . $dir, 0755, true)) return [null, 'Could not create the uploads folder.'];
    $base = substr(slugify(pathinfo($f['name'], PATHINFO_FILENAME)), 0, 60);
    $name = $base . '-' . bin2hex(random_bytes(3)) . '.' . IMAGE_TYPES[$mime];
    if (!move_uploaded_file($f['tmp_name'], ROOT . $dir . '/' . $name)) return [null, 'Could not save the file.'];
    @chmod(ROOT . $dir . '/' . $name, 0644);
    return [$dir . '/' . $name, null];
}

/** Saves an uploaded CV privately; returns [stored file name, null] or [null, error]. */
function save_cv(array $f): array
{
    if ($err = upload_error($f)) return [null, $err];
    if ($f['size'] > 5 * 1048576) return [null, 'Your CV must be under 5 MB.'];
    $mime = detect_mime($f['tmp_name']);
    if (!isset(CV_TYPES[$mime])) return [null, 'Please upload your CV as a PDF or Word file.'];
    $dir = APP . '/storage/cv';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return [null, 'Could not save your CV. Please email it to us instead.'];
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . CV_TYPES[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return [null, 'Could not save your CV. Please email it to us instead.'];
    return [$name, null];
}
