<?php

namespace App\Console\Commands;

use App\Helpers\WebImageUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Sekali jalan: kompres ulang gambar Web Content lama yang terlanjur besar
 * (public/web/images), dengan aturan yang sama seperti WebImageUploader
 * sekarang. JPG ditimpa di tempat (nama sama); PNG/WebP jadi .webp dan
 * kolom DB yang menunjuk ke file itu ikut diperbarui. File hanya diganti
 * kalau hasilnya memang lebih kecil. Jalankan dengan --dry-run dulu.
 */
class OptimizeWebImages extends Command
{
    protected $signature = 'web:optimize-images
        {--dry-run : Tampilkan saja, tidak mengubah apa pun}
        {--min-kb=150 : Hanya proses file di atas ukuran ini}
        {--max-width=1600 : Lebar maksimal gambar}
        {--webp : JPG juga diubah ke WebP (biasanya jauh lebih kecil)}';

    protected $description = 'Kompres ulang gambar Web Content yang besar supaya bizbos.id lebih cepat';

    /** Kolom yang menyimpan path relatif ke public/web/images. */
    private const COLUMNS = [
        'web_articles' => ['images', 'meta_images'],
        'web_category_articles' => ['images'],
        'web_category_videos' => ['thumbnail'],
        'web_videos' => ['thumbnail', 'meta_images'],
        'web_features' => ['images'],
        'web_footers' => ['background_image'],
        'web_headers' => ['background_images', 'thumbnail_images', 'thumbnail_background_images'],
        'web_home_sections' => ['background_image', 'media_image'],
        'web_home_section_items' => ['image'],
        'web_pages' => ['hero_image', 'meta_image'],
        'web_settings' => ['favicon', 'logo', 'meta_images', 'icon_instagram', 'icon_facebook', 'icon_youtube', 'icon_tiktok'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $minBytes = (int) $this->option('min-kb') * 1024;
        $maxWidth = (int) $this->option('max-width');
        $manager = ImageManager::gd();
        $saved = 0;

        foreach ($this->referencedPaths() as $path => $refs) {
            $full = public_path('web/images/'.$path);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! is_file($full) || filesize($full) < $minBytes || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            try {
                $image = $manager->read($full);
                $image->scaleDown(width: $maxWidth);
                $keepFormat = str_starts_with($path, 'settings/');
                [$newExtension, $encoded] = $this->option('webp') && ! $keepFormat && in_array($extension, ['jpg', 'jpeg'], true)
                    ? ['webp', $image->toWebp(quality: WebImageUploader::QUALITY)]
                    : WebImageUploader::encode($image, $extension, $keepFormat);
            } catch (Throwable $e) {
                $this->warn("Lewati {$path}: {$e->getMessage()}");

                continue;
            }

            $before = filesize($full);
            $after = $encoded ? $encoded->size() : $before;

            if (! $encoded || $after >= $before) {
                continue;
            }

            $newPath = preg_replace('/\.[a-z]+$/i', '.'.$newExtension, $path);
            $this->line(sprintf('%-70s %6d KB -> %5d KB', $path.($newPath !== $path ? " -> .{$newExtension}" : ''), $before / 1024, $after / 1024));
            $saved += $before - $after;

            if ($dryRun) {
                continue;
            }

            $encoded->save(public_path('web/images/'.$newPath));

            if ($newPath !== $path) {
                DB::transaction(function () use ($refs, $path, $newPath) {
                    foreach ($refs as [$table, $column]) {
                        DB::table($table)->where($column, $path)->update([$column => $newPath]);
                    }
                });
                @unlink($full);
            }
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Hemat total: '.round($saved / 1048576, 1).' MB');

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{0: string, 1: string}>> path => [[table, column], ...]
     */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach (DB::table($table)->whereNotNull($column)->distinct()->pluck($column) as $path) {
                    if ($path !== '' && ! str_contains($path, '..')) {
                        $paths[$path][] = [$table, $column];
                    }
                }
            }
        }

        return $paths;
    }
}
