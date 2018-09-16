<?php
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Simple\ApcuCache;

class FicoraEppCache
{
    private static $cache;

    /**
     * @return CacheInterface
     */
    public static function get(): CacheInterface
    {
        // Feel free to change the cache definition here
        if(!static::$cache)
            static::$cache = new ApcuCache('ficoraepp');

        return static::$cache;
    }
}