<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Generic (non-image) file upload/store helper for the public web
 * content catalog — currently just App\Models\WebVideo::$videos (the
 * uploaded video file itself, as opposed to WebVideo::$thumbnail, which
 * goes through App\Helpers\WebImageUploader since it needs resizing).
 * Same "plain path under public/web/..." convention as WebImageUploader
 * — no storage:link dependency — just no resize step since there's
 * nothing to resize about a video file.
 */
class WebFileUploader
{
    /**
     * @param  UploadedFile $file        the uploaded file
     * @param  string       $subdirectory subfolder under public/web, e.g. "videos"
     * @return string       path relative to public/web, e.g. "videos/9c1e...-a1b2.mp4" — store this in the DB column
     */
    public static function upload(UploadedFile $file, string $subdirectory): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $filename = (string) Str::uuid().'.'.$extension;

        $subdirectory = trim($subdirectory, '/');
        $directory = public_path('web/'.$subdirectory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->move($directory, $filename);

        return $subdirectory.'/'.$filename;
    }

    /**
     * Deletes a previously uploaded file given the relative path stored
     * in the DB (as returned by upload()). Safe to call with null/empty.
     */
    public static function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $full = public_path('web/'.ltrim($relativePath, '/'));

        if (is_file($full)) {
            @unlink($full);
        }
    }

    /**
     * Public URL for a stored relative path.
     */
    public static function url(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        return asset('web/'.ltrim($relativePath, '/'));
    }
}
