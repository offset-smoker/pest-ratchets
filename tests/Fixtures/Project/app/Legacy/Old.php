<?php

namespace App\Legacy;

final class Old
{
    public function region(): ?string
    {
        return env('LEGACY_REGION');
    }
}
