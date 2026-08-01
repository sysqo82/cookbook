<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StorageUrlResolver
{
    public function __construct(
        #[Autowire('%storage.public_base_path%')]
        private readonly string $basePath,
    ) {
    }

    public function getPublicUrl(string $key): string
    {
        if (preg_match('#^https?://#i', $key) === 1 || str_starts_with($key, '/')) {
            return $key;
        }

        return rtrim($this->basePath, '/') . '/' . ltrim($key, '/');
    }

    /** Base URL for EasyAdmin ImageField::setBasePath() */
    public function getBaseUrl(): string
    {
        return rtrim($this->basePath, '/') . '/';
    }
}
