<?php

declare(strict_types=1);

namespace App\Import;

use App\Storage\Storage;

final class HttpImageFetcher implements ImageFetcher
{
    public function __construct(
        private readonly UrlSafetyChecker $urls,
        private readonly Storage $storage,
        private readonly int $maxBytes,
        private readonly int $timeout,
    ) {
    }

    public function fetch(string $url): StoredImage
    {
        $checked = $this->urls->check($url);
        $tmp = tempnam($this->storage->root(), 'img');
        if ($tmp === false) {
            throw new ImageFetchException('Не удалось подготовить файл изображения');
        }

        try {
            $meta = $this->download($checked, $tmp, 0);
            $extension = $this->extension($tmp, $meta['type']);
            $hash = hash_file('sha256', $tmp);
            if (!is_string($hash)) {
                throw new ImageFetchException('Не удалось сохранить изображение');
            }
            $relative = 'images/' . substr($hash, 0, 2) . '/' . $hash . '.' . $extension;
            $target = $this->storage->absolute($relative);
            $created = !is_file($target);
            if ($created && !rename($tmp, $target)) {
                throw new ImageFetchException('Не удалось сохранить изображение');
            }
            if (!$created) {
                unlink($tmp);
            }
            @chmod($target, 0644);

            return new StoredImage($url, $relative, $created);
        } catch (ImageFetchException $exception) {
            if (is_file($tmp)) {
                unlink($tmp);
            }
            throw $exception;
        }
    }

    public function discard(StoredImage $image): void
    {
        if ($image->created) {
            $this->storage->delete($image->relativePath);
        }
    }

    /** @return array{type: string} */
    private function download(CheckedUrl $checked, string $target, int $redirects): array
    {
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new ImageFetchException('Не удалось подготовить файл изображения');
        }
        $written = 0;
        $tooLarge = false;
        $curl = curl_init($checked->scheme . '://' . $checked->host . $checked->pathAndQuery);
        if ($curl === false) {
            fclose($handle);
            throw new ImageFetchException('Не удалось скачать изображение');
        }

        $options = [
            CURLOPT_RESOLVE => [$checked->host . ':' . $checked->port . ':' . $checked->ip],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'ProductsImporter/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/jpeg, image/png, image/webp, image/gif'],
            CURLOPT_FAILONERROR => false,
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
        } elseif (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        curl_setopt_array($curl, $options);
        $max = $this->maxBytes;
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, static function ($curl, string $data) use ($handle, &$written, &$tooLarge, $max): int {
            unset($curl);
            $length = strlen($data);
            if ($written + $length > $max) {
                $tooLarge = true;

                return 0;
            }
            $stored = fwrite($handle, $data);
            if (!is_int($stored)) {
                return 0;
            }
            $written += $stored;

            return $stored;
        });

        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $location = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        curl_close($curl);
        fclose($handle);

        if ($tooLarge || $written > $this->maxBytes) {
            throw new ImageFetchException('Изображение превышает допустимый размер');
        }
        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            if ($redirects >= 3 || !is_string($location) || $location === '') {
                throw new ImageFetchException('Слишком много перенаправлений изображения');
            }
            $next = $this->urls->resolveRedirect($checked, $location);
            file_put_contents($target, '');

            return $this->download($next, $target, $redirects + 1);
        }
        if ($status === 403 || $status === 404) {
            throw new ImageFetchException('Файл недоступен на сервере поставщика');
        }
        if ($ok !== true || $status < 200 || $status >= 300) {
            throw new ImageFetchException('Не удалось скачать изображение');
        }

        return ['type' => strtolower(trim(explode(';', $type)[0]))];
    }

    private function extension(string $path, string $mediaType): string
    {
        $handle = fopen($path, 'rb');
        $magic = $handle === false ? '' : (string) fread($handle, 16);
        if (is_resource($handle)) {
            fclose($handle);
        }
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $byMagic = $this->sniff($magic);
        $byType = $map[$mediaType] ?? null;
        if ($byMagic === null) {
            throw new ImageFetchException('Файл по ссылке не является изображением');
        }
        if ($byType !== null && $byType !== $byMagic && !($byType === 'jpg' && $byMagic === 'jpg')) {
            if ($mediaType !== '' && $mediaType !== 'application/octet-stream' && $byType !== $byMagic) {
                throw new ImageFetchException('Тип изображения не совпадает с содержимым');
            }
        }

        return $byMagic;
    }

    private function sniff(string $magic): ?string
    {
        if (str_starts_with($magic, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($magic, "\x89PNG\x0D\x0A\x1A\x0A")) {
            return 'png';
        }
        if (str_starts_with($magic, 'GIF87a') || str_starts_with($magic, 'GIF89a')) {
            return 'gif';
        }
        if (str_starts_with($magic, 'RIFF') && substr($magic, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }
}
