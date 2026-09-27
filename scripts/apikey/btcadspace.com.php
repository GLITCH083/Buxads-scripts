<?php
/**
 * BTCAdSpace Bot - Buxads Edition (PHP)
 * Faucet + Surfads
 * Uses existing AntiCaptcha system (same key as other scripts)
 * Site: https://btcadspace.com
 * Version: 1.6.0
 */

date_default_timezone_set("Asia/Karachi");
if (!defined("APP_HOST")) define("APP_HOST", "buxads-Bot");

if (!defined("BASE_DIR")) {
    $root = __DIR__;
    while ($root !== "/" && !file_exists($root . "/functions/function.php")) {
        $root = dirname($root);
    }
    define("BASE_DIR", $root);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!file_exists(BASE_DIR . "/functions/function.php")) {
    die("ERROR: functions/function.php not found!\nPut this file inside scripts/apikey/ of the buxads folder.\n");
}
if (!file_exists(BASE_DIR . "/functions/captcha.php")) {
    die("ERROR: functions/captcha.php not found!\n");
}

include_once BASE_DIR . "/functions/function.php";
include_once BASE_DIR . "/functions/captcha.php";

if (!defined("GREEN")) {
    define("RED", "\033[1;31;40m");
    define("GREEN", "\033[1;32;40m");
    define("YELLOW", "\033[1;33;40m");
    define("BLUE", "\033[1;34;40m");
    define("PURPLE", "\033[1;35;40m");
    define("CYAN", "\033[1;36;40m");
    define("GREY", "\033[1;30;40m");
    define("WHITE", "\033[1;37m");
    define("RESET", "\033[0m");
    define("BOLD", "\033[1m");
}

if (function_exists('enableCtrlC')) {
    enableCtrlC();
} elseif (function_exists('pcntl_signal')) {
    if (function_exists('pcntl_async_signals')) pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo "\n" . YELLOW . "Stopped by user (Ctrl+C)\n" . RESET;
        exit(0);
    });
}

if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

if (!defined("SITE")) define("SITE", "https://btcadspace.com");
if (!defined("AVISO")) define("AVISO", "https://aviso.bz/api/v1");
if (!defined("AVISO_PLATFORM")) define("AVISO_PLATFORM", "Linux armv81");
if (!defined("TURNSTILE_SITEKEY")) define("TURNSTILE_SITEKEY", "0x4AAAAAAAB-TZt_lwYtViEL");

$GLOBALS['faucet_claims'] = 0;
$GLOBALS['faucet_totalclaims'] = 0;
$GLOBALS['surf_claims'] = 0;
$GLOBALS['surf_totalclaims'] = 0;

// ========== THEME (same as makeyoutask / *pick) ==========
function themeLine() {
    echo CYAN . "──────────────────────────────────────────────\n" . RESET;
}
function themeBoxTop($title) {
    $w = 61;
    $plain = preg_replace('/\033\[[0-9;]*m/', '', $title);
    $pad = max(1, $w - 2 - strlen($plain));
    echo CYAN . "┌" . str_repeat("─", $w) . "┐\n" . RESET;
    echo CYAN . "│" . RESET . WHITE . BOLD . "  " . $title . str_repeat(" ", $pad - 2) . CYAN . "│\n" . RESET;
    echo CYAN . "├" . str_repeat("─", $w) . "┤\n" . RESET;
}
function themeBoxRow($label, $value) {
    $w = 61;
    $plain = preg_replace('/\033\[[0-9;]*m/', '', $value);
    $pad = max(1, $w - 16 - strlen($plain));
    echo CYAN . "│" . RESET . WHITE . "  " . str_pad($label, 12) . ": " . $value . str_repeat(" ", $pad) . CYAN . "│\n" . RESET;
}
function themeBoxBottom() {
    echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
}
function themeOk($msg) {
    echo GREEN . "✔ " . $msg . RESET . "\n";
}
function themeFail($msg) {
    echo RED . "✖ " . $msg . RESET . "\n";
}
function themeWarn($msg) {
    echo YELLOW . "⚠ " . $msg . RESET . "\n";
}
function themeInfo($msg) {
    echo WHITE . "◆ " . $msg . RESET . "\n";
}
function themeCap($msg) {
    echo YELLOW . "🧩 " . $msg . RESET . "\n";
}
function themeStar($msg) {
    echo PURPLE . "★ " . $msg . RESET . "\n";
}
function themeWait($msg) {
    echo YELLOW . "⏳ " . $msg . RESET . "\n";
}
function themeOpen($title) {
    $w = 61;
    echo "\n";
    echo CYAN . "┌" . str_repeat("─", $w) . "┐\n" . RESET;
    $plain = preg_replace('/\033\[[0-9;]*m/', '', $title);
    $pad = max(1, $w - 2 - strlen($plain));
    echo CYAN . "│" . RESET . WHITE . BOLD . "  " . $title . str_repeat(" ", $pad - 2) . CYAN . "│\n" . RESET;
    echo CYAN . "├" . str_repeat("─", $w) . "┤\n" . RESET;
}
function themeRow($label, $value) {
    $w = 61;
    $plainV = preg_replace('/\033\[[0-9;]*m/', '', (string)$value);
    if (strlen($plainV) > 42) {
        $value = substr($plainV, 0, 39) . "...";
        $plainV = $value;
    }
    $padV = max(1, $w - 16 - strlen($plainV));
    echo CYAN . "│" . RESET . WHITE . "  " . str_pad($label, 12) . ": " . $value . str_repeat(" ", $padV) . CYAN . "│\n" . RESET;
}
function themeClose() {
    echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
}
function themeStatus($title, $rows) {
    $w = 61;
    echo "\n";
    echo CYAN . "┌" . str_repeat("─", $w) . "┐\n" . RESET;
    $plain = preg_replace('/\033\[[0-9;]*m/', '', $title);
    $pad = max(1, $w - 2 - strlen($plain));
    echo CYAN . "│" . RESET . WHITE . BOLD . "  " . $title . str_repeat(" ", $pad - 2) . CYAN . "│\n" . RESET;
    echo CYAN . "├" . str_repeat("─", $w) . "┤\n" . RESET;
    foreach ($rows as $label => $value) {
        $plainV = preg_replace('/\033\[[0-9;]*m/', '', $value);
        $padV = max(1, $w - 16 - strlen($plainV));
        echo CYAN . "│" . RESET . WHITE . "  " . str_pad($label, 12) . ": " . $value . str_repeat(" ", $padV) . CYAN . "│\n" . RESET;
    }
    echo CYAN . "└" . str_repeat("─", $w) . "┘\n" . RESET;
}
function themeSection($title) {
    echo "\n" . CYAN . "── " . WHITE . BOLD . $title . RESET . CYAN . " ──\n" . RESET;
}
function themeClaim($progress, $total, $reward, $extra = "") {
    if (function_exists("logClaim")) {
        logClaim($progress, $total, $reward, $extra !== "" ? $extra : "-", null);
        return;
    }
    themeBoxTop("✅  CLAIM SUCCESSFUL");
    themeBoxRow("Progress", GREEN . $progress . WHITE . " / " . YELLOW . $total . RESET);
    themeBoxRow("Reward", GREEN . "+" . $reward . RESET);
    if ($extra !== "") themeBoxRow("Info", YELLOW . $extra . RESET);
    themeBoxBottom();
}
function themeCaptchaDetail($type, $tokenLen = 0, $tokenPreview = "", $antibot = "") {
    themeLine();
    echo WHITE . "🔐 Captcha  : " . YELLOW . $type . RESET;
    if ($tokenLen > 0) {
        echo WHITE . " | Len: " . GREEN . $tokenLen . RESET;
        if ($tokenPreview) echo WHITE . " | " . GREY . $tokenPreview . RESET;
    }
    echo "\n";
    if ($antibot !== "") {
        echo WHITE . "🧩 Antibot  : " . GREEN . $antibot . RESET . "\n";
    }
    themeLine();
}



function getAccountsFile() {
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) mkdir($infoDir, 0755, true);
    $file = $infoDir . "/btcadspace.com.txt";
    if (!file_exists($file)) {
        file_put_contents($file, "# Format 1 (Cookie): cookie_string\n# Format 2 (Login): username|password\n");
    }
    return $file;
}

function loadAccounts() {
    $file = getAccountsFile();
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $accounts = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) >= 1 && $parts[0] !== '') $accounts[] = $parts;
    }
    return $accounts;
}

function saveAccounts($accounts) {
    $content = "# Format 1 (Cookie): cookie_string\n# Format 2 (Login): username|password\n";
    foreach ($accounts as $acc) $content .= implode('|', $acc) . "\n";
    file_put_contents(getAccountsFile(), $content);
}

function addAccount($data) {
    $accounts = loadAccounts();
    $accounts[] = $data;
    saveAccounts($accounts);
    return true;
}

function deleteAccountByIndex($index) {
    $accounts = loadAccounts();
    if (!isset($accounts[$index])) return false;
    array_splice($accounts, $index, 1);
    saveAccounts($accounts);
    return true;
}

function editAccountByIndex($index, $newData) {
    $accounts = loadAccounts();
    if (!isset($accounts[$index])) return false;
    $accounts[$index] = $newData;
    saveAccounts($accounts);
    return true;
}

function accountLabel($parts) {
    if (count($parts) >= 2 && strpos($parts[0], '=') === false && strpos($parts[0], ' ') === false) return $parts[0];
    $c = $parts[0];
    return (strlen($c) > 40) ? substr($c, 0, 28) . "..." : $c;
}

function displayAccounts() {
    $accounts = loadAccounts();
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  SAVED ACCOUNTS" . str_repeat(" ", 45) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    if (empty($accounts)) {
        echo CYAN . "║" . YELLOW . "  No accounts saved yet" . str_repeat(" ", 38) . CYAN . "║\n" . RESET;
    } else {
        foreach ($accounts as $i => $p) {
            $type = (count($p) >= 2 && strpos($p[0], '=') === false) ? "Login" : "Cookie";
            $label = accountLabel($p);
            $line = "  " . ($i + 1) . ". [$type] $label";
            $pad = max(1, 61 - strlen($line));
            echo CYAN . "║" . WHITE . $line . str_repeat(" ", $pad) . CYAN . "║\n" . RESET;
        }
    }
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
}

function selectAccount() {
    $accounts = loadAccounts();
    if (empty($accounts)) { echo YELLOW . "No accounts. Add some first!\n" . RESET; return null; }
    displayAccounts();
    echo WHITE . "Select account number: " . RESET;
    $choice = intval(trim(fgets(STDIN)));
    if ($choice < 1 || $choice > count($accounts)) { echo RED . "Invalid selection!\n" . RESET; return null; }
    return $accounts[$choice - 1];
}

function btcHeaders($cookie = null, $referer = null) {
    $h = [
        "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.9",
        "Origin: " . SITE,
        "Referer: " . ($referer ?: SITE . "/"),
        "Sec-Fetch-Dest: document",
        "Sec-Fetch-Mode: navigate",
        "Sec-Fetch-Site: same-origin",
        "Sec-Fetch-User: ?1",
        "Upgrade-Insecure-Requests: 1",
        "Cache-Control: max-age=0",
    ];
    if ($cookie) $h[] = "Cookie: " . $cookie;
    return $h;
}

function mergeCookies($old, $new) {
    $map = [];
    foreach ([$old, $new] as $str) {
        if (!$str) continue;
        foreach (explode(';', $str) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, '=') === false) continue;
            list($k, $v) = explode('=', $part, 2);
            $k = trim($k); $v = trim($v);
            if ($k === '' || strcasecmp($k, 'Path') === 0 || strcasecmp($k, 'Domain') === 0
                || strcasecmp($k, 'Expires') === 0 || strcasecmp($k, 'Max-Age') === 0
                || strcasecmp($k, 'HttpOnly') === 0 || strcasecmp($k, 'Secure') === 0
                || strcasecmp($k, 'SameSite') === 0) continue;
            $map[$k] = $v;
        }
    }
    $out = [];
    foreach ($map as $k => $v) $out[] = "$k=$v";
    return implode('; ', $out);
}

function btcGet($path, $cookie = null) {
    $url = (strpos($path, "http") === 0) ? $path : SITE . $path;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => btcHeaders($cookie), CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return ["header" => substr($resp, 0, $hs), "body" => substr($resp, $hs), "http_code" => $code, "url" => $final];
}

function btcPost($path, $data, $cookie = null, $referer = "", $ajax = false) {
    $url = (strpos($path, "http") === 0) ? $path : SITE . $path;
    $headers = btcHeaders($cookie);
    $headers[] = "Content-Type: application/x-www-form-urlencoded";
    if ($ajax) {
        $headers[] = "X-Requested-With: XMLHttpRequest";
        $headers[] = "Accept: application/json, text/javascript, */*; q=0.01";
    }
    if ($referer) $headers[] = "Referer: $referer";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => is_array($data) ? http_build_query($data) : $data,
        CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => true,
    ]);
    $resp = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ["header" => substr($resp, 0, $hs), "body" => substr($resp, $hs), "http_code" => $code];
}

function extractCsrf($html) {
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)/', $html, $m)) return $m[1];
    if (preg_match('/csrf_token["\']?\s*[:=]\s*["\']([^"\']+)/', $html, $m)) return $m[1];
    return "";
}

function isLoggedIn($cookie) {
    $r = btcGet("/account", $cookie);
    if ($r["http_code"] != 200) return false;
    $t = strtolower($r["body"]);
    if (strpos($r["url"], "/login") !== false) return false;
    if (strpos($t, "logout") !== false || strpos($t, "balance") !== false || strpos($t, "coins") !== false) {
        if (strpos($t, 'name="username"') !== false && strpos($t, 'name="password"') !== false && strpos($t, "logout") === false) return false;
        return true;
    }
    return false;
}

function extractCookiesFromHeader($header) {
    preg_match_all('/^Set-Cookie:\s*([^;\r\n]+)/mi', $header, $matches);
    if (empty($matches[1])) return "";
    $map = [];
    foreach ($matches[1] as $pair) {
        $kv = explode('=', $pair, 2);
        if (count($kv) == 2) $map[trim($kv[0])] = trim($kv[1]);
    }
    $out = [];
    foreach ($map as $k => $v) $out[] = "$k=$v";
    return implode('; ', $out);
}

function extractToken($result) {
    if (empty($result) || !is_array($result)) return null;
    foreach (['token', 'request', 'gRecaptchaResponse', 'cf-turnstile-response'] as $k) {
        if (!empty($result[$k]) && is_string($result[$k])) {
            $v = trim($result[$k]);
            if (in_array(strtoupper($v), ['CAPCHA_NOT_READY', 'ERROR', 'ERROR_CAPTCHA_UNSOLVABLE', 'ERROR_WRONG_USER_KEY', 'ERROR_KEY_DOES_NOT_EXIST', 'ERROR_ZERO_BALANCE'], true)) continue;
            if (strlen($v) > 20) return $v;
        }
    }
    return null;
}

function solveTurnstile($pageurl, $silent = false) {
    if (!$silent) themeCap("Solving Turnstile...");
    if (empty(anticaptcha_key)) {
        if (!$silent) themeFail("No anticaptcha key");
        return null;
    }
    $result = captcha($pageurl, TURNSTILE_SITEKEY, "turnstile");
    $tok = extractToken($result);
    if (!$tok) {
        if (!$silent) themeFail("Turnstile failed");
        return null;
    }
    if (!$silent) themeOk("Turnstile ok · Len: " . strlen($tok) . " · " . substr($tok, 0, 22) . "...");
    return $tok;
}

function solveAntibot($html, $silent = false) {
    if (!$silent) themeCap("Solving Antibot...");

    // Extract main image (order <img ...>)
    if (!preg_match('/order\s*<img\s+src="(data:image\/png;base64,[^"]+)"/i', $html, $m)) {
        if (!$silent) themeFail("Antibot main image not found");
        return null;
    }
    $mainB64 = $m[1];
    if (strpos($mainB64, ',') !== false) {
        $mainB64 = explode(',', $mainB64, 2)[1];
    }

    // Extract subs from ablinks = [ ... ]
    $subs = [];
    if (preg_match('/ablinks\s*=\s*(\[.*?\]);/s', $html, $m)) {
        $raw = $m[1];
        if (preg_match_all('/rel=\\\\?"(\d+)\\\\?".*?src=\\\\?"(data:image\/[^\\\\"]+)\\\\?"/s', $raw, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $b) {
                $src = str_replace('\\/', '/', $b[2]);
                if (strpos($src, ',') !== false) $src = explode(',', $src, 2)[1];
                $subs[$b[1]] = $src;
            }
        }
    }
    // Fallback: generic antibot() from captcha.php
    if (count($subs) < 2 && function_exists('antibot')) {
        $order = antibot($html);
        if ($order) {
            // Must be leading space + space-separated IDs (form-urlencoded -> +id1+id2)
            $ids = preg_match_all('/\d+/', $order, $mm) ? $mm[0] : [];
            if ($ids) {
                $order = " " . implode(" ", $ids);
                if (!$silent) themeOk("Antibot ok · " . trim(str_replace(" ", "+", $order)));
                return $order;
            }
        }
    }
    if (count($subs) < 2) {
        if (!$silent) themeFail("Antibot subs not found (" . count($subs) . ")");
        return null;
    }

    if (!$silent) themeInfo("main + " . count($subs) . " subs");

    // Call Vernuable/AntiCaptcha antibot API directly
    $payload = [
        "key"    => anticaptcha_key,
        "method" => "antibot",
        "main"   => $mainB64,
        "sub"    => $subs,
        "json"   => "1",
    ];
    $create = json_decode(Run(api_in, ["Content-Type: application/json"], json_encode($payload, JSON_UNESCAPED_SLASHES))["body"], true);
    if (empty($create["request"]) || (int)($create["status"] ?? 0) !== 1) {
        if (!$silent) themeFail("Antibot submit failed");
        return null;
    }
    $res = poll($create["request"]);
    $rawOrder = "";
    if (!empty($res["order"])) $rawOrder = $res["order"];
    elseif (!empty($res["request"]) && is_string($res["request"])) $rawOrder = $res["request"];

    preg_match_all('/\d+/', (string)$rawOrder, $mm);
    $ids = $mm[0] ?? [];
    if (!$ids) {
        if (!$silent) themeFail("Antibot empty order");
        return null;
    }

    // CRITICAL: leading space + spaces between IDs
    // http_build_query turns spaces into +  => antibotlinks=+id1+id2+id3 (matches browser)
    $order = " " . implode(" ", $ids);
    if (!$silent) themeOk("Antibot ok · " . trim(str_replace(" ", "+", $order)));
    return $order;
}


function saveCookieForAccount($parts, $cookie) {
    // Update matching login account to cookie format so next run uses session
    $accounts = loadAccounts();
    $changed = false;
    foreach ($accounts as $i => $acc) {
        if (count($acc) >= 2 && ($acc[0] ?? '') === ($parts[0] ?? '') && ($acc[1] ?? '') === ($parts[1] ?? '')) {
            $accounts[$i] = [$cookie];
            $changed = true;
            break;
        }
        // already cookie matching prefix
        if (count($acc) == 1 && strpos($cookie, substr($acc[0], 0, 20)) !== false) {
            $accounts[$i] = [$cookie];
            $changed = true;
            break;
        }
    }
    if ($changed) {
        saveAccounts($accounts);
        themeOk("Session cookie saved for next run");
    }
}

function loginPassword($username, $password) {
    themeInfo("Mode: Login → " . $username);
    themeStar("Logging in...");

    // 1) Load login page and keep session cookies (required or server returns 403)
    $r = btcGet("/login");
    if ($r["http_code"] == 403) {
        return [false, null, "login page blocked (403) - try Cookie mode or wait"];
    }
    if ($r["http_code"] != 200) return [false, null, "login page error " . $r["http_code"]];

    $sessionCookie = extractCookiesFromHeader($r["header"]);
    $csrf = extractCsrf($r["body"]);
    if (!$csrf) return [false, null, "csrf missing on login"];

    // 2) Solve Turnstile
    $ts = solveTurnstile(SITE . "/login");
    if (!$ts) return [false, null, "turnstile failed"];

    $data = [
        "csrf_token" => $csrf,
        "username" => $username,
        "password" => $password,
        "remember" => "1",
        "cf-turnstile-response" => $ts
    ];

    // 3) POST login WITH session cookies from step 1
    $resp = btcPost("/login", $data, $sessionCookie, SITE . "/login");
    $cookie = mergeCookies($sessionCookie, extractCookiesFromHeader($resp["header"]));

    if ($cookie && isLoggedIn($cookie)) {
        return [true, $cookie, "ok"];
    }

    // Retry once with fresh turnstile if 403 (token sometimes expires)
    if ($resp["http_code"] == 403) {
        echo YELLOW . "403 on login - retrying with fresh Turnstile...\n" . RESET;
        $r2 = btcGet("/login");
        $sessionCookie = mergeCookies($sessionCookie, extractCookiesFromHeader($r2["header"]));
        $csrf = extractCsrf($r2["body"]) ?: $csrf;
        $ts = solveTurnstile(SITE . "/login");
        if ($ts) {
            $data["csrf_token"] = $csrf;
            $data["cf-turnstile-response"] = $ts;
            $resp = btcPost("/login", $data, $sessionCookie, SITE . "/login");
            $cookie = mergeCookies($sessionCookie, extractCookiesFromHeader($resp["header"]));
            if ($cookie && isLoggedIn($cookie)) return [true, $cookie, "ok"];
        }
    }

    $body = strtolower($resp["body"]);
    if (strpos($body, "invalid") !== false || strpos($body, "incorrect") !== false || strpos($body, "wrong") !== false) {
        return [false, null, "invalid username/password"];
    }
    if (strpos($body, "2fa") !== false || strpos($body, "two-factor") !== false) return [false, null, "2FA required"];
    if (strpos($body, "captcha") !== false || strpos($body, "turnstile") !== false) return [false, null, "captcha rejected"];
    if ($resp["http_code"] == 403) {
        return [false, null, "blocked 403 - use Cookie mode (login in browser, paste Cookie)"];
    }
    return [false, null, "login failed (http " . $resp["http_code"] . ")"];
}

function loginFromParts($parts) {
    // Prefer saved cookie session (from previous successful login)
    if (count($parts) == 1 || (count($parts) >= 1 && strpos($parts[0], '=') !== false)) {
        $cookie = preg_replace('/(?i)^cookie:\s*/', '', $parts[0]);
        themeInfo("Mode: Saved Cookie Session");
        if (isLoggedIn($cookie)) {
            themeOk("Session still valid");
            return [true, $cookie, "session ok"];
        }
        themeWarn("Cookie expired — need re-login or new cookie");
        return [false, null, "cookie expired"];
    }
    // Login with user/pass, then SAVE cookie so next run uses session
    $res = loginPassword($parts[0], $parts[1] ?? '');
    if ($res[0] && !empty($res[1])) {
        saveCookieForAccount($parts, $res[1]);
    }
    return $res;
}

function faucetClaim($cookie) {
    themeOpen("FAUCET CLAIM");

    $r = btcGet("/faucet", $cookie);
    if ($r["http_code"] == 401 || strpos($r["url"], "/login") !== false) {
        themeRow("Status", RED . "session dead" . RESET);
        themeClose();
        return [false, "session dead"];
    }
    if ($r["http_code"] != 200) {
        themeRow("Status", RED . "page error " . $r["http_code"] . RESET);
        themeClose();
        return [false, "faucet page " . $r["http_code"]];
    }
    $html = $r["body"];

    if (preg_match('/maximum daily claims|daily limit|reached the maximum/i', $html)) {
        themeRow("Status", YELLOW . "daily limit reached" . RESET);
        themeClose();
        return [false, "daily limit reached"];
    }
    if (preg_match('/wait|cooldown|next claim|come back|already claimed/i', $html)) {
        $extra = "";
        if (preg_match('/(\d+)\s*(?:minute|second|min|sec)/i', $html, $tm)) $extra = " (~" . $tm[0] . ")";
        themeRow("Status", YELLOW . "cooldown" . $extra . RESET);
        themeClose();
        return [false, "cooldown" . $extra];
    }

    $csrf = extractCsrf($html);
    if (!$csrf) {
        themeRow("Status", RED . "csrf missing" . RESET);
        themeClose();
        return [false, "csrf missing (not logged in?)"];
    }
    if (!preg_match('/order\s*<img\s+src="(data:image\/png;base64,[^"]+)"/i', $html)) {
        $msg = preg_match('/ablinks/i', $html) ? "antibot image not found" : "faucet form missing";
        themeRow("Status", RED . $msg . RESET);
        themeClose();
        return [false, $msg];
    }

    themeRow("Captcha", YELLOW . "Solving Antibot..." . RESET);
    $order = solveAntibot($html, true);
    if (!$order) {
        themeRow("Antibot", RED . "failed" . RESET);
        themeClose();
        return [false, "antibot failed"];
    }
    themeRow("Antibot", GREEN . trim(str_replace(" ", "+", $order)) . RESET);

    themeRow("Captcha", YELLOW . "Solving Turnstile..." . RESET);
    $ts = solveTurnstile(SITE . "/faucet", true);
    if (!$ts) {
        themeRow("Captcha", RED . "Turnstile failed" . RESET);
        themeClose();
        return [false, "turnstile failed"];
    }
    themeRow("Captcha", GREEN . "AntiBot+Turnstile Len " . strlen($ts) . RESET);
    themeRow("Token", GREY . substr($ts, 0, 28) . "..." . RESET);

    $resp = btcPost("/faucet", [
        "csrf_token" => $csrf,
        "antibotlinks" => $order,
        "cf-turnstile-response" => $ts
    ], $cookie, SITE . "/faucet");
    $body = $resp["body"];

    if (preg_match('/You have earned[^<!.]{0,80}|claimed successfully|Coins?\s+(?:have been\s+)?(?:added|credited)[^<!.]{0,40}/i', $body, $m)) {
        $msg = trim(preg_replace('/\s+/', ' ', strip_tags($m[0])));
        themeRow("Status", GREEN . "SUCCESS" . RESET);
        themeRow("Reward", GREEN . $msg . RESET);
        themeClose();
        return [true, $msg];
    }
    if (preg_match('/notyf\.open\(\{\s*type:\s*[\'"]success[\'"],\s*message:\s*[\'"]([^\'"]+)/i', $body, $m)) {
        themeRow("Status", GREEN . "SUCCESS" . RESET);
        themeRow("Reward", GREEN . $m[1] . RESET);
        themeClose();
        return [true, $m[1]];
    }
    if (preg_match('/notyf\.open\(\{\s*type:\s*[\'"](?:danger|warning|error)[\'"],\s*message:\s*[\'"]([^\'"]+)/i', $body, $m)) {
        themeRow("Status", RED . $m[1] . RESET);
        themeClose();
        return [false, $m[1]];
    }
    if (preg_match('/maximum daily|daily limit/i', $body)) {
        themeRow("Status", YELLOW . "daily limit reached" . RESET);
        themeClose();
        return [false, "daily limit reached"];
    }
    if (preg_match('/wait|cooldown|next claim/i', $body)) {
        themeRow("Status", YELLOW . "cooldown" . RESET);
        themeClose();
        return [false, "cooldown"];
    }
    if (preg_match('/invalid (antibot|captcha|token)|wrong order|incorrect/i', $body)) {
        themeRow("Status", RED . "antibot/captcha rejected" . RESET);
        themeClose();
        return [false, "antibot/captcha rejected"];
    }
    $r2 = btcGet("/faucet", $cookie);
    if (preg_match('/You have earned|claimed successfully/i', $r2["body"])) {
        themeRow("Status", GREEN . "SUCCESS" . RESET);
        themeRow("Reward", GREEN . "claimed" . RESET);
        themeClose();
        return [true, "claimed"];
    }
    if (preg_match('/maximum daily|daily limit/i', $r2["body"])) {
        themeRow("Status", YELLOW . "daily limit reached" . RESET);
        themeClose();
        return [false, "daily limit reached"];
    }
    themeRow("Status", RED . "claim unclear" . RESET);
    themeClose();
    return [false, "claim unclear (http " . $resp["http_code"] . ")"];
}


function surfList($cookie) {
    $r = btcGet("/surf", $cookie);
    if ($r["http_code"] != 200 || strpos($r["url"], "/login") !== false) return [];
    $html = $r["body"];
    $items = [];
    $hidden = 0;

    // Parse each surf card. Site hides already-clicked ads with class "d-none"
    // Only claim ads that are SHOWN (no d-none on the <a class="card ...">)
    if (preg_match_all(
        '/<a\s+href="(\/surf\/([a-f0-9]+))"\s+class="([^"]*)"[^>]*>[\s\S]*?(?:fa-coins|far fa-coins)[\s\S]*?(\d+)\s*Coins[\s\S]*?(?:fa-stopwatch|far fa-stopwatch)[\s\S]*?(\d+)\s*seconds[\s\S]*?<\/a>/i',
        $html,
        $m,
        PREG_SET_ORDER
    )) {
        foreach ($m as $row) {
            $path = $row[1];
            $hash = $row[2];
            $classes = $row[3];
            $coins = intval($row[4]);
            $secs = intval($row[5]);
            // Skip hidden / already clicked
            if (preg_match('/\bd-none\b/i', $classes)) {
                $hidden++;
                continue;
            }
            $items[] = ["path" => $path, "hash" => $hash, "coins" => $coins, "seconds" => $secs];
        }
    }

    // Fallback: any /surf/HASH link whose parent card is not d-none
    if (empty($items)) {
        if (preg_match_all('/<a\s+href="(\/surf\/([a-f0-9]+))"\s+class="([^"]*)"/i', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                if (preg_match('/\bd-none\b/i', $row[3])) {
                    $hidden++;
                    continue;
                }
                $items[] = ["path" => $row[1], "hash" => $row[2], "coins" => 0, "seconds" => 10];
            }
        }
    }

    $seen = [];
    $out = [];
    foreach ($items as $it) {
        if (!isset($seen[$it["hash"]])) {
            $seen[$it["hash"]] = true;
            $out[] = $it;
        }
    }
    // return hidden count via global for themed display
    $GLOBALS["surf_hidden_count"] = $hidden;
    return $out;
}

function surfOne($cookie, $item) {
    $path = $item["path"];
    $uid = $item["hash"];
    $secs = max(3, intval($item["seconds"] ?: 10));
    $coins = $item["coins"] ?: "?";

    themeOpen("SURF CLAIM");
    themeRow("Ad", GREY . substr($uid, 0, 16) . "..." . RESET);
    themeRow("Reward", YELLOW . $coins . " coins" . RESET);
    themeRow("Timer", WHITE . $secs . "s" . RESET);

    $r = btcGet($path, $cookie);
    if ($r["http_code"] == 401 || strpos($r["url"], "/login") !== false) {
        themeRow("Status", RED . "session dead" . RESET);
        themeClose();
        return [false, "session dead"];
    }
    if ($r["http_code"] != 200) {
        themeRow("Status", RED . "open " . $r["http_code"] . RESET);
        themeClose();
        return [false, "open " . $r["http_code"]];
    }
    $view = $r["body"];
    $sid = "";
    if (preg_match('/\bid\s*=\s*["\']([a-f0-9]{32,})["\']/i', $view, $m)) $sid = $m[1];
    if (!$sid && preg_match('/["\']id["\']\s*[:=]\s*["\']([a-f0-9]{32,})["\']/i', $view, $m)) $sid = $m[1];
    if (!$sid) {
        $csrfTmp = extractCsrf($view);
        if (preg_match_all('/["\']([a-f0-9]{64})["\']/', $view, $all)) {
            foreach ($all[1] as $hx) { if ($hx !== $csrfTmp) { $sid = $hx; break; } }
        }
    }
    if (!$sid) {
        themeRow("Status", RED . "page id missing" . RESET);
        themeClose();
        return [false, "page id missing"];
    }
    if (preg_match('/\bcount\s*=\s*(\d+)/', $view, $m)) $secs = max($secs, intval($m[1]));
    $hasCaptcha = true;
    if (preg_match('/\bhasCAPTCHA\s*=\s*(!0|!1|true|false)/i', $view, $m)) {
        $hasCaptcha = in_array(strtolower($m[1]), ['!0', 'true'], true);
    }
    if (preg_match('/id=["\']uid["\'][^>]*value=["\']([^"\']*)["\']/i', $view, $m) && $m[1]) $uid = $m[1];
    if (preg_match('/name=["\']uid["\'][^>]*value=["\']([^"\']*)["\']/i', $view, $m) && $m[1]) $uid = $m[1];
    $csrf = extractCsrf($view);
    $cVal = $sid . mt_rand(1, 9999);
    btcGet("/surf/{$uid}/{$cVal}", $cookie);
    $wait = $secs + (mt_rand(12, 25) / 10);
    themeRow("Watching", YELLOW . round($wait) . "s..." . RESET);
    sleep((int)$wait);
    if (!$csrf) { $r3 = btcGet($path, $cookie); $csrf = extractCsrf($r3["body"]); }
    if (!$csrf) { $r4 = btcGet("/surf", $cookie); $csrf = extractCsrf($r4["body"]); }
    if (!$csrf) {
        themeRow("Status", RED . "csrf missing" . RESET);
        themeClose();
        return [false, "csrf missing"];
    }
    $ts = "";
    if ($hasCaptcha) {
        themeRow("Captcha", YELLOW . "Solving Turnstile..." . RESET);
        $ts = solveTurnstile(SITE . $path, true);
        if (!$ts) {
            themeRow("Captcha", RED . "Turnstile failed" . RESET);
            themeClose();
            return [false, "turnstile failed"];
        }
        themeRow("Captcha", GREEN . "Turnstile Len " . strlen($ts) . RESET);
        themeRow("Token", GREY . substr($ts, 0, 28) . "..." . RESET);
    }
    $payload = ["csrf_token" => $csrf, "uid" => $uid, "c" => $cVal];
    if ($ts) $payload["cf-turnstile-response"] = $ts;
    $resp = btcPost("/ajax/surf", $payload, $cookie, SITE . $path, true);
    if ($resp["http_code"] == 401) {
        themeRow("Status", RED . "http 401" . RESET);
        themeClose();
        return [false, "http 401"];
    }
    $j = json_decode($resp["body"], true);
    if (is_array($j) && !empty($j["success"])) {
        $msg = $j["message"] ?? "ok";
        themeRow("Status", GREEN . "SUCCESS" . RESET);
        themeRow("Reward", GREEN . $msg . RESET);
        themeClose();
        return [true, $msg];
    }
    $err = is_array($j) ? ($j["message"] ?? "fail") : substr($resp["body"], 0, 60);
    themeRow("Status", RED . $err . RESET);
    themeClose();
    return [false, $err];
}

function showClaimBox($progress, $reward, $extra = "") {
    if (function_exists('logClaim')) { logClaim($progress, $progress, $reward, $extra ?: "-", null); return; }
    $w = 61;
    echo CYAN . "┌" . str_repeat("─", $w) . "┐\n" . RESET;
    echo CYAN . "│" . WHITE . BOLD . "  CLAIM SUCCESSFUL" . str_repeat(" ", $w - 18) . CYAN . "│\n" . RESET;
    echo CYAN . "├" . str_repeat("─", $w) . "┤\n" . RESET;
    echo CYAN . "│" . WHITE . "  Progress   : " . GREEN . $progress . RESET . str_repeat(" ", max(1, $w - 16 - strlen((string)$progress))) . CYAN . "│\n" . RESET;
    echo CYAN . "│" . WHITE . "  Reward     : " . GREEN . "+" . $reward . RESET . str_repeat(" ", max(1, $w - 17 - strlen($reward))) . CYAN . "│\n" . RESET;
    if ($extra !== "") echo CYAN . "│" . WHITE . "  Info       : " . YELLOW . $extra . RESET . str_repeat(" ", max(1, $w - 17 - strlen($extra))) . CYAN . "│\n" . RESET;
    echo CYAN . "└" . str_repeat("─", $w) . "┘\n" . RESET;
}


// ========== VIDEOS (Aviso YouTube) — same flow as Python ==========
function avisoJson($method, $url, $apiKey, $jsonBody = null) {
    $ch = curl_init();
    $headers = [
        "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36",
        "Accept: */*",
        "Content-Type: application/json",
        "X-API-Key: " . $apiKey,
        "Origin: " . SITE,
        "Referer: " . SITE . "/",
        "Accept-Language: en",
    ];
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if (strtoupper($method) === "POST") {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $jsonBody !== null ? json_encode($jsonBody) : "{}";
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode($raw, true);
    return ["http_code" => $code, "body" => $raw, "json" => is_array($j) ? $j : []];
}

function ytConfig($cookie) {
    $r = btcGet("/ytvideos", $cookie);
    $html = $r["body"] ?? "";
    $apiKey = "";
    $hash = "";
    if (preg_match('/data-api-key="(ak_[a-f0-9]+)"/i', $html, $m)) $apiKey = $m[1];
    if (preg_match('/\bhash="([a-f0-9]{32,})"/i', $html, $m)) $hash = $m[1];
    if (!$hash && preg_match('/hash["\']?\s*[:=]\s*["\']([a-f0-9]{32,})["\']/i', $html, $m)) $hash = $m[1];
    return [$apiKey, $hash];
}

function ytIdentify($apiKey, $hash) {
    $fp = bin2hex(random_bytes(16));
    avisoJson("POST", AVISO . "/youtube/tasks/identify", $apiKey, [
        "hash" => $hash,
        "ip" => null,
        "userAgent" => "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36",
        "fingerprint" => [
            "fp" => $fp,
            "langs" => "en-US, en",
            "timezone" => "Asia/Karachi",
        ],
    ]);
}

function ytOne($apiKey, $hash, $ad) {
    $tid = $ad["id"] ?? null;
    $typ = $ad["type"] ?? "ads";
    $duration = intval($ad["duration"] ?? 5);
    $amount = $ad["amount"] ?? "?";

    themeOpen("VIDEO CLAIM");
    themeRow("Task", GREY . substr((string)$tid, 0, 18) . RESET);
    themeRow("Type", WHITE . $typ . RESET);
    themeRow("Reward", YELLOW . "$" . $amount . RESET);
    themeRow("Duration", WHITE . $duration . "s" . RESET);

    $st = avisoJson("POST", AVISO . "/youtube/tasks/start", $apiKey, [
        "taskId" => $tid,
        "hash" => $hash,
        "type" => $typ,
        "platform" => AVISO_PLATFORM,
    ]);
    $sj = $st["json"];
    if (empty($sj["success"]) && empty($sj["attemptId"])) {
        themeRow("Status", RED . "start fail" . RESET);
        themeClose();
        return [false, "start fail"];
    }
    $attempt = $sj["attemptId"] ?? null;
    $need = intval($sj["duration"] ?? $duration);
    themeRow("Attempt", GREY . substr((string)$attempt, 0, 16) . "..." . RESET);

    avisoJson("POST", AVISO . "/youtube/tasks/timer-status", $apiKey, [
        "attemptId" => $attempt,
        "action" => "start",
    ]);

    $wait = $need + (mt_rand(20, 40) / 10);
    themeRow("Watching", YELLOW . round($wait) . "s (need " . $need . ")" . RESET);
    sleep((int)$wait);

    for ($i = 0; $i < 3; $i++) {
        $chk = avisoJson("POST", AVISO . "/youtube/tasks/timer-status", $apiKey, [
            "attemptId" => $attempt,
            "action" => "check",
        ]);
        if (!empty($chk["json"]["viewExists"])) break;
        sleep(2);
    }

    $comp = avisoJson("POST", AVISO . "/youtube/tasks/timer-status", $apiKey, [
        "attemptId" => $attempt,
        "action" => "complete",
        "watchedTime" => $need,
    ]);
    $cj = $comp["json"];
    if (!(($cj["status"] ?? "") === "good" || !empty($cj["verified"]))) {
        themeRow("Status", RED . "timer not verified" . RESET);
        themeClose();
        return [false, "timer: " . json_encode($cj)];
    }

    $fin = avisoJson("POST", AVISO . "/youtube/tasks/complete", $apiKey, [
        "attemptId" => $attempt,
    ]);
    $fj = $fin["json"];
    if (isset($fj["earned"])) {
        $msg = "+" . $fj["earned"] . " " . ($fj["currency"] ?? "usd");
        themeRow("Status", GREEN . "SUCCESS" . RESET);
        themeRow("Reward", GREEN . $msg . RESET);
        themeClose();
        return [true, $msg];
    }
    themeRow("Status", RED . "complete fail" . RESET);
    themeClose();
    return [false, json_encode($fj)];
}

function ytRun($cookie, $maxTasks = 10) {
    list($apiKey, $hash) = ytConfig($cookie);
    if (!$apiKey || !$hash) {
        themeStatus("VIDEOS STATUS", [
            "Available" => YELLOW . "0" . RESET,
            "Action"    => YELLOW . "No api-key/hash — open /ytvideos once in browser" . RESET,
        ]);
        return 0;
    }

    themeStatus("VIDEOS STATUS", [
        "API Key" => GREY . substr($apiKey, 0, 16) . "..." . RESET,
        "Hash"    => GREY . substr($hash, 0, 16) . "..." . RESET,
        "Action"  => GREEN . "Fetching tasks..." . RESET,
    ]);

    ytIdentify($apiKey, $hash);

    $availUrl = AVISO . "/youtube/tasks/available?hash=" . urlencode($hash) . "&platform=" . urlencode(AVISO_PLATFORM);
    $r = avisoJson("GET", $availUrl, $apiKey);
    $ads = $r["json"]["ads"] ?? [];
    $count = is_array($ads) ? count($ads) : 0;

    if ($count === 0) {
        themeStatus("VIDEOS STATUS", [
            "Available" => YELLOW . "0" . RESET,
            "Action"    => YELLOW . "No videos available — skipped" . RESET,
        ]);
        return 0;
    }

    themeStatus("VIDEOS STATUS", [
        "Available" => GREEN . $count . RESET,
        "Action"    => GREEN . "Watching up to " . $maxTasks . "..." . RESET,
    ]);

    $done = 0;
    foreach ($ads as $ad) {
        if ($done >= $maxTasks) break;
        list($ok, $msg) = ytOne($apiKey, $hash, $ad);
        if ($ok) {
            $done++;
            $GLOBALS["video_claims"] = ($GLOBALS["video_claims"] ?? 0) + 1;
        }
        sleep(mt_rand(4, 9));
    }
    return $done;
}


function runAccount($parts) {
    $label = accountLabel($parts);
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  👤  Running: " . GREEN . $label . RESET . str_repeat(" ", max(1, 45 - strlen($label))) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;

    list($ok, $cookie, $msg) = loginFromParts($parts);
    if (!$ok) {
        themeFail("Login failed · " . $msg);
        return;
    }
    themeOk("Login Successful");

    $GLOBALS["surf_claims"] = $GLOBALS["surf_claims"] ?? 0;
    $GLOBALS["video_claims"] = $GLOBALS["video_claims"] ?? 0;
    $cycle = 0;

    // LOOP: Surf + Videos only — no faucet — retry after wait when empty
    while (true) {
        $cycle++;
        themeStatus("CYCLE " . $cycle, [
            "Surf total"   => GREEN . $GLOBALS["surf_claims"] . RESET,
            "Videos total" => GREEN . $GLOBALS["video_claims"] . RESET,
            "Mode"         => WHITE . "Surf + Videos · auto retry" . RESET,
        ]);

        // SURFADS
        $GLOBALS["surf_hidden_count"] = 0;
        $items = surfList($cookie);
        $hidden = intval($GLOBALS["surf_hidden_count"] ?? 0);
        $avail = count($items);
        $surfDone = 0;
        $skipped = 0;

        themeStatus("SURFADS STATUS", [
            "Available" => GREEN . $avail . RESET,
            "Hidden"    => YELLOW . $hidden . WHITE . " (already clicked)" . RESET,
            "Action"    => $avail > 0
                ? GREEN . "Claiming visible ads..." . RESET
                : YELLOW . "None available" . RESET,
        ]);

        if ($avail > 0) {
            foreach ($items as $it) {
                if ($surfDone >= 20) break;
                list($ok, $msg) = surfOne($cookie, $it);
                if ($ok) {
                    $surfDone++;
                    $GLOBALS["surf_claims"]++;
                } else {
                    if (stripos($msg, "already clicked") !== false || stripos($msg, "come back tomorrow") !== false) {
                        $skipped++;
                        continue;
                    }
                    if (stripos($msg, "session dead") !== false || stripos($msg, "401") !== false) {
                        themeFail("Session dead — stop");
                        return;
                    }
                }
                sleep(mt_rand(3, 7));
            }
            themeStatus("SURFADS RESULT", [
                "Claimed" => GREEN . $surfDone . RESET,
                "Skipped" => YELLOW . $skipped . RESET,
                "Hidden"  => YELLOW . $hidden . RESET,
            ]);
        }

        // VIDEOS
        $vBefore = $GLOBALS["video_claims"];
        ytRun($cookie, 15);
        $vThis = $GLOBALS["video_claims"] - $vBefore;

        themeStatus("CYCLE DONE", [
            "Cycle"   => WHITE . $cycle . RESET,
            "Surf +"  => GREEN . $surfDone . RESET,
            "Video +" => GREEN . $vThis . RESET,
            "Totals"  => WHITE . "S:" . $GLOBALS["surf_claims"] . " V:" . $GLOBALS["video_claims"] . RESET,
        ]);

        // Wait then retry (few minutes)
        $wait = mt_rand(120, 300); // 2–5 min
        themeStatus("WAITING", [
            "Next run" => YELLOW . gmdate("i\\m s\\s", $wait) . RESET,
            "Hint"     => GREY . "Ctrl+C to stop" . RESET,
        ]);
        sleep($wait);
    }
}


function menu() {
    $count = count(loadAccounts());
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  ₿  BTCADSPACE AUTO BOT" . str_repeat(" ", 36) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  Accounts loaded: " . GREEN . $count . RESET . str_repeat(" ", 40 - strlen((string)$count)) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  1. Run Account (Single)" . str_repeat(" ", 36) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  2. Run All Accounts" . str_repeat(" ", 40) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  3. Add Account (Cookie)" . str_repeat(" ", 36) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  4. Add Account (Username/Password)" . str_repeat(" ", 25) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  5. Edit Account" . str_repeat(" ", 44) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  6. Delete Account" . str_repeat(" ", 42) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  7. View Accounts" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  8. Exit" . str_repeat(" ", 52) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo WHITE . "Choose: " . RESET;
}

if (php_sapi_name() !== 'cli') die("Run from command line: php btcadspace.com.php\n");

while (true) {
    try {
        menu();
        $option = trim(fgets(STDIN));
        if ($option === '') continue;
        switch ($option) {
            case '1':
                $acc = selectAccount();
                if ($acc) {
                    echo "\n" . CYAN . "═══════════════════════════════════════════════════════════════\n" . RESET;
                    echo WHITE . "  Running selected account\n" . RESET;
                    echo CYAN . "═══════════════════════════════════════════════════════════════\n" . RESET;
                    $GLOBALS['faucet_claims'] = 0; $GLOBALS['surf_claims'] = 0;
                    runAccount($acc);
                    echo WHITE . "Press Enter to continue..." . RESET; fgets(STDIN);
                }
                break;
            case '2':
                $accounts = loadAccounts();
                if (empty($accounts)) { echo YELLOW . "No accounts. Add some first!\n" . RESET; break; }
                echo "\n" . PURPLE . "Running ALL " . count($accounts) . " account(s)...\n" . RESET;
                foreach ($accounts as $i => $parts) {
                    echo "\n" . PURPLE . "======== Account " . ($i + 1) . "/" . count($accounts) . " ========\n" . RESET;
                    $GLOBALS['faucet_claims'] = 0; $GLOBALS['surf_claims'] = 0;
                    runAccount($parts); sleep(2);
                }
                echo GREEN . "\nAll accounts finished.\n" . RESET;
                echo WHITE . "Press Enter..." . RESET; fgets(STDIN);
                break;
            case '3':
                echo WHITE . "Paste full Cookie string: " . RESET;
                $cookie = trim(fgets(STDIN));
                if ($cookie) { addAccount([$cookie]); echo GREEN . "Cookie account added!\n" . RESET; }
                else echo RED . "Empty cookie\n" . RESET;
                break;
            case '4':
                echo WHITE . "Username: " . RESET; $user = trim(fgets(STDIN));
                echo WHITE . "Password: " . RESET; $pass = trim(fgets(STDIN));
                if ($user && $pass) { addAccount([$user, $pass]); echo GREEN . "Login account added!\n" . RESET; }
                else echo RED . "Invalid input\n" . RESET;
                break;
            case '5':
                $accounts = loadAccounts();
                if (empty($accounts)) { echo YELLOW . "No accounts to edit.\n" . RESET; break; }
                displayAccounts();
                echo WHITE . "Account number to edit: " . RESET;
                $idx = intval(trim(fgets(STDIN))) - 1;
                if (!isset($accounts[$idx])) { echo RED . "Invalid number\n" . RESET; break; }
                $cur = $accounts[$idx];
                $isLogin = (count($cur) >= 2 && strpos($cur[0], '=') === false);
                if ($isLogin) {
                    echo WHITE . "New Username [" . $cur[0] . "]: " . RESET;
                    $u = trim(fgets(STDIN)); if ($u === '') $u = $cur[0];
                    echo WHITE . "New Password: " . RESET;
                    $p = trim(fgets(STDIN)); if ($p === '') $p = $cur[1] ?? '';
                    editAccountByIndex($idx, [$u, $p]);
                } else {
                    echo WHITE . "New Cookie (Enter to keep): " . RESET;
                    $c = trim(fgets(STDIN)); if ($c === '') $c = $cur[0];
                    editAccountByIndex($idx, [$c]);
                }
                echo GREEN . "Account updated!\n" . RESET;
                break;
            case '6':
                $accounts = loadAccounts();
                if (empty($accounts)) { echo YELLOW . "No accounts to delete.\n" . RESET; break; }
                displayAccounts();
                echo WHITE . "Account number to delete: " . RESET;
                $idx = intval(trim(fgets(STDIN))) - 1;
                if (deleteAccountByIndex($idx)) echo GREEN . "Account deleted!\n" . RESET;
                else echo RED . "Invalid number\n" . RESET;
                break;
            case '7':
                displayAccounts();
                echo WHITE . "Press Enter..." . RESET; fgets(STDIN);
                break;
            case '8':
                echo GREEN . "Goodbye!\n" . RESET; exit(0);
            default:
                echo RED . "Invalid option\n" . RESET; sleep(1);
        }
    } catch (Exception $e) {
        echo RED . "Error: " . $e->getMessage() . "\n" . RESET;
    }
}
