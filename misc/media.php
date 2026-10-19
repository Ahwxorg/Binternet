<?php

// Helpers for Pinterest media. The search page uses these to pick a playable
// video variant, choose a poster and map CDN URLs onto the local /media/ route.

function valid_media_host(string $host): bool
{
    $host = strtolower($host);
    return $host === 'pinimg.com'
        || str_ends_with($host, '.pinimg.com')
        || $host === 'pinterest.com'
        || str_ends_with($host, '.pinterest.com');
}

function valid_media_path(string $path): bool
{
    $decoded = rawurldecode($path);
    if ($decoded === '' || $decoded === '..'
        || preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded)) {
        return false;
    }
    return !str_contains($decoded, '/../')
        && !str_starts_with($decoded, '../')
        && !str_ends_with($decoded, '/..');
}

// media_proxy_path maps a Pinterest CDN URL to /media/{host}/{path}. Relative
// HLS references then stay inside the proxy instead of reaching Pinterest.
function media_proxy_path(string $raw_url): string
{
    $parts = parse_url($raw_url);
    if ($parts === false) {
        return '';
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }
    $host = strtolower($parts['host'] ?? '');
    if (!valid_media_host($host)) {
        return '';
    }
    $path = $parts['path'] ?? '';
    if ($path === '' || !valid_media_path($path)) {
        return '';
    }

    $out = '/media/' . $host . $path;
    if (($parts['query'] ?? '') !== '') {
        $out .= '?' . $parts['query'];
    }
    return $out;
}

function is_gif_image(string $raw_url): bool
{
    $lower = strtolower($raw_url);
    $lower = substr($lower, 0, strcspn($lower, '?#'));
    return str_ends_with($lower, '.gif');
}

function is_mp4(string $raw_url): bool
{
    return str_contains(strtolower($raw_url), '.mp4');
}

function is_hls(string $raw_url): bool
{
    return str_contains(strtolower($raw_url), '.m3u8');
}

function video_from(object $video, bool $hls): array
{
    return [
        'url' => (string) ($video->url ?? ''),
        'hls' => $hls,
        'width' => (int) ($video->width ?? 0),
        'height' => (int) ($video->height ?? 0),
        'duration' => (int) ($video->duration ?? 0),
        'poster' => (string) ($video->thumbnail ?? ''),
    ];
}

// pick_video prefers MP4 variants when Pinterest returns them. Anonymous
// search currently returns HLS for almost every video pin.
function pick_video($videos): ?array
{
    $list = $videos->video_list ?? null;
    if (!is_object($list) && !is_array($list)) {
        return null;
    }
    $list = (array) $list;
    if ($list === []) {
        return null;
    }

    foreach (['V_1080P', 'V_720P', 'V_540P', 'V_480P', 'V_360P', 'V_240P'] as $key) {
        $video = $list[$key] ?? null;
        if (is_object($video) && is_mp4((string) ($video->url ?? ''))) {
            return video_from($video, false);
        }
    }

    $best = null;
    foreach ($list as $video) {
        if (!is_object($video) || !is_mp4((string) ($video->url ?? ''))) {
            continue;
        }
        $height = (int) ($video->height ?? 0);
        $width = (int) ($video->width ?? 0);
        if ($best === null
            || $height > (int) ($best->height ?? 0)
            || ($height === (int) ($best->height ?? 0) && $width > (int) ($best->width ?? 0))) {
            $best = $video;
        }
    }
    if ($best !== null) {
        return video_from($best, false);
    }

    foreach (['V_HLSV4', 'V_HLSV3_WEB', 'V_HLSV3_MOBILE'] as $key) {
        $video = $list[$key] ?? null;
        if (is_object($video) && is_hls((string) ($video->url ?? ''))) {
            return video_from($video, true);
        }
    }
    foreach ($list as $video) {
        if (is_object($video) && is_hls((string) ($video->url ?? ''))) {
            return video_from($video, true);
        }
    }
    return null;
}

// display_image picks a reasonably sized variant. GIFs keep the original
// because Pinterest's smaller GIF variants are static JPEGs.
function display_image($images, string $original): string
{
    if (is_gif_image($original) || !is_object($images)) {
        return $original;
    }
    foreach (['736x', '474x', '236x'] as $key) {
        $image = $images->$key ?? null;
        $url = is_object($image) ? (string) ($image->url ?? '') : '';
        if ($url !== '' && !is_gif_image($url)) {
            return $url;
        }
    }
    return $original;
}

// allowed_media_content_type keeps the proxy limited to images, video and HLS
// payloads. Pinterest serves HLS segments as video/mp2t or octet-stream.
function media_filename(string $raw_url): string
{
    $path = parse_url($raw_url, PHP_URL_PATH);
    $name = is_string($path) ? basename($path) : '';
    if ($name === '' || $name === '.' || $name === '..' || $name === '/') {
        return 'pinterest-media';
    }
    return $name;
}

function mp4_filename(string $filename): string
{
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension !== '') {
        $filename = substr($filename, 0, -strlen($extension) - 1);
    }
    if ($filename === '') {
        $filename = 'pinterest-video';
    }
    return $filename . '.mp4';
}

// pin_filename turns a pin title into a readable download filename while
// keeping the media type's real extension.
function pin_filename(string $title, string $raw_url, bool $hls = false): string
{
    $slug = preg_replace('/[^\pL\pN]+/u', '-', $title);
    if ($slug === null) {
        $slug = '';
    }
    $slug = trim($slug, '-');
    if (preg_match('/^.{0,80}/u', $slug, $match)) {
        $slug = trim($match[0], '-');
    }
    if ($slug === '') {
        $slug = pathinfo(media_filename($raw_url), PATHINFO_FILENAME);
    }
    if ($slug === '') {
        $slug = 'pinterest-media';
    }

    $path = parse_url($raw_url, PHP_URL_PATH);
    $extension = $hls ? 'mp4' : (is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '');
    if ($extension === '') {
        $extension = $hls ? 'mp4' : 'jpg';
    }

    return $slug . '.' . $extension;
}

function safe_download_name(string $name): string
{
    $name = str_replace(["\0", "\r", "\n", "/", "\\"], '', $name);
    $name = ltrim(trim($name), '.');
    if ($name === '') {
        return '';
    }
    if (preg_match('/^.{0,150}/u', $name, $match)) {
        return $match[0];
    }
    return substr($name, 0, 150);
}

// media_upstream_query drops Binternet's own download flag and passes the rest
// of the query through byte-for-byte, so signed Pinterest URLs keep working.
function media_upstream_query(string $raw_query): string
{
    if ($raw_query === '') {
        return '';
    }
    $kept = [];
    foreach (explode('&', $raw_query) as $pair) {
        $key = explode('=', $pair, 2)[0];
        if ($key === 'download' || $key === 'name') {
            continue;
        }
        $kept[] = $pair;
    }
    return implode('&', $kept);
}

function content_disposition(string $filename): string
{
    $filename = trim(str_replace(["\r", "\n", '"', '\\'], '', $filename));
    if ($filename === '' || $filename === '.' || $filename === '..') {
        $filename = 'pinterest-media';
    }

    $ascii = preg_replace('/[^\x20-\x7e]/', '', $filename);
    if ($ascii === null || $ascii === '') {
        $ascii = 'pinterest-media';
    }

    return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
}

function allowed_media_content_type(string $content_type): bool
{
    $content_type = strtolower(trim(explode(';', $content_type, 2)[0]));
    if (in_array($content_type, [
        '',
        'application/octet-stream',
        'binary/octet-stream',
        'application/x-mpegurl',
        'application/vnd.apple.mpegurl',
        'application/mp4',
    ], true)) {
        return true;
    }
    return str_starts_with($content_type, 'image/')
        || str_starts_with($content_type, 'video/')
        || str_starts_with($content_type, 'audio/');
}

function valid_media_url(string $url): bool
{
    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }
    if (!valid_media_host(strtolower($parts['host'] ?? ''))) {
        return false;
    }
    $path = $parts['path'] ?? '';
    return $path !== '' && valid_media_path($path);
}

// resolve_media_url resolves a redirect Location against the current URL. The
// result is checked again before the proxy follows it.
function resolve_media_url(string $base, string $reference): string
{
    $reference = trim($reference);
    if ($reference === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $reference)) {
        return $reference;
    }

    $parts = parse_url($base);
    if ($parts === false) {
        return '';
    }
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    if (str_starts_with($reference, '//')) {
        return $scheme . ':' . $reference;
    }
    if (str_starts_with($reference, '/')) {
        return $scheme . '://' . $host . $port . $reference;
    }
    if (str_starts_with($reference, '?')) {
        return $scheme . '://' . $host . $port . ($parts['path'] ?? '/') . $reference;
    }

    $base_path = $parts['path'] ?? '/';
    $directory = substr($base_path, 0, strrpos($base_path, '/') + 1);
    if ($directory === '') {
        $directory = '/';
    }

    $combined = $directory . $reference;
    $cut = strcspn($combined, '?#');
    $path = substr($combined, 0, $cut);
    $suffix = substr($combined, $cut);

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '..') {
            if (count($segments) > 1) {
                array_pop($segments);
            }
            continue;
        }
        if ($segment === '.' || $segment === '') {
            continue;
        }
        $segments[] = $segment;
    }

    $normalized = '/' . implode('/', $segments);
    return $scheme . '://' . $host . $port . $normalized . $suffix;
}
