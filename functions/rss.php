<?php

// ══════════════════════════════════════════════════════════════════
// RsCaptcha Token Builder
// Builds the base64 response token from click coordinates + HTML
// ══════════════════════════════════════════════════════════════════

class RsCaptchaBuilder
{
    private const FINGERPRINT = [
        'screenWidth'        => '806',
        'screenHeight'       => '320',
        'availWidth'         => '806',
        'availHeight'        => '320',
        'colorDepth'         => '24',
        'pixelDepth'         => '24',
        'innerHeight'        => '320',
        'innerWidth'         => '806',
        'platform'           => 'Linux armv81',
        'appCodeName'        => 'Mozilla',
        'hardwareConcurrency'=> '8',
    ];

    private const SOURCE_TO_VALUE = [
        'screen_0'    => 'screenWidth',
        'screen_1'    => 'screenHeight',
        'screen_2'    => 'availWidth',
        'screen_3'    => 'availHeight',
        'screen_4'    => 'colorDepth',
        'screen_5'    => 'pixelDepth',
        'navigator_0' => 'appCodeName',
        'navigator_1' => 'appCodeName',
        'navigator_2' => 'mozFlag',
        'clientInfo_0'=> 'platform',
        'clientInfo_1'=> 'hardwareConcurrency',
        'window_0'    => 'innerHeight',
        'window_1'    => 'innerWidth',
        'document_0'  => 'hasFocus',
        'click_0'     => 'clickX',
        'click_1'     => 'clickY',
        'timestamp'   => 'timestamp',
    ];

    // ── Public entry point ────────────────────────────────────────

    public function build(int $x, int $y, string $html): string {
        $js    = $this->deobfuscate($html);
        $order = $js ? $this->extractFieldOrder($js) : $this->defaultOrder();
        return $this->generateToken($x, $y, $order);
    }

    // ── Token generation ──────────────────────────────────────────

    private function generateToken(int $x, int $y, array $order): string {
        $dynamic = [
            'timestamp' => (string) time(),
            'clickX'    => (string) $x,
            'clickY'    => (string) $y,
        ];

        $static = array_merge(self::FINGERPRINT, [
            'hasFocus' => '1',
            'mozFlag'  => '0',
        ]);

        $values = [];
        foreach ($order as $field) {
            $key      = self::SOURCE_TO_VALUE[$field['source']] ?? null;
            $values[] = $dynamic[$key] ?? $static[$key] ?? '0';
        }

        return base64_encode(implode(',', $values));
    }

    // ── JS deobfuscation ──────────────────────────────────────────

    private function deobfuscate(string $html): ?string {
        if (!preg_match('/\}\("([^"]+)",\d+,"([^"]+)",(\d+),(\d+),\d+\)\)/', $html, $m)) return null;

        [$encoded, $alphabet, $shift, $base] = [$m[1], $m[2], (int)$m[3], (int)$m[4]];

        if ($base >= strlen($alphabet)) return null;

        $separator = $alphabet[$base];
        $result    = '';

        foreach (explode($separator, $encoded) as $seg) {
            if ($seg === '') continue;

            $converted = $seg;
            for ($j = 0; $j < strlen($alphabet); $j++) {
                $converted = str_replace($alphabet[$j], (string)$j, $converted);
            }

            $charCode = $this->baseConvert($converted, $base) - $shift;
            if ($charCode > 0 && $charCode < 65536) $result .= mb_chr($charCode);
        }

        return $result ?: null;
    }

    private function baseConvert(string $encoded, int $base): int {
        $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/';
        $src    = substr($chars, 0, $base);
        $result = 0;
        $len    = strlen($encoded);

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($src, $encoded[$len - 1 - $i]);
            if ($pos !== false) $result += $pos * (int)pow($base, $i);
        }

        return $result;
    }

    // ── Field order extraction ────────────────────────────────────

    private function extractFieldOrder(string $js): array {
        $btoaIdx = strpos($js, 'btoa');
        if ($btoaIdx === false) return $this->defaultOrder();

        $section = substr($js, $btoaIdx, 3000);

        preg_match('/\((_0x[a-f0-9]+),/', $section, $first);
        preg_match_all('/\),(_0x[a-f0-9]+)\)/', $section, $rest);

        $order = array_merge(
            $first[1] ? [$first[1]] : [],
            array_slice($rest[1], 0, 16)
        );

        if (count($order) < 17) return $this->defaultOrder();

        $map = [];
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*screen\[/',             $map, 'screen',     6);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*navigator\[/',           $map, 'navigator',  3);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*clientInformation\[/',   $map, 'clientInfo', 2);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*Math\[.*?\]\(window\[.*?\]\)/', $map, 'window', 2);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*document\[/',            $map, 'document',   1);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*Math\[.*?\]\(_0x.*?\[/',$map, 'click',      2);

        if (preg_match('/(_0x[a-f0-9]+)\s*=\s*~~_0x/', $js, $m)) {
            $map[$m[1]] = 'timestamp';
        }

        return array_map(fn($v) => ['source' => $map[$v] ?? 'unknown', 'is_flag' => false],
                         array_slice($order, 0, 17));
    }

    private function mapVars(string $js, string $pattern, array &$map, string $prefix, int $limit): void {
        preg_match_all($pattern, $js, $m);
        foreach (array_slice($m[1], 0, $limit) as $i => $v) {
            $map[$v] = "{$prefix}_{$i}";
        }
    }

    private function defaultOrder(): array {
        return [
            ['source' => 'screen_4',    'is_flag' => false],
            ['source' => 'navigator_0', 'is_flag' => true ],
            ['source' => 'click_1',     'is_flag' => false],
            ['source' => 'click_0',     'is_flag' => false],
            ['source' => 'document_0',  'is_flag' => true ],
            ['source' => 'screen_1',    'is_flag' => false],
            ['source' => 'navigator_1', 'is_flag' => false],
            ['source' => 'navigator_2', 'is_flag' => true ],
            ['source' => 'window_0',    'is_flag' => false],
            ['source' => 'clientInfo_0','is_flag' => false],
            ['source' => 'screen_0',    'is_flag' => false],
            ['source' => 'window_1',    'is_flag' => false],
            ['source' => 'screen_2',    'is_flag' => false],
            ['source' => 'timestamp',   'is_flag' => false],
            ['source' => 'document_0',  'is_flag' => true ],
            ['source' => 'navigator_2', 'is_flag' => true ],
            ['source' => 'screen_5',    'is_flag' => false],
        ];
    }
}

// ══════════════════════════════════════════════════════════════════
// RsCaptcha Image Fetcher
// Extracts captcha filename from HTML and fetches the image bytes
// ══════════════════════════════════════════════════════════════════

class RsCaptchaImage
{
    private const BASE_URL = 'https://rscaptcha.com/assets/generated_captcha/';
    private const UA       = 'Mozilla/5.0 (Linux; Android 10; Infinix X680B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.99 Mobile Safari/537.36';

    /**
     * Returns raw image bytes, or null if not found / fetch failed.
     */
    public function fetch(string $html, string $referer): ?string {
        $filename = $this->extractFilename($html);
        if (!$filename) return null;

        return $this->download(self::BASE_URL . $filename, $referer) ?: null;
    }

    private function extractFilename(string $html): ?string {
        if (preg_match('/assets\/generated_captcha\/([^"]+)/', $html, $m)) return $m[1];
        return null;
    }

    private function download(string $url, string $referer): string|false {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'Host: rscaptcha.com',
                'sec-fetch-dest: image',
                'user-agent: ' . self::UA,
                'accept: image/webp,image/apng,image/*,*/*;q=0.8',
                "referer: $referer",
            ]),
        ]]);

        return file_get_contents($url, false, $context);
    }
}
