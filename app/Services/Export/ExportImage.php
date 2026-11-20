<?php

namespace App\Services\Export;

use App\Models\MediaAsset;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * A public image prepared for export: re-encoded as JPEG (both dompdf and
 * PowerPoint read it reliably) and flattened onto white to drop transparency.
 */
final readonly class ExportImage
{
    private function __construct(
        public string $jpeg,
        public int $width,
        public int $height,
        public string $alt,
    ) {}

    /**
     * Returns null for private, non-image or missing files, so exports degrade gracefully.
     */
    public static function fromMedia(?MediaAsset $media, string $variant = 'large'): ?self
    {
        if ($media === null || ! $media->isPublic() || ! $media->isImage()) {
            return null;
        }

        $disk = Storage::disk($media->disk);
        $path = $media->variantPath($variant);

        if (! $disk->exists($path)) {
            return null;
        }

        $source = @imagecreatefromstring((string) $disk->get($path));

        if (! $source instanceof GdImage) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();

        return new self($jpeg, $width, $height, (string) ($media->alt_text ?? ''));
    }

    public function dataUri(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode($this->jpeg);
    }

    public function gd(): ?GdImage
    {
        $image = @imagecreatefromstring($this->jpeg);

        return $image instanceof GdImage ? $image : null;
    }

    /**
     * Largest size that fits inside the box while keeping the aspect ratio.
     *
     * @return array{0: int, 1: int}
     */
    public function fitWithin(int $maxWidth, int $maxHeight): array
    {
        $scale = min($maxWidth / $this->width, $maxHeight / $this->height, 1);

        return [max(1, (int) floor($this->width * $scale)), max(1, (int) floor($this->height * $scale))];
    }
}
