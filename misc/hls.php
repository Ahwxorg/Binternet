<?php

// HLS -> MP4 download. ffmpeg remuxes the best variant and default audio track
// into a fragmented MP4 stream. Playlists and segments are fetched server-side,
// so the visitor's IP never reaches Pinterest.

function ffmpeg_path(): string
{
    if (function_exists('exec')) {
        $lines = [];
        $code = 1;
        @exec('command -v ffmpeg 2>/dev/null', $lines, $code);
        if ($code === 0 && !empty($lines[0])) {
            $path = trim($lines[0]);
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }
    }

    foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/bin/ffmpeg'] as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }
    return '';
}

function fetch_hls_playlist(string $url): ?string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $http_version = defined("CURL_HTTP_VERSION_2TLS") ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => "",
            CURLOPT_HTTP_VERSION => $http_version,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_NODELAY => 1,
            CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64; rv:156.0) Gecko/20100101 Firefox/156.0",
            CURLOPT_HTTPHEADER => [
                "Accept: */*",
                "Referer: https://www.pinterest.com/",
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        unset($ch);

        if ($body === false) {
            return null;
        }
        if ($status >= 300 && $status < 400 && $location !== '') {
            $url = resolve_media_url($url, $location);
            if (!valid_media_url($url)) {
                return null;
            }
            continue;
        }
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $body = (string) $body;
        if (!str_starts_with(ltrim($body), '#EXTM3U')) {
            return null;
        }
        return $body;
    }
    return null;
}

function parse_hls_attrs(string $line): array
{
    $attrs = [];
    if (preg_match_all('/([A-Z0-9-]+)=("[^"]*"|[^,]*)/', $line, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attrs[$match[1]] = trim($match[2], '"');
        }
    }
    return $attrs;
}

function parse_hls_size(string $resolution): array
{
    $parts = explode('x', strtolower($resolution), 2);
    if (count($parts) !== 2) {
        return [0, 0];
    }
    return [(int) $parts[0], (int) $parts[1]];
}

// parse_hls_playlist picks the highest-resolution variant and its default
// audio track. A media playlist with no variants returns its own URL.
function parse_hls_playlist(string $body, string $base): array
{
    $best = null;
    $default_audio = '';
    $any_audio = '';

    $lines = preg_split('/\r?\n/', $body);
    if ($lines === false) {
        return [$base, ''];
    }

    $count = count($lines);
    for ($index = 0; $index < $count; $index++) {
        $line = trim($lines[$index]);

        if (str_starts_with($line, '#EXT-X-STREAM-INF:')) {
            $attrs = parse_hls_attrs($line);
            $uri = '';
            for ($next = $index + 1; $next < $count; $next++) {
                $candidate = trim($lines[$next]);
                if ($candidate === '' || str_starts_with($candidate, '#')) {
                    continue;
                }
                $uri = $candidate;
                $index = $next;
                break;
            }
            if ($uri === '') {
                continue;
            }
            $resolved = resolve_media_url($base, $uri);
            if (!valid_media_url($resolved)) {
                continue;
            }
            [$width, $height] = parse_hls_size($attrs['RESOLUTION'] ?? '');
            $bandwidth = (int) ($attrs['BANDWIDTH'] ?? 0);
            $variant = [
                'url' => $resolved,
                'width' => $width,
                'height' => $height,
                'bandwidth' => $bandwidth,
            ];
            if ($best === null
                || $variant['width'] * $variant['height'] > $best['width'] * $best['height']
                || ($variant['width'] * $variant['height'] === $best['width'] * $best['height']
                    && $variant['bandwidth'] > $best['bandwidth'])) {
                $best = $variant;
            }
        } elseif (str_starts_with($line, '#EXT-X-MEDIA:')) {
            $attrs = parse_hls_attrs($line);
            if (strcasecmp($attrs['TYPE'] ?? '', 'AUDIO') !== 0 || empty($attrs['URI'])) {
                continue;
            }
            $audio = resolve_media_url($base, $attrs['URI']);
            if (!valid_media_url($audio)) {
                continue;
            }
            if ($any_audio === '') {
                $any_audio = $audio;
            }
            if (strcasecmp($attrs['DEFAULT'] ?? '', 'YES') === 0 && $default_audio === '') {
                $default_audio = $audio;
            }
        }
    }

    $audio = $default_audio !== '' ? $default_audio : $any_audio;
    if ($best !== null) {
        return [$best['url'], $audio];
    }
    return [$base, $audio];
}

// stream_hls_download writes the remuxed MP4 to the response. It returns false,
// before writing anything, when ffmpeg is missing or the playlist is unusable.
function stream_hls_download(string $target, string $media_path, string $download_name = ""): bool
{
    if (!function_exists('proc_open')) {
        error_log('binternet: proc_open is disabled; HLS download unavailable');
        return false;
    }

    $ffmpeg = ffmpeg_path();
    if ($ffmpeg === '') {
        error_log('binternet: ffmpeg not found; HLS download unavailable');
        return false;
    }

    $playlist = fetch_hls_playlist($target);
    if ($playlist === null) {
        error_log('binternet: could not read HLS playlist ' . $target);
        return false;
    }
    [$video_url, $audio_url] = parse_hls_playlist($playlist, $target);
    if ($video_url === '') {
        return false;
    }

    $args = [
        $ffmpeg,
        '-hide_banner',
        '-loglevel', 'error',
        '-nostdin',
        '-rw_timeout', '30000000',
        '-user_agent', 'Mozilla/5.0 (X11; Linux x86_64; rv:156.0) Gecko/20100101 Firefox/156.0',
        '-headers', "Referer: https://www.pinterest.com/\r\n",
        '-i', $video_url,
    ];
    if ($audio_url !== '') {
        $args[] = '-i';
        $args[] = $audio_url;
    }
    $args[] = '-map';
    $args[] = '0:v:0';
    if ($audio_url !== '') {
        $args[] = '-map';
        $args[] = '1:a:0?';
    } else {
        $args[] = '-map';
        $args[] = '0:a:0?';
    }
    array_push(
        $args,
        '-c', 'copy',
        '-bsf:a', 'aac_adtstoasc',
        '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
        '-f', 'mp4',
        'pipe:1'
    );

    $error_file = tempnam(sys_get_temp_dir(), 'binternet-ffmpeg-');
    if ($error_file === false) {
        return false;
    }
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $error_file, 'w'],
    ];
    $process = proc_open($args, $descriptors, $pipes);
    if (!is_resource($process)) {
        @unlink($error_file);
        return false;
    }

    fclose($pipes[0]);
    $stdout = $pipes[1];
    $first = fread($stdout, 1);
    if ($first === false || $first === '') {
        $error = (string) @file_get_contents($error_file);
        fclose($stdout);
        proc_close($process);
        @unlink($error_file);
        error_log('binternet: ffmpeg produced no video for ' . $target . ': ' . trim($error));
        return false;
    }

    $filename = $download_name !== "" ? $download_name : mp4_filename(media_filename($media_path));
    if (!str_ends_with(strtolower($filename), ".mp4")) {
        $filename .= ".mp4";
    }
    header('Content-Type: video/mp4');
    header('Content-Disposition: ' . content_disposition($filename));
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no');
    http_response_code(200);
    echo $first;
    flush();

    while (!feof($stdout)) {
        $chunk = fread($stdout, 65536);
        if ($chunk === false) {
            proc_terminate($process);
            break;
        }
        if ($chunk !== '') {
            echo $chunk;
            flush();
        }
        if (connection_aborted()) {
            proc_terminate($process);
            break;
        }
    }
    fclose($stdout);

    $exit_code = proc_close($process);
    $error = (string) @file_get_contents($error_file);
    @unlink($error_file);
    if ($exit_code !== 0 && trim($error) !== '') {
        error_log('binternet: ffmpeg exited ' . $exit_code . ': ' . trim($error));
    }
    return true;
}
