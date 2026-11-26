<?php

namespace App\Support;

class Idgames
{
    public const MIRROR_URL = 'https://youfailit.net/pub/idgames/levels/';

    private const LETTERS = ['0-9', 'a-c', 'd-f', 'g-i', 'j-l', 'm-o', 'p-r', 's-u', 'v-z'];

    /**
     * Every idgames levels directory we mirror, as "main/sub" relative paths
     * (e.g. "doom2/megawads"). Used for both the remote URLs and local layout.
     *
     * @return list<string>
     */
    public static function directories(): array
    {
        $withMegawads = [...self::LETTERS, 'megawads'];

        $mainDirs = [
            'doom' => $withMegawads,
            'doom/Ports' => $withMegawads,
            'doom/deathmatch' => $withMegawads,
            'doom/deathmatch/Ports' => self::LETTERS,
            'doom2' => $withMegawads,
            'doom2/Ports' => $withMegawads,
            'doom2/deathmatch' => $withMegawads,
            'doom2/deathmatch/Ports' => self::LETTERS,
        ];

        $directories = [];

        foreach ($mainDirs as $main => $subs) {
            foreach ($subs as $sub) {
                $directories[] = "{$main}/{$sub}";
            }
        }

        return $directories;
    }
}
