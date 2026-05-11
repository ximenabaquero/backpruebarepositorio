<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Comprime y guarda imágenes usando la extensión GD nativa de PHP.
 * Sin dependencias externas. Reduce imágenes de celular ~80-90%.
 *
 * Resultado: JPEG a calidad 82, máximo 1600px de ancho.
 * Una foto de 5 MB queda en ~250-400 KB.
 */
class ImageHelper
{
    private const MAX_WIDTH   = 1600;
    private const JPEG_QUALITY = 82;

    /**
     * Comprime la imagen, la guarda en el disco y devuelve la ruta.
     * Si GD no puede leer el formato, guarda el archivo tal cual (fallback).
     */
    public static function compressAndStore(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
    ): string {
        $mime   = $file->getMimeType();
        $source = self::createFromFile($file->getRealPath(), $mime);

        // Sin GD o formato no soportado → guardar sin comprimir
        if (!$source) {
            return $file->store($directory, $disk);
        }

        $origW = imagesx($source);
        $origH = imagesy($source);

        // Redimensionar solo si supera el ancho máximo
        if ($origW > self::MAX_WIDTH) {
            $ratio  = self::MAX_WIDTH / $origW;
            $newW   = self::MAX_WIDTH;
            $newH   = (int) round($origH * $ratio);
            $canvas = imagecreatetruecolor($newW, $newH);

            // Fondo blanco para PNGs con transparencia
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);

            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($source);
            $source = $canvas;
        }

        // Capturar el JPEG en memoria y guardar en Storage
        ob_start();
        imagejpeg($source, null, self::JPEG_QUALITY);
        $data = ob_get_clean();
        imagedestroy($source);

        $path = $directory . '/' . uniqid('img_') . '.jpg';
        Storage::disk($disk)->put($path, $data);

        return $path;
    }

    private static function createFromFile(string $path, ?string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png'               => @imagecreatefrompng($path),
            'image/webp'              => @imagecreatefromwebp($path),
            default                   => false,
        };
    }
}
