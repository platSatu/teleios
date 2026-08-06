<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Shared image resize/store helper for the public web content catalog
 * (App\Models\WebCategoryArticle, WebArticle, WebCategoryVideo, WebVideo,
 * ...) — every one of those tables' `images`/`thumbnail` columns stores a
 * path relative to public/web/images, and every superadmin controller
 * that accepts an upload for one of those columns goes through here
 * instead of hand-rolling its own Storage/resize calls, so resize
 * dimensions and the on-disk layout stay consistent across the whole
 * catalog.
 *
 * Deliberately saves straight into public/web/images (not the
 * storage/app/public disk + symlink Company::logo and Profile::image
 * use elsewhere in this app) because this catalog's images are meant to
 * be served directly by the eventual public-facing web/blog pages at a
 * plain /web/images/... URL, same as the theme's own static assets
 * under public/be — no storage:link dependency to remember on a fresh
 * deploy.
 *
 * Uses Intervention Image's GD driver (Intervention\Image, ^3.11 —
 * see composer.json). Swap ImageManager::gd() for ImageManager::imagick()
 * below if a given server doesn't have the GD extension enabled but does
 * have Imagick.
 */
class WebImageUploader
{
    /**
     * Resize (downscale only, aspect ratio preserved — never upscales a
     * smaller source image) and store an uploaded image.
     *
     * @param  UploadedFile $file        the uploaded image
     * @param  string       $subdirectory subfolder under public/web/images, e.g. "category-articles"
     * @param  int          $maxWidth     images wider than this are scaled down to it; taller-than-wide images are left alone width-wise
     * @return string       path relative to public/web/images, e.g. "category-articles/9c1e...-a1b2.jpg" — store this in the DB column
     */
    public static function upload(UploadedFile $file, string $subdirectory, int $maxWidth = 1600): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($file->getRealPath());

        $image->scaleDown(width: $maxWidth);

        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $filename = (string) Str::uuid().'.'.$extension;

        $subdirectory = trim($subdirectory, '/');
        $directory = public_path('web/images/'.$subdirectory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $image->save($directory.'/'.$filename);

        return $subdirectory.'/'.$filename;
    }

    /**
     * Same as upload(), but crops to an exact width x height (center
     * crop) instead of just scaling down — for fixed-aspect thumbnails
     * (e.g. video/article category thumbnails shown in a uniform grid)
     * where a consistent shape matters more than preserving the
     * original aspect ratio.
     *
     * @return string path relative to public/web/images
     */
    public static function uploadCover(UploadedFile $file, string $subdirectory, int $width, int $height): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($file->getRealPath());

        $image->cover($width, $height);

        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $filename = (string) Str::uuid().'.'.$extension;

        $subdirectory = trim($subdirectory, '/');
        $directory = public_path('web/images/'.$subdirectory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $image->save($directory.'/'.$filename);

        return $subdirectory.'/'.$filename;
    }

    /**
     * Deletes a previously uploaded image given the relative path stored
     * in the DB (as returned by upload()/uploadCover()). Safe to call
     * with null/empty — no-ops rather than erroring, so callers can pass
     * $model->images straight through on replace/destroy without an
     * extra empty-check.
     */
    public static function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $full = public_path('web/images/'.ltrim($relativePath, '/'));

        if (is_file($full)) {
            @unlink($full);
        }
    }

    /**
     * Public URL for a stored relative path — use in Blade instead of
     * hand-building the /web/images/... prefix everywhere.
     */
    public static function url(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        return asset('web/images/'.ltrim($relativePath, '/'));
    }
}
