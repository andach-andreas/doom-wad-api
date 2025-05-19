<?php

namespace App\Observers;

use App\Models\Wad;

class WadObserver
{
    public function saving(Wad $wad): void
    {
        foreach ($wad->getAttributes() as $key => $value) {
            if (is_string($value)) {
                $wad->{$key} = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
    }
}
