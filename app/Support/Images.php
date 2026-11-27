<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Images envoyées par les utilisateurs : on accepte jusqu'à 30 Mo partout, puis on
 * adapte selon l'usage. Les avatars (personnes, projets) sont recadrés et fortement
 * réduits ; les images jointes aux tâches, commentaires et actualités restent intactes
 * (seule une vignette de couverture est générée à part).
 */
class Images
{
    /** Taille maximale d'une image envoyée, en kilo-octets (30 Mo). */
    public const MAX_UPLOAD_KB = 30720;

    /** Côté des avatars enregistrés, en pixels (suffisant pour les écrans haute densité). */
    public const AVATAR_SIZE = 256;

    /** Au-delà, décoder l'image demanderait trop de mémoire (≈ 5 octets par pixel). */
    public const MAX_PIXELS = 100_000_000;

    /** Règles de validation d'une image d'avatar. */
    public static function avatarRules(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'image', 'max:'.self::MAX_UPLOAD_KB];
    }

    /** Message lisible pour la règle « max » (sinon Laravel parle en kilo-octets). */
    public static function maxMessage(): string
    {
        return __('L’image doit faire moins de 30 Mo.');
    }

    /**
     * Recadre l'image au centre, la réduit à 256 px et la recompresse en JPEG léger.
     *
     * @throws ValidationException si l'image ne peut pas être lue
     */
    public static function avatarJpeg(UploadedFile $file, string $field): string
    {
        $source = self::load((string) $file->getRealPath());
        if (! $source) {
            throw ValidationException::withMessages([
                $field => __('Cette image n’a pas pu être lue. Essayez en JPG, PNG ou WebP.'),
            ]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $size = min(self::AVATAR_SIZE, $side);

        $square = imagecreatetruecolor($size, $size);
        // Fond blanc pour les images transparentes (PNG, GIF, WebP).
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled(
            $square, $source, 0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            $size, $size, $side, $side,
        );
        imagedestroy($source);

        return self::jpeg($square, 80);
    }

    /** Vignette JPEG d'au plus $maxWidth px de large, ou null si l'image ne peut pas être lue. */
    public static function thumbnailJpeg(string $path, int $maxWidth = 600): ?string
    {
        $image = self::load($path);
        if (! $image) {
            return null;
        }
        $width = min($maxWidth, imagesx($image));
        $height = max(1, (int) round(imagesy($image) * $width / imagesx($image)));
        $thumb = imagecreatetruecolor($width, $height);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        imagedestroy($image);

        return self::jpeg($thumb, 82);
    }

    /**
     * Décode une image avec GD en tenant compte de l'orientation EXIF (photos de téléphone).
     * La limite mémoire est relevée le temps du décodage : une photo de 30 Mo peut
     * dépasser 50 mégapixels une fois décompressée.
     */
    public static function load(string $path): ?GdImage
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg') || ! is_file($path)) {
            return null;
        }
        $size = @getimagesize($path);
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }

        self::raiseMemoryLimit((int) ($size[0] * $size[1] * 6) + 64 * 1024 * 1024);

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (! $image) {
            return null;
        }

        return self::orient($image, $path, $size[2] ?? null);
    }

    private static function orient(GdImage $image, string $path, ?int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        if ($orientation === 2 || $orientation === 4 || $orientation === 5 || $orientation === 7) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if ($angle) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        return $image;
    }

    private static function raiseMemoryLimit(int $needed): void
    {
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit < 0) {
            return;
        }
        $target = memory_get_usage(true) + $needed;
        if ($target > $limit) {
            @ini_set('memory_limit', (string) $target);
        }
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function jpeg(GdImage $image, int $quality): string
    {
        imageinterlace($image, true);
        ob_start();
        imagejpeg($image, null, $quality);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
