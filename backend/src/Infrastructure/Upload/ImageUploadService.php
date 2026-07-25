<?php

declare(strict_types=1);

namespace App\Infrastructure\Upload;

use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Upload immagini: validazione (jpg/png/webp, max UPLOAD_MAX_MB),
 * ottimizzazione con GD (resize a max 1600px lato lungo, ricompressione).
 */
final class ImageUploadService
{
    private const MAX_DIMENSION = 1600;
    private const JPEG_QUALITY = 82;
    private const WEBP_QUALITY = 82;
    private const ALLOWED_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly string $baseDir)
    {
    }

    public function maxBytes(): int
    {
        $mb = (int) ($_ENV['UPLOAD_MAX_MB'] ?? getenv('UPLOAD_MAX_MB') ?: 8);
        return max(1, $mb) * 1024 * 1024;
    }

    /**
     * Salva un'immagine ottimizzata in {baseDir}/{subPath}/ e ritorna il
     * percorso relativo (da servire via /api/files/...).
     *
     * @throws RuntimeException per file non validi
     */
    public function store(UploadedFileInterface $file, string $subPath): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload non riuscito (errore ' . $file->getError() . ').');
        }
        if ($file->getSize() === null || $file->getSize() > $this->maxBytes()) {
            throw new RuntimeException('File troppo grande: massimo ' . ($this->maxBytes() / 1024 / 1024) . ' MB.');
        }

        // Il mime dichiarato dal client non è affidabile: leggiamo i byte reali
        $tmpPath = $file->getStream()->getMetadata('uri');
        if (!is_string($tmpPath) || !is_file($tmpPath)) {
            // Stream non su file (raro): scriviamo su tmp
            $tmpPath = tempnam(sys_get_temp_dir(), 'rtupl');
            file_put_contents($tmpPath, (string) $file->getStream());
        }

        $info = @getimagesize($tmpPath);
        if ($info === false || !isset(self::ALLOWED_MIME[$info['mime']])) {
            throw new RuntimeException('Formato non supportato: usa JPG, PNG o WEBP.');
        }

        $mime = $info['mime'];
        [$width, $height] = $info;

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($tmpPath),
            'image/png' => imagecreatefrompng($tmpPath),
            'image/webp' => imagecreatefromwebp($tmpPath),
        };
        if ($src === false) {
            throw new RuntimeException('Immagine corrotta o illeggibile.');
        }

        // Resize proporzionale se supera MAX_DIMENSION
        $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
        if ($scale < 1.0) {
            $newW = (int) round($width * $scale);
            $newH = (int) round($height * $scale);
            $resized = imagecreatetruecolor($newW, $newH);
            // Sfondo bianco per PNG con trasparenza convertite in JPEG
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($src);
            $src = $resized;
        }

        $dir = rtrim($this->baseDir, '/') . '/' . trim($subPath, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException('Impossibile creare la cartella di destinazione.');
        }

        $ext = $mime === 'image/webp' ? 'webp' : 'jpg';
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $dir . '/' . $filename;

        $ok = $ext === 'webp'
            ? imagewebp($src, $destPath, self::WEBP_QUALITY)
            : imagejpeg($src, $destPath, self::JPEG_QUALITY);
        imagedestroy($src);

        if (!$ok) {
            throw new RuntimeException('Salvataggio immagine non riuscito.');
        }

        return trim($subPath, '/') . '/' . $filename;
    }

    public function delete(string $relativePath): void
    {
        $path = $this->resolve($relativePath);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /** Risolve un percorso relativo dentro baseDir, bloccando i path traversal. */
    public function resolve(string $relativePath): ?string
    {
        $base = realpath($this->baseDir);
        if ($base === false) {
            return null;
        }
        $full = realpath($base . '/' . ltrim($relativePath, '/'));
        if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $full;
    }
}
