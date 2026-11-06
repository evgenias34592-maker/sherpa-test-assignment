<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportJob;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use App\Storage\Storage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportJobRepository $jobs,
        private readonly FileValidator $validator,
        private readonly Storage $storage,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function enqueue(UploadedFileInterface $file, int $userId): ImportJob
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \App\Http\ApiException($this->uploadError($file->getError()), 422);
        }
        $clientName = $this->clientName($file->getClientFilename());
        $temporary = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($temporary === false) {
            throw new \App\Http\ApiException('Не удалось сохранить файл', 500);
        }
        $file->moveTo($temporary);
        try {
            $size = $file->getSize();
            $bytes = is_int($size) && $size > 0 ? $size : (int) filesize($temporary);
            $this->validator->assertXlsx($temporary, $clientName, $bytes, $file->getClientMediaType());
            $id = $this->uuid();
            $relative = 'imports/' . $id . '.xlsx';
            $target = $this->storage->absolute($relative);
            if (!copy($temporary, $target)) {
                throw new \App\Http\ApiException('Не удалось сохранить файл', 500);
            }
            @chmod($target, 0640);
            $job = new ImportJob($id, $userId, $clientName, $relative);
            $this->jobs->add($job);
            $this->em->flush();
            try {
                $this->bus->dispatch(new ImportProductsMessage($id));
            } catch (\Throwable $exception) {
                $job->fail('Очередь импорта недоступна');
                $this->em->flush();
                throw new \App\Http\ApiException('Очередь импорта недоступна', 503, $exception);
            }

            return $job;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function clientName(?string $name): string
    {
        $name = basename(str_replace(["\0", '\\'], ['', '/'], (string) $name));
        if ($name === '' || $name === '.' || $name === '..') {
            return 'import.xlsx';
        }

        return mb_substr($name, 0, 255);
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл превышает допустимый размер',
            UPLOAD_ERR_PARTIAL => 'Файл загружен не полностью',
            UPLOAD_ERR_NO_FILE => 'Файл не выбран',
            default => 'Не удалось загрузить файл',
        };
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
