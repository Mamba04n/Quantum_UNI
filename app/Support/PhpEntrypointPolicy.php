<?php

namespace App\Support;

class PhpEntrypointPolicy
{
    private const ALLOWED_PHP_DIRECTORIES = [
        'app/',
        'bootstrap/',
        'config/',
        'database/',
        'lang/',
        'resources/',
        'routes/',
        'tests/',
    ];

    private const ALLOWED_PUBLIC_ENTRYPOINTS = [
        'public/index.php',
    ];

    public function isExecutablePhp(string $path): bool
    {
        return preg_match('/\.(?:php|phtml|phar|php[3-8])$/i', $this->normalize($path)) === 1;
    }

    public function isAllowed(string $path): bool
    {
        $path = $this->normalize($path);

        if (! $this->isExecutablePhp($path)) {
            return true;
        }

        if (in_array($path, self::ALLOWED_PUBLIC_ENTRYPOINTS, true)) {
            return true;
        }

        if (! str_ends_with(strtolower($path), '.php')) {
            return false;
        }

        foreach (self::ALLOWED_PHP_DIRECTORIES as $directory) {
            if (str_starts_with($path, $directory)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, string>  $paths
     * @return array<int, string>
     */
    public function rejected(iterable $paths): array
    {
        $rejected = [];

        foreach ($paths as $path) {
            if (! $this->isAllowed($path)) {
                $rejected[] = $this->normalize($path);
            }
        }

        sort($rejected);

        return array_values(array_unique($rejected));
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), './');
    }
}
