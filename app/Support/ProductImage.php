<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class ProductImage
{
    /** @return list<string> */
    public static function paths(string $imagePath): array
    {
        if (! str_ends_with($imagePath, '-1600.webp')) {
            return [$imagePath];
        }

        $basePath = substr($imagePath, 0, -strlen('1600.webp'));

        return [
            $basePath.'640.webp',
            $basePath.'1024.webp',
            $imagePath,
        ];
    }

    public static function srcSet(string $imagePath): ?string
    {
        $widths = [640, 1024, 1600];
        $sources = [];

        foreach (self::paths($imagePath) as $index => $path) {
            if (Storage::disk('public')->exists($path)) {
                $sources[] = Storage::disk('public')->url($path).' '.$widths[$index].'w';
            }
        }

        return $sources === [] ? null : implode(', ', $sources);
    }
}
