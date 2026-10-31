<?php

namespace App\Support;

use App\Models\SaleOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/** Shared, customer-facing presentation. Does not change orders or send mail. */
final class OrderEmailPresentation
{
    public static function items(SaleOrder $order): array
    {
        if ($order->exists) {
            $order->loadMissing('items.product');
        }

        return ($order->relationLoaded('items') ? $order->getRelation('items') : collect())->map(function ($item) {
            $product = $item->product;
            $description = self::text($item->description ?: $product?->name ?: 'Producto');
            $summary = self::summary($product?->description, $description, $product?->brand);
            $image = self::productImage($product?->image_path);

            return [
                'description' => $description,
                'short_description' => $summary,
                'part_number' => $item->part_number ?: $product?->part_number,
                'quantity' => (float) $item->quantity,
                'total_price' => (float) $item->total_price,
                'image_url' => $image['src'] ?? null,
                'image' => $image,
            ];
        })->all();
    }

    public static function summary(?string $text, string $title, ?string $brand = null): string
    {
        $text = self::text($text ?? '');

        return Str::limit($text !== '' && $text !== $title ? $text : ($brand ? 'Marca: '.self::text($brand) : ''), 150);
    }

    public static function text(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    public static function currency(?string $currency): string
    {
        return match (strtoupper($currency ?: 'NIO')) {
            'NIO' => 'C$', 'USD' => 'US$', 'EUR' => '€',
            default => strtoupper($currency).' ',
        };
    }

    public static function logo(): ?array
    {
        return self::inlineImage(public_path('images/logo-email.png'));
    }

    public static function productImage(?string $storedPath): ?array
    {
        if (! $storedPath) {
            return null;
        }
        if (filter_var($storedPath, FILTER_VALIDATE_URL)) {
            if (parse_url($storedPath, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)) {
                return null; // Never download arbitrary remote URLs while sending mail.
            }
            $storedPath = rawurldecode((string) parse_url($storedPath, PHP_URL_PATH));
        }
        $relative = ltrim(str_replace('\\', '/', $storedPath), '/');
        $storageRelative = preg_replace('#^storage/#', '', $relative);
        $candidates = [$storedPath, storage_path('app/public/'.$storageRelative), public_path($relative), public_path('storage/'.$relative)];
        $roots = array_filter([realpath(public_path()), realpath(storage_path('app/public'))]);
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if (! $path || ! is_file($path) || ! collect($roots)->contains(fn ($root) => str_starts_with($path, $root.DIRECTORY_SEPARATOR))) {
                continue;
            }
            $size = @getimagesize($path);
            if (! $size || ! in_array($size['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
                || filesize($path) > 8 * 1024 * 1024 || $size[0] > 6000 || $size[1] > 6000) {
                return null;
            }
            if (! function_exists('imagecreatefromstring')) {
                return $size['mime'] === 'image/webp' ? null : self::inlineImage($path);
            }
            // PNG thumbnails are compatible with mail clients that cannot show WebP.
            $directory = storage_path('app/private/mail-images');
            if (! is_dir($directory)) {
                mkdir($directory, 0750, true);
            }
            $thumbPath = $directory.'/'.hash_file('sha256', $path).'.png';
            if (! is_file($thumbPath)) {
                $source = @imagecreatefromstring(file_get_contents($path));
                if (! $source) {
                    return null;
                }
                $scale = min(1, 256 / max($size[0], $size[1]));
                $thumb = imagecreatetruecolor(max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale)));
                imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
                imagecopyresampled($thumb, $source, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $size[0], $size[1]);
                $temporary = $thumbPath.'.'.bin2hex(random_bytes(5)).'.tmp';
                imagepng($thumb, $temporary);
                rename($temporary, $thumbPath);
                imagedestroy($thumb);
                imagedestroy($source);
            }

            return self::inlineImage($thumbPath);
        }

        return null;
    }

    private static function inlineImage(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $cid = 'quantum-'.substr(hash_file('sha256', $path), 0, 24).'@quantumsolucionesnic.com';

        return ['src' => 'cid:'.$cid, 'cid' => $cid, 'path' => $path, 'mime' => mime_content_type($path)];
    }

    public static function embed(MailMessage $mail, array $images): MailMessage
    {
        $images = collect($images)->filter()->unique('cid')->values()->all();

        return $mail->withSymfonyMessage(function (Email $message) use ($images) {
            foreach ($images as $image) {
                $message->addPart((new DataPart(new File($image['path']), basename($image['path']), $image['mime']))->asInline()->setContentId($image['cid']));
            }
        });
    }
}
