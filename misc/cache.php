<?php

// Small file cache for public Pinterest search responses. Entries are keyed by
// query and bookmark and expire quickly, so repeated searches render without
// another upstream round trip. No visitor data is stored.

function cache_directory(): string
{
    $directory = sys_get_temp_dir() . '/binternet-cache';
    if (!is_dir($directory)) {
        @mkdir($directory, 0700, true);
    }
    return $directory;
}

function cache_get(string $key, int $ttl): ?string
{
    $file = cache_directory() . '/' . hash('sha256', $key);
    if (!is_file($file)) {
        return null;
    }
    if (filemtime($file) < time() - $ttl) {
        @unlink($file);
        return null;
    }
    $value = @file_get_contents($file);
    return $value === false ? null : $value;
}

function cache_set(string $key, string $value): void
{
    $file = cache_directory() . '/' . hash('sha256', $key);
    $temporary = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($temporary, $value, LOCK_EX) === false) {
        @unlink($temporary);
        return;
    }
    if (!@rename($temporary, $file)) {
        @unlink($temporary);
    }
}

// Prune expired entries occasionally so the cache directory cannot grow
// without bound across many distinct queries.
function cache_prune(int $ttl): void
{
    if (random_int(1, 50) !== 1) {
        return;
    }
    foreach (glob(cache_directory() . '/*') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < time() - max($ttl, 3600)) {
            @unlink($file);
        }
    }
}
