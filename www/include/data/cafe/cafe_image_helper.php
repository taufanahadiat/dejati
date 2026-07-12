<?php

const CAFE_PRODUCT_UPLOAD_DIR = __DIR__ . '/../../../dist/img/products/';
const CAFE_PRODUCT_UPLOAD_URL = '/dist/img/products/';
const CAFE_PRODUCT_THUMB_DIR = __DIR__ . '/../../../dist/img/products/thumbs/';
const CAFE_PRODUCT_THUMB_URL = '/dist/img/products/thumbs/';
const CAFE_PRODUCT_PLACEHOLDER_URL = '/dist/img/default-150x150.png';
const CAFE_PRODUCT_MAX_WIDTH = 1200;
const CAFE_PRODUCT_THUMB_SIZE = 240;
const CAFE_PRODUCT_JPEG_QUALITY = 82;

function cafe_sanitize_filename($string)
{
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9]+/i', '_', $string);
    return trim($string, '_');
}

function cafe_is_remote_image_url($value)
{
    return (bool)preg_match('#^https?://#i', $value);
}

function cafe_local_image_path($foto)
{
    if (empty($foto) || cafe_is_remote_image_url($foto)) {
        return null;
    }

    return CAFE_PRODUCT_UPLOAD_DIR . basename($foto);
}

function cafe_local_image_exists($foto)
{
    $path = cafe_local_image_path($foto);
    return $path && is_file($path);
}

function cafe_delete_local_image($foto)
{
    $path = cafe_local_image_path($foto);
    if ($path && is_file($path)) {
        unlink($path);
    }

    $thumbPath = cafe_local_thumb_path($foto);
    if ($thumbPath && is_file($thumbPath)) {
        unlink($thumbPath);
    }
}

function cafe_product_image_url($foto)
{
    $foto = trim((string)$foto);
    if ($foto === '') {
        return CAFE_PRODUCT_PLACEHOLDER_URL;
    }

    if (cafe_local_image_exists($foto)) {
        return CAFE_PRODUCT_UPLOAD_URL . rawurlencode(basename($foto));
    }

    if (cafe_is_remote_image_url($foto)) {
        return $foto;
    }

    if (defined('CDN_BASE')) {
        return rtrim(CDN_BASE, '/') . '/img/products/' . rawurlencode(basename($foto));
    }

    return CAFE_PRODUCT_PLACEHOLDER_URL;
}

function cafe_local_thumb_path($foto)
{
    if (empty($foto) || cafe_is_remote_image_url($foto)) {
        return null;
    }

    return CAFE_PRODUCT_THUMB_DIR . pathinfo(basename($foto), PATHINFO_FILENAME) . '.jpg';
}

function cafe_product_thumb_url($foto)
{
    $foto = trim((string)$foto);
    if ($foto === '') {
        return CAFE_PRODUCT_PLACEHOLDER_URL;
    }

    if (!cafe_local_image_exists($foto)) {
        return cafe_product_image_url($foto);
    }

    $thumbPath = cafe_local_thumb_path($foto);
    if ($thumbPath && !is_file($thumbPath)) {
        cafe_generate_product_thumbnail($foto, $thumbPath);
    }

    if ($thumbPath && is_file($thumbPath)) {
        return CAFE_PRODUCT_THUMB_URL . rawurlencode(basename($thumbPath));
    }

    return cafe_product_image_url($foto);
}

function cafe_orient_gd_image($source, $sourcePath, $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data') || !function_exists('imagerotate')) {
        return $source;
    }

    $exif = @exif_read_data($sourcePath);
    $orientation = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;

    switch ($orientation) {
        case 3:
            $rotated = imagerotate($source, 180, 0);
            break;
        case 6:
            $rotated = imagerotate($source, -90, 0);
            break;
        case 8:
            $rotated = imagerotate($source, 90, 0);
            break;
        default:
            $rotated = $source;
            break;
    }

    if ($rotated !== $source) {
        imagedestroy($source);
    }

    return $rotated ?: $source;
}

function cafe_generate_product_thumbnail($foto, $thumbPath)
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return false;
    }

    $sourcePath = cafe_local_image_path($foto);
    if (!$sourcePath || !is_file($sourcePath)) {
        return false;
    }

    $imageInfo = @getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }

    if (!is_dir(CAFE_PRODUCT_THUMB_DIR)) {
        mkdir(CAFE_PRODUCT_THUMB_DIR, 0777, true);
    }

    [$width, $height] = $imageInfo;
    if ($width <= 0 || $height <= 0) {
        return false;
    }

    if ($imageInfo['mime'] === 'image/jpeg') {
        $source = @imagecreatefromjpeg($sourcePath);
    } elseif ($imageInfo['mime'] === 'image/png') {
        $source = @imagecreatefrompng($sourcePath);
    } else {
        return false;
    }

    if (!$source) {
        return false;
    }

    $source = cafe_orient_gd_image($source, $sourcePath, $imageInfo['mime']);
    if (!$source) {
        return false;
    }

    $width = imagesx($source);
    $height = imagesy($source);

    $thumbSize = CAFE_PRODUCT_THUMB_SIZE;
    $scale = max($thumbSize / $width, $thumbSize / $height);
    $scaledWidth = (int)ceil($width * $scale);
    $scaledHeight = (int)ceil($height * $scale);
    $offsetX = (int)floor(($thumbSize - $scaledWidth) / 2);
    $offsetY = (int)floor(($thumbSize - $scaledHeight) / 2);

    $target = imagecreatetruecolor($thumbSize, $thumbSize);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefilledrectangle($target, 0, 0, $thumbSize, $thumbSize, $white);
    imagecopyresampled($target, $source, $offsetX, $offsetY, 0, 0, $scaledWidth, $scaledHeight, $width, $height);

    $saved = imagejpeg($target, $thumbPath, 76);
    imagedestroy($source);
    imagedestroy($target);

    return $saved;
}

function cafe_validate_uploaded_image($file)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return 'upload error';
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return 'Invalid file type.';
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png'];
    if (!in_array($imageInfo['mime'], $allowedMimeTypes, true)) {
        return 'Invalid file type.';
    }

    return null;
}

function cafe_compress_uploaded_image($file, $destination)
{
    $error = cafe_validate_uploaded_image($file);
    if ($error !== null) {
        return $error;
    }

    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return 'Image compression is unavailable.';
    }

    if (!is_dir(CAFE_PRODUCT_UPLOAD_DIR)) {
        mkdir(CAFE_PRODUCT_UPLOAD_DIR, 0777, true);
    }

    $imageInfo = getimagesize($file['tmp_name']);
    [$width, $height] = $imageInfo;

    if ($imageInfo['mime'] === 'image/jpeg') {
        $source = imagecreatefromjpeg($file['tmp_name']);
    } else {
        $source = imagecreatefrompng($file['tmp_name']);
    }

    if (!$source) {
        return 'Invalid image.';
    }

    $targetWidth = min($width, CAFE_PRODUCT_MAX_WIDTH);
    $targetHeight = (int)round($height * ($targetWidth / $width));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    $saved = imagejpeg($target, $destination, CAFE_PRODUCT_JPEG_QUALITY);
    imagedestroy($source);
    imagedestroy($target);

    if ($saved) {
        $savedName = basename($destination);
        $thumbPath = cafe_local_thumb_path($savedName);
        if ($thumbPath) {
            cafe_generate_product_thumbnail($savedName, $thumbPath);
        }
    }

    return $saved ? null : 'failed';
}
