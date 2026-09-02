<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Shared file store/delete helper untuk fitur Form (background header +
 * jawaban file_upload). Ikut pola App\Helpers\JadwalImageUploader persis
 * -- simpan langsung ke public/form (bukan storage disk + symlink),
 * cuma folder root-nya "form" bukan "jadwal".
 */
class FormImageUploader
{
    /**
     * Simpan file ke public/form/{$subdirectory}.
     *
     * @param  UploadedFile $file
     * @param  string       $subdirectory subfolder di bawah public/form, mis. "background" atau "submissions"
     * @return string       path relatif terhadap public/form, mis. "background/9c1e...-a1b2.jpg"
     */
    public static function upload(UploadedFile $file, string $subdirectory): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $filename = (string) Str::uuid().'.'.$extension;

        $subdirectory = trim($subdirectory, '/');
        $directory = public_path('form/'.$subdirectory);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->move($directory, $filename);

        return $subdirectory.'/'.$filename;
    }

    public static function delete(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $full = public_path('form/'.ltrim($relativePath, '/'));

        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function url(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        return asset('form/'.ltrim($relativePath, '/'));
    }
}
