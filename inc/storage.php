<?php
// Subida de imágenes del panel. Se re-codifican a WebP con GD: se descarta cualquier archivo
// que no sea una imagen real y se eliminan los metadatos. Los archivos quedan en /uploads.
declare(strict_types=1);

function save_image(array $file, string $folder, int $maxWidth = 1600): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new UserError('No se pudo subir la imagen.');
    if ($file['size'] > 5 * 1024 * 1024) throw new UserError('La imagen supera los 5 MB.');
    if (!in_array($folder, ['productos', 'marca', 'portada'], true)) throw new UserError('Carpeta no válida.');

    $info = @getimagesize($file['tmp_name']);
    $type = $info[2] ?? 0;
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => (defined('IMAGETYPE_AVIF') && $type === IMAGETYPE_AVIF && function_exists('imagecreatefromavif')) ? @imagecreatefromavif($file['tmp_name']) : false,
    };
    if (!$img) throw new UserError('El archivo no es una imagen válida. Usa JPG, PNG o WebP.');
    if ($info[0] * $info[1] > 40_000_000) throw new UserError('La imagen es demasiado grande.');

    // Orientación de fotos de celular (EXIF)
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = @exif_read_data($file['tmp_name'])['Orientation'] ?? 1;
        $img = match ((int) $o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
    $w = imagesx($img);
    if ($w > $maxWidth) $img = imagescale($img, $maxWidth);
    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $dir = dirname(__DIR__) . "/uploads/$folder";
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new UserError('No se pudo crear la carpeta de imágenes. Revisa los permisos de /uploads.');
    $name = base_convert((string) time(), 10, 36) . '-' . bin2hex(random_bytes(6)) . '.webp';
    if (!imagewebp($img, "$dir/$name", 82)) throw new UserError('No se pudo guardar la imagen.');
    imagedestroy($img);
    return "uploads/$folder/$name";
}
