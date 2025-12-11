<?php
function normalizeAddress($text)
{
    $text = strtolower($text);

    $replace = [
        '/[^a-z0-9\s]/' => ' ',
        '/\bjln\b/' => 'jalan',
        '/\bjl\b/' => 'jalan',
        '/\bkel\b/' => 'kelurahan',
        '/\bkec\b/' => 'kecamatan',
        '/\bkab\b/' => 'kabupaten',
        '/\bprov\b/' => 'provinsi',
        '/\bno\b/' => '',
        '/\bkm\b/' => '',
        '/\brw\b/' => '',
        '/\brt\b/' => '',
        '/\bgedung\b/' => '',
        '/\bgd\b/' => '',
        '/\bblok\b/' => '',
        '/\blantai\b/' => '',
        '/\blt\b/' => '',
        '/\bwing\b/' => '',
        '/\bperumahan\b/' => '',
        '/\bkomplek\b/' => '',
        '/\boffice\b/' => '',
        '/\bkantor\b/' => '',
        '/\bindonesia\b/' => '',
        '/\s+/' => ' ',
    ];

    foreach ($replace as $pattern => $value) {
        $text = preg_replace($pattern, $value, $text);
    }

    return trim($text);
}
