<?php
/**
 * Secure image uploads: size limit, real MIME detection (finfo), image header
 * check, random file names and a non-executable upload directory.
 */

declare(strict_types=1);

function allowed_image_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

/**
 * Store an uploaded image under assets/uploads/<subdir>.
 * Returns ['ok' => true, 'path' => 'avatars/abc.jpg'] or ['ok' => false, 'error' => '...'].
 */
function store_uploaded_image(array $file, string $subdir = 'avatars'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Please choose an image to upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return ['ok' => false, 'error' => $tooBig ? 'The image is too large.' : 'The upload failed. Please try again.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $maxBytes = (int) config('uploads.max_bytes');
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'Images must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.'];
    }

    // Trust the file contents, never the browser-supplied type or name
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = allowed_image_types();
    if (!isset($types[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || ($info['mime'] ?? '') !== $mime) {
        return ['ok' => false, 'error' => 'The file is not a valid image.'];
    }
    [$width, $height] = $info;
    if ($width < 64 || $height < 64 || $width > 6000 || $height > 6000) {
        return ['ok' => false, 'error' => 'Images must be between 64 and 6000 pixels wide and tall.'];
    }

    $subdir = preg_replace('/[^a-z0-9_-]/', '', $subdir);
    $dir = APP_ROOT . '/' . config('uploads.dir') . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Upload folder is not writable.'];
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the image.'];
    }
    return ['ok' => true, 'path' => $subdir . '/' . $name];
}

/** Remove a previously uploaded file (only inside the uploads directory). */
function delete_uploaded_file(?string $relativePath): void
{
    if (!$relativePath || !preg_match('#^[a-z0-9_-]+/[a-f0-9]{32}\.(jpg|png|webp)$#', $relativePath)) {
        return;
    }
    $path = APP_ROOT . '/' . config('uploads.dir') . '/' . $relativePath;
    if (is_file($path)) {
        unlink($path);
    }
}
