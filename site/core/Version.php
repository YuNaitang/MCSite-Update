<?php

class Version
{
    const CURRENT = '1.0.9';
    const UPDATE_SERVER = 'https://update.candycake.cloud';

    /**
     * 比较版本号，remote > local 时返回 true
     */
    static function hasNewer(string $remote): bool
    {
        return version_compare($remote, self::CURRENT, '>');
    }

    static function current(): string
    {
        return self::CURRENT;
    }
}
