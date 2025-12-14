<?php

//=============================
// LOCATION DETECTION - FULL + OPTIMIZED
//=============================
//
// Modifikasi utama:
// 1) Balik urutan token (pencarian dimulai dari token terakhir).
// 2) Perbaiki urutan token yang terbalik untuk frasa wilayah yang diambil
//    secara dinamis dari data region (tanpa hard-code).
// 3) Optimasi: precompute DF, normalisasi kandidat, dan bigram list untuk pemeriksaan cepat.
// 4) Tetap mempertahankan logika scoring asli (TF-IDF, n-gram, overlap, penalti).
//
// Cara pakai: panggil detectLocationOptimized($rawAddress, $provincies, $regencies, $districts)
// dimana $provincies, $regencies, $districts adalah array kandidat dengan key 'name' dan 'code' dsb.
//=============================


//=============================
// UTILITY
//=============================

function normalizeName($s)
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = preg_replace('/\b(kabupaten|kab|kota|provinsi|prov|kecamatan|kec|kelurahan|kel|desa)\b/u', '', $s);
    return trim($s);
}

function normalizeFlexible($s)
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]/u', '', $s);
    $s = preg_replace('/\b(kabupaten|kab|kota|provinsi|prov|kecamatan|kec|kelurahan|kel|desa)\b/u', '', $s);
    return trim($s);
}

function generateNgrams($tokens, $maxN = 3)
{
    $nPhrases = [];
    $n = count($tokens);
    for ($len = 1; $len <= $maxN; $len++) {
        for ($i = 0; $i + $len <= $n; $i++) {
            $slice = array_slice($tokens, $i, $len);
            $nPhrases[] = implode(' ', $slice);
            $nPhrases[] = implode('', $slice);
        }
    }
    return $nPhrases;
}

function wordOverlapScore($tokens, $nameTokens)
{
    $setA = array_values(array_filter(array_map('trim', $tokens)));
    $setB = array_values(array_filter(array_map('trim', $nameTokens)));
    if (count($setB) === 0) {
        return 0.0;
    }
    $common = 0;
    foreach (array_unique($setB) as $w) {
        if (in_array($w, $setA)) {
            $common++;
        }
    }
    return ($common / count(array_unique($setB))) * 100.0;
}

function phraseSimilarityPercent($a, $b)
{
    $a = normalizeName($a);
    $b = normalizeName($b);
    $maxLen = max(strlen($a), strlen($b));
    if ($maxLen === 0) {
        return 0;
    }
    return max(0, (1 - levenshtein($a, $b) / $maxLen) * 100);
}

function phraseSimilarityPercentFlexible($a, $b)
{
    $pct1 = phraseSimilarityPercent($a, $b);
    $a2 = normalizeFlexible($a);
    $b2 = normalizeFlexible($b);
    $maxLen = max(strlen($a2), strlen($b2));
    if ($maxLen === 0) {
        return $pct1;
    }
    $pct2 = max(0, (1 - levenshtein($a2, $b2) / $maxLen) * 100);
    return max($pct1, $pct2);
}

function applyGenericPenalty($tokens, $nameTokens, $baseScore)
{
    $overlapCount = 0;
    foreach (array_unique($nameTokens) as $w) {
        if (in_array($w, $tokens)) {
            $overlapCount++;
        }
    }
    if (count(array_unique($nameTokens)) > 1 && $overlapCount <= 1) {
        return $baseScore * 0.5;
    }
    return $baseScore;
}

//=============================
// TF-IDF (OPTIMIZED)
//=============================

// hitung DF setiap kata dari seluruh kandidat
function computeDocumentFrequencies($candidates)
{
    $df = [];
    foreach ($candidates as $c) {
        $tokens = preg_split('/\s+/', normalizeName($c['name']), -1, PREG_SPLIT_NO_EMPTY);
        $unique = array_unique($tokens);
        foreach ($unique as $t) {
            if ($t === '') continue;
            if (!isset($df[$t])) {
                $df[$t] = 0;
            }
            $df[$t]++;
        }
    }
    return $df;
}

// computeTfidfScore sekarang menerima df dan N untuk menghindari hit berulang
function computeTfidfScore($tokens, $candTokens, $df, $N)
{
    $score = 0.0;
    foreach ($candTokens as $t) {
        if ($t === '') continue;
        $tf = in_array($t, $tokens) ? 1 : 0;
        if ($tf === 0) {
            continue;
        }
        $idf = log(($N + 1) / (($df[$t] ?? 0) + 1));
        $score += $tf * $idf;
    }
    return $score;
}

//=============================
// HELPERS: build region token lists (dynamic, no hard-code)
//=============================

function buildRegionNameList($provincies, $regencies, $districts)
{
    $list = [];

    foreach ($provincies as $p) {
        if (!empty($p['name'])) {
            $list[] = strtolower($p['name']);
        }
    }
    foreach ($regencies as $r) {
        if (!empty($r['name'])) {
            $list[] = strtolower($r['name']);
        }
    }
    foreach ($districts as $d) {
        if (!empty($d['name'])) {
            $list[] = strtolower($d['name']);
        }
    }

    return array_values(array_unique($list));
}

// ambil daftar tokenized nama wilayah (hanya yang memiliki >=2 token)
function buildRegionTokenizedList(array $regionNames)
{
    $out = [];
    foreach ($regionNames as $name) {
        $parts = preg_split('/\s+/', normalizeName($name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) > 1) {
            $out[] = $parts;
        }
    }
    return $out;
}

//=============================
// TOKEN REVERSAL + FIX (tanpa hard-code)
//  - balik seluruh token (pencarian dari belakang ke depan)
//  - jika ditemukan segmen yang merupakan kebalikan dari nama wilayah di DB,
//    maka kembalikan urutannya (swap) -- tetap tidak menggabungkan token
//=============================

function reverseTokensThenFixFromRegions(array $tokens, array $regionTokenList)
{
    // ubah ke lowercase (asumsi input sudah normalized sebelumnya)
    $tokens = array_map('strtolower', $tokens);

    // balik urutan token
    $tokens = array_reverse($tokens);

    $count = count($tokens);
    if ($count <= 1 || empty($regionTokenList)) {
        return $tokens;
    }

    // untuk performa, buat index berdasarkan panjang token region yang ada
    $regionsByLen = [];
    foreach ($regionTokenList as $rtokens) {
        $len = count($rtokens);
        if (!isset($regionsByLen[$len])) {
            $regionsByLen[$len] = [];
        }
        // simpan normalized versi (sudah normalizeName saat build)
        $regionsByLen[$len][] = $rtokens;
    }

    // iterate pos di tokens, coba cocokkan untuk setiap panjang region yang mungkin
    // kita batasi panjang region hingga 4 token untuk performa (bisa disesuaikan)
    $maxRegionLen = 4;
    foreach ($regionsByLen as $len => $_) {
        if ($len > $maxRegionLen) {
            unset($regionsByLen[$len]); // abaikan terlalu panjang
        }
    }

    // scan tokens left-to-right (yang sebenarnya adalah dari akhir alamat asli)
    for ($i = 0; $i < $count; $i++) {
        // cek panjang segment dari besar -> kecil (prioritaskan panjang lebih panjang)
        $possibleLens = array_keys($regionsByLen);
        rsort($possibleLens);
        foreach ($possibleLens as $len) {
            if ($i + $len - 1 >= $count) continue;

            // ambil segmen di posisi i
            $segment = array_slice($tokens, $i, $len);

            // cek apakah segment adalah kebalikan dari salah satu region token
            // -> dibandingkan dengan array_reverse($regionTokens)
            foreach ($regionsByLen[$len] as $regionTokens) {
                // regionTokens sudah berupa token urut normal (misal ['jawa','barat'])
                // kita cek apakah $segment === array_reverse($regionTokens)
                if ($segment === array_reverse($regionTokens)) {
                    // perbaiki urutan pada tokens (swap ke urutan normal regionTokens)
                    for ($k = 0; $k < $len; $k++) {
                        $tokens[$i + $k] = $regionTokens[$k];
                    }
                    // jika perbaikan terjadi, lanjutkan ke posisi setelah segmen
                    // (untuk mencegah overlap deteksi yang aneh)
                    $i = $i + $len - 1;
                    break 2; // keluar ke loop for($i...)
                }
            }
        }
    }

    return $tokens;
}

//=============================
// DETECTION GENERIC (OPTIMIZED)
// - detectBestCandidate menerima df dan N optional untuk menghindari recompute
// - mem-cache normalisasi kandidat
//=============================

function detectBestCandidate($tokens, $candidates, $minPhraseThreshold = 80, $minFinalThreshold = 60, $precomputed = null)
{
    // precompute structure jika disediakan, jika tidak buat sendiri
    // precomputed = [
    //   'tokensNorm' => ...,
    //   'allNgrams' => ...,
    //   'df' => ...,
    //   'N' => ...,
    //   'candNorms' => [ idx => normalizedName ],
    //   'candTokens' => [ idx => tokenArray ]
    // ]
    $tokensNorm = array_map('normalizeName', $tokens);
    $allNgrams = generateNgrams($tokensNorm, 3);

    if ($precomputed && is_array($precomputed)) {
        $df = $precomputed['df'] ?? computeDocumentFrequencies($candidates);
        $N = $precomputed['N'] ?? count($candidates);
        $candNorms = $precomputed['candNorms'] ?? [];
        $candTokensMap = $precomputed['candTokens'] ?? [];
    } else {
        $df = computeDocumentFrequencies($candidates);
        $N = count($candidates);
        $candNorms = [];
        $candTokensMap = [];
    }

    // buat normalisasi kandidat bila belum ada
    foreach ($candidates as $idx => $c) {
        if (!isset($candNorms[$idx])) {
            $candNorms[$idx] = normalizeName($c['name']);
            $candTokensMap[$idx] = preg_split('/\s+/', $candNorms[$idx], -1, PREG_SPLIT_NO_EMPTY);
        }
    }

    $best = ['code' => null, 'name' => null, 'score' => 0, 'index' => null];

    foreach ($candidates as $idx => $c) {
        $candNorm = $candNorms[$idx];
        $candTokens = $candTokensMap[$idx];

        $bestPhrasePct = 0.0;
        // cari ngram terbaik
        foreach ($allNgrams as $phr) {
            $pct = phraseSimilarityPercentFlexible($phr, $candNorm);
            if ($pct > $bestPhrasePct) {
                $bestPhrasePct = $pct;
            }
        }

        $tfidfScore = computeTfidfScore($tokensNorm, $candTokens, $df, $N);

        if ($bestPhrasePct >= $minPhraseThreshold) {
            $finalScore = min(100, $bestPhrasePct * 1.1);
            $finalScore += $tfidfScore * 15;
            $finalScore = applyGenericPenalty($tokensNorm, $candTokens, $finalScore);
        } else {
            $overlap = wordOverlapScore($tokensNorm, $candTokens);
            $fullPhrasePct = phraseSimilarityPercentFlexible(implode(' ', $tokensNorm), $candNorm);

            $base = ($overlap * 0.55) + ($fullPhrasePct * 0.25) + ($tfidfScore * 20);
            $finalScore = applyGenericPenalty($tokensNorm, $candTokens, $base);
        }

        if ($finalScore > $best['score'] && $finalScore >= $minFinalThreshold) {
            $best = [
                'code' => $c['code'],
                'name' => $c['name'],
                'score' => round($finalScore, 2),
                'index' => $idx
            ];
            if (isset($c['province_code'])) {
                $best['province_code'] = $c['province_code'];
            }
            if (isset($c['regency_code'])) {
                $best['regency_code'] = $c['regency_code'];
            }
        }
    }

    return $best;
}

//=============================
// DETEKSI HIERARKI OPTIMASI (INTEGRASI SEMPURNA)
// - Membalik token dulu, perbaiki urutan untuk segmen wilayah yang terbalik,
//   lalu gunakan detectBestCandidate seperti semula.
// - Precompute DF dan normalisasi untuk setiap level kandidat untuk performa.
//=============================

function detectLocationOptimized($rawAddress, $provincies, $regencies, $districts)
{
    $rawAddress = strtolower(trim($rawAddress));
    $rawAddress = preg_replace('/[^\p{L}0-9\s]/u', ' ', $rawAddress);
    $rawAddress = preg_replace('/\s+/', ' ', $rawAddress);

    $tokens = preg_split('/\s+/', $rawAddress, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($tokens)) {
        return [
            'province' => ['code' => null, 'name' => null, 'score' => 0],
            'regency' => ['code' => null, 'name' => null, 'score' => 0],
            'district' => ['code' => null, 'name' => null, 'score' => 0]
        ];
    }

    // build region lists (dynamic, no hard-code)
    $regionNames = buildRegionNameList($provincies, $regencies, $districts);
    $regionTokenList = buildRegionTokenizedList($regionNames);

    // balik token dan perbaiki segmen yang merupakan reversed region
    $tokens = reverseTokensThenFixFromRegions($tokens, $regionTokenList);

    // Precompute DF & normalized candidate tokens untuk tiap level agar tidak compute berulang
    $provDf = computeDocumentFrequencies($provincies);
    $regDf = computeDocumentFrequencies($regencies);
    $distDf = computeDocumentFrequencies($districts);

    $provPre = [
        'df' => $provDf,
        'N' => count($provincies),
        'candNorms' => null,
        'candTokens' => null
    ];
    $regPre = [
        'df' => $regDf,
        'N' => count($regencies),
        'candNorms' => null,
        'candTokens' => null
    ];
    $distPre = [
        'df' => $distDf,
        'N' => count($districts),
        'candNorms' => null,
        'candTokens' => null
    ];

    $result = [
        'province' => ['code' => null, 'name' => null, 'score' => 0],
        'regency' => ['code' => null, 'name' => null, 'score' => 0],
        'district' => ['code' => null, 'name' => null, 'score' => 0]
    ];

    // 1) coba cari provinsi
    $provMatch = detectBestCandidate($tokens, $provincies, 80, 60, $provPre);
    if ($provMatch['code']) {
        $result['province'] = $provMatch;

        // persempit regency berdasarkan province_code
        $regInProv = array_filter($regencies, fn($r) => ($r['province_code'] ?? null) === $provMatch['code']);
        if (!empty($regInProv)) {
            // precompute df untuk subset ini (optional, but small)
            $regInProvList = array_values($regInProv);
            $regInProvDf = computeDocumentFrequencies($regInProvList);
            $regInProvPre = [
                'df' => $regInProvDf,
                'N' => count($regInProvList),
                'candNorms' => null,
                'candTokens' => null
            ];
            $regMatch = detectBestCandidate($tokens, $regInProvList, 80, 60, $regInProvPre);
            if ($regMatch['code']) {
                $result['regency'] = $regMatch;
            }
        }
    }

    // 2) jika provinsi tidak ditemukan, coba cari regency di seluruh data
    if (!$result['province']['code']) {
        $regMatch = detectBestCandidate($tokens, $regencies, 80, 60, $regPre);
        if ($regMatch['code']) {
            $result['regency'] = $regMatch;
            $provinceResult = getProvinceData($regMatch['province_code'], $provincies);
            $result['province'] = $provinceResult[0] ?? $result['province'];
        }
    }

    // 3) jika regency belum ditemukan, coba kecamatan
    if (!$result['regency']['code']) {
        $distMatch = detectBestCandidate($tokens, $districts, 70, 50, $distPre);
        if ($distMatch['code']) {
            $result['district'] = $distMatch;

            $regancyResult = getRegancyData($distMatch['regency_code'], $regencies);
            $result['regency'] = $regancyResult[0] ?? $result['regency'];

            if ($result['regency']) {
                $provinceResult = getProvinceData($result['regency']['province_code'], $provincies);
                $result['province'] = $provinceResult[0] ?? $result['province'];
            }
        }
    }

    return $result;
}

//=============================
// UTIL: fetch helpers
//=============================

function getProvinceData($code, $provincies)
{
    return array_values(array_filter($provincies, fn($p) => ($p['code'] ?? null) === $code));
}

function getRegancyData($code, $regencies)
{
    return array_values(array_filter($regencies, fn($r) => ($r['code'] ?? null) === $code));
}

//=============================
// END OF FILE
//=============================
