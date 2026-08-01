<?php

declare(strict_types=1);

namespace App\Service;

use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploader
{
    public function __construct(
        private readonly FilesystemOperator $activeStorage,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $normalizedExtension = $extension !== '' ? strtolower((string) $extension) : null;
        $key = sprintf(
            '%s%s',
            bin2hex(random_bytes(16)),
            $normalizedExtension !== null ? '.' . $normalizedExtension : ''
        );

        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read uploaded image stream.');
        }

        try {
            $this->activeStorage->writeStream($key, $stream);
        } finally {
            fclose($stream);
        }

        return $key;
    }
}
