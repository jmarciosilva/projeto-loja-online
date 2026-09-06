<?php

namespace App\Services;

use App\Models\Media;
use Closure;

/** Registro compartilhado que desacopla a mídia de seus consumidores futuros. */
class MediaUsageRegistry
{
    /** @var array<string, Closure(Media): bool> */
    private array $checkers = [];

    public function register(string $label, Closure $checker): void
    {
        $this->checkers[$label] = $checker;
    }

    /** @return list<string> */
    public function usages(Media $media): array
    {
        return array_keys(array_filter(
            $this->checkers,
            fn (Closure $checker): bool => $checker($media),
        ));
    }

    public function isInUse(Media $media): bool
    {
        return $this->usages($media) !== [];
    }
}
