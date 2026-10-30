<?php
/**
 * Helpers for parsing Google Maps location/share URLs.
 * Supports direct coordinate URLs and tries to follow Google Maps short links.
 */
function gmaps_valid_coords($lat, $lng) {
    return is_numeric($lat) && is_numeric($lng) && (float)$lat >= -90 && (float)$lat <= 90 && (float)$lng >= -180 && (float)$lng <= 180;
}

function gmaps_allowed_host($host) {
    $host = strtolower(rtrim((string)$host, '.'));
    if (in_array($host, ['maps.app.goo.gl', 'goo.gl', 'google.com', 'google.co.id'], true)) return true;
    foreach (['.google.com', '.google.co.id'] as $suffix) {
        if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) return true;
    }
    return false;
}

function gmaps_extract_coords($input) {
    $text = trim(html_entity_decode((string)$input, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') return null;
    $decoded = rawurldecode($text);
    $lat = '(-?\\d{1,2}(?:\\.\\d+)?)';
    $lng = '(-?\\d{1,3}(?:\\.\\d+)?)';
    // Exact pin coordinates in Google Maps URLs have priority over viewport center.
    $patterns = [
        ['regex' => '/!3d' . $lat . '!4d' . $lng . '/i', 'order' => 'latlng'],
        ['regex' => '/!2d' . $lng . '!3d' . $lat . '/i', 'order' => 'lnglat'],
        ['regex' => '/[?&](?:q|query|ll|center|destination)=\\s*' . $lat . '(?:,|%2c|%2C)\\s*' . $lng . '/i', 'order' => 'latlng'],
        ['regex' => '/@' . $lat . '\\s*,\\s*' . $lng . '/', 'order' => 'latlng'],
        ['regex' => '/(?:^|\\/place\\/)[^\\/]*?(' . $lat . '),(' . $lng . ')/i', 'order' => 'latlng'],
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern['regex'], $decoded, $m)) {
            if ($pattern['order'] === 'lnglat') { $latitude = (float)$m[2]; $longitude = (float)$m[1]; }
            elseif (strpos($pattern['regex'], 'place') !== false) { $latitude = (float)$m[2]; $longitude = (float)$m[3]; }
            else { $latitude = (float)$m[1]; $longitude = (float)$m[2]; }
            if (gmaps_valid_coords($latitude, $longitude)) return ['latitude' => $latitude, 'longitude' => $longitude];
        }
    }
    $parts = parse_url($text);
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (['q', 'query', 'll', 'center', 'destination'] as $key) {
            if (!isset($query[$key])) continue;
            if (preg_match('/^\\s*' . $lat . '\\s*[,;]\\s*' . $lng . '\\s*$/', (string)$query[$key], $m)) {
                $latitude = (float)$m[1]; $longitude = (float)$m[2];
                if (gmaps_valid_coords($latitude, $longitude)) return ['latitude' => $latitude, 'longitude' => $longitude];
            }
        }
    }
    // Raw coordinate pair pasted by a user is accepted too.
    if (preg_match('/^\\s*' . $lat . '\\s*[,;]\\s*' . $lng . '\\s*$/', $decoded, $m)) {
        $latitude = (float)$m[1]; $longitude = (float)$m[2];
        if (gmaps_valid_coords($latitude, $longitude)) return ['latitude' => $latitude, 'longitude' => $longitude];
    }
    return null;
}

function gmaps_resolve_link($input) {
    $url = trim((string)$input);
    if ($url === '') return ['success' => false, 'message' => 'Tempel tautan Bagikan dari Google Maps terlebih dahulu.'];
    $coords = gmaps_extract_coords($url);
    if ($coords) return ['success' => true, 'coordinates' => $coords, 'resolved_url' => $url];

    if (!preg_match('~^https?://~i', $url)) {
        return ['success' => false, 'message' => 'Link tidak valid. Tempel URL Google Maps lengkap yang diawali https://.'];
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || !gmaps_allowed_host($parts['host'])) {
        return ['success' => false, 'message' => 'Link harus berasal dari Google Maps atau maps.app.goo.gl.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'PHP cURL belum aktif di XAMPP, sehingga link pendek belum bisa dibaca otomatis. Aktifkan ekstensi curl di php.ini lalu restart Apache.'];
    }

    $effective = $url;
    $body = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 7,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | (defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP : 0));
    if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS | (defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP : 0));
    $headResult = curl_exec($ch);
    $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $headError = curl_error($ch);
    $coords = gmaps_extract_coords($effective);
    curl_close($ch);
    if ($coords) return ['success' => true, 'coordinates' => $coords, 'resolved_url' => $effective];

    // Some Google endpoints do not put pin coordinates in the final URL. Inspect a limited response body.
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 7,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => function($curl, $chunk) use (&$body) {
            $room = 524288 - strlen($body);
            if ($room <= 0) return 0;
            $body .= substr($chunk, 0, $room);
            return strlen($chunk);
        },
    ]);
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | (defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP : 0));
    if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS | (defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP : 0));
    curl_exec($ch);
    $effective = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $getError = curl_error($ch);
    curl_close($ch);

    $resolvedParts = parse_url($effective);
    if (!$resolvedParts || empty($resolvedParts['host']) || !gmaps_allowed_host($resolvedParts['host'])) {
        return ['success' => false, 'message' => 'Pengalihan link tidak mengarah ke halaman Google Maps yang dikenali. Pastikan link adalah hasil Bagikan lokasi yang tepat.'];
    }
    $coords = gmaps_extract_coords($effective) ?: gmaps_extract_coords($body);
    if ($coords) return ['success' => true, 'coordinates' => $coords, 'resolved_url' => $effective];

    $detail = $getError ?: $headError;
    $message = 'Link berhasil dibuka, tetapi koordinat titik tidak ditemukan. Di Google Maps, pilih titik ODP/rumah yang tepat lalu gunakan Bagikan. Hindari link hasil pencarian umum.';
    if ($detail && stripos($detail, 'SSL') !== false) $message = 'XAMPP gagal memverifikasi koneksi HTTPS ke Google Maps. Periksa koneksi internet dan sertifikat CA pada PHP/XAMPP.';
    return ['success' => false, 'message' => $message];
}
?>
