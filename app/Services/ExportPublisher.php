<?php

namespace App\Services;

use App\Models\ExportRun;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportPublisher
{
    /** Public disk: storage/app/public → /storage/... */
    protected string $publicDisk = 'public';

    /** S3/R2 disk adı (yoksa null) */
    protected ?string $s3Disk;

    public function __construct()
    {
        $this->s3Disk = config('filesystems.disks.s3') ? 's3' : null;
    }

    /**
     * Kayıttaki XML'i yayınlar / yeniler.
     * - Public diskte mevcutsa (Filament FileUpload ile geldi), yolunu korur.
     * - S3/R2 yapılandırılmışsa aynı path ile R2'ye de yükler.
     * - Kayıt is_public=true + published_at=now ile güncellenir.
     */
    public function upload(ExportRun $run): void
    {
        // 1) Hedef path'i belirle
        $path = $run->path;
        if (blank($path)) {
            $basename = 'manual-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.xml';
            $path = 'exports/' . ($run->export_profile_id ?? 1) . '/manual/' . $basename;
        }

        // 2) Kaynak içeriği al
        // Tercih: public diskte dosya mevcutsa onu esas al
        $sourceDisk = $this->publicDisk;
        if (!Storage::disk($sourceDisk)->exists($path)) {
            // public'te yoksa, local 'path' farklı bir yerde olabilir: o zaman public'e kopyala
            // (örn. 'local' diske düşmüşse)
            if (Storage::disk('local')->exists($path)) {
                $contents = Storage::disk('local')->get($path);
                Storage::disk($this->publicDisk)->put($path, $contents, 'public');
            } else {
                // Kaynak yoksa hata ver, logla ve çık
                Log::warning('ExportPublisher: kaynak xml bulunamadı', ['path' => $path]);
                throw new \RuntimeException('XML kaynağı bulunamadı: ' . $path);
            }
        }

        // 3) S3/R2 varsa mirror et
        if ($this->s3Disk) {
            $stream = Storage::disk($this->publicDisk)->readStream($path);
            if ($stream === false) {
                throw new \RuntimeException('Public diskten stream alınamadı: ' . $path);
            }
            Storage::disk($this->s3Disk)->put($path, $stream, ['visibility' => 'public']);
        }

        // 4) Kaydı yayınlandı olarak işaretle
        $run->path = $path;
        $run->is_public = true;
        $run->published_at = now();
        $run->error = null;
        if (blank($run->publish_token)) {
            $run->publish_token = Str::random(32);
        }
        $run->save();
    }

    /**
     * Yayından kaldırır:
     * - S3/R2 varsa oradan siler,
     * - (İsteğe bağlı) public'teki kopyayı da silebilirsin; burada dosyayı bırakıyoruz,
     * - Kaydı is_public=false yapar.
     */
    public function delete(ExportRun $run): void
    {
        $path = $run->path;

        if ($this->s3Disk && !blank($path) && Storage::disk($this->s3Disk)->exists($path)) {
            Storage::disk($this->s3Disk)->delete($path);
        }

        // Public dosyayı korumak istiyorsan aşağıyı YORUMDA bırak.
        // Tamamen silmek istersen yorumdan çıkar:
        // if (!blank($path) && Storage::disk($this->publicDisk)->exists($path)) {
        //     Storage::disk($this->publicDisk)->delete($path);
        // }

        $run->is_public = false;
        $run->published_at = null;
        $run->save();
    }

    /**
     * (Opsiyonel) R2/S3 tarafında public URL üretmek istersen kullan.
     * Cloudflare R2 için genelde CDN domain’i .env ile verilir.
     */
    public function publicUrl(string $path): string
    {
        // Öncelik: S3/R2 CDN domain
        $cdn = rtrim((string) env('CDN_PUBLIC_BASE', ''), '/');
        if ($this->s3Disk && $cdn !== '') {
            return $cdn . '/' . ltrim($path, '/');
        }

        // Public disk URL’si (storage symlink)
        return Storage::disk($this->publicDisk)->url($path);
    }
}
