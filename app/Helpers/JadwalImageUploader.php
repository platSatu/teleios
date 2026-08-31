<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Shared image store/delete helper for the Jadwal feature (currently
 * just App\Models\JadwalMataPelajaran::image) — every column stores a
 * path relative to public/jadwal, so every Jadwal\* controller that
 * accepts an image upload goes through here instead of hand-rolling its
 * own file-move logic.
 *
 * Deliberately saves straight into public/jadwal (not the
 * storage/app/public disk + symlink Company::logo/Profile::image use
 * elsewhere in this app) per the feature's own spec ("directory
 * penyimpan public/jadwal") — same reasoning as App\Helpers\
 * WebImageUploader for the public web content catalog: no storage:link
 * symlink to remember on a fresh deploy. Deliberately does NOT resize
 * (unlike WebImageUploader) — Jadwal's images are small catalog
 * thumbnails, not the web catalog's hero/cover images.
 */
class JadwalImageUploader
{
    /**
     * Store an uploaded image under public/jadwal/{$subdirectory}.
     *
     * @param  UploadedFile $file         the uploaded image
     * @param  string       $subdirectory subfolder under public/jadwal, e.g. "mata-pelajaran"
     * @return string       path relative to public/jadwal, e.g. "mata-pelajaran/9c1e...-a1b2.jpg" — store this in the DB column
     */
    public static function upload(UploadedFile $file, string $subdirectory): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $filename = (string) Str::uuid().'.'.$extension;

        $subdirectory = trim($subdirectory, '/');
        $directory = public_path('jadwal/'.$subdirectory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->move($directory, $filename);

        return $subdirectory.'/'.$filename;
    }

    /**
     * Deletes a previously uploaded image given the relative path stored
     * in the DB (as returned by upload()). Safe to call with null/empty
     * — no-ops rather than erroring, so callers can pass $model->image
     * straight through on replace/destroy without an extra empty-check.
     */
    public static function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $full = public_path('jadwal/'.ltrim($relativePath, '/'));

        if (is_file($full)) {
            @unlink($full);
        }
    }

    /**
     * Public URL for a stored relative path — use in Blade instead of
     * hand-building the /jadwal/... prefix everywhere.
     */
    public static function url(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        return asset('jadwal/'.ltrim($relativePath, '/'));
    }
}
