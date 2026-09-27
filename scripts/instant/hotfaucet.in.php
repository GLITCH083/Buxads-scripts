<?php
date_default_timezone_set("Asia/Karachi");
define("APP_HOST", "buxads-Bot");
$root = __DIR__;
while ($root !== "/" && !file_exists($root . "/functions/function.php")) {
    $root = dirname($root);
}
define("BASE_DIR", $root);
error_reporting(0);
if (!file_exists(BASE_DIR . "/functions/function.php")) die("ERROR: functions/function.php not found!\n");
if (!file_exists(BASE_DIR . "/functions/captcha.php")) die("ERROR: functions/captcha.php not found!\n");
include_once BASE_DIR . "/functions/function.php";
include_once BASE_DIR . "/functions/captcha.php";


/** Detect captcha type on page HTML. Returns null if none required. */
function detectCaptchaNeed($body) {
    $body = (string)$body;
    if (preg_match('/cf-turnstile|data-sitekey=["\']0x[A-Za-z0-9_-]+|turnstile/i', $body)
        && preg_match('/data-sitekey=["\']([^"\']+)["\']/', $body, $m)) {
        return ["type" => "turnstile", "sitekey" => $m[1]];
    }
    if (preg_match('/h-captcha|hcaptcha/i', $body) && preg_match('/data-sitekey=["\']([^"\']+)["\']/', $body, $m)) {
        return ["type" => "hcaptcha", "sitekey" => $m[1]];
    }
    if (preg_match('/g-recaptcha|recaptcha/i', $body) && preg_match('/data-sitekey=["\']([^"\']+)["\']/', $body, $m)) {
        return ["type" => "recaptcha", "sitekey" => $m[1]];
    }
    if (preg_match('/iconcaptcha|_iconcaptcha-token|icaptcha/i', $body)) {
        $tok = "";
        if (preg_match('/name=["\']_iconcaptcha-token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $tok = $m[1];
        elseif (preg_match('/_iconcaptcha-token["\']?\s*(?:value=)?["\']([a-f0-9]{40,})["\']/i', $body, $m)) $tok = $m[1];
        return ["type" => "icaptcha", "token" => $tok];
    }
    if (preg_match('/rscaptcha/i', $body)) {
        return ["type" => "rscaptcha"];
    }
    return null; // no captcha required
}

/** Merge captcha fields into payload only when needed. Returns [payload, meta] */
function applyCaptchaIfNeeded($body, $pageUrl, $payload, $email = null, $proxy = null, $host = null) {
    $need = detectCaptchaNeed($body);
    $meta = ["type" => "None", "token" => "", "len" => 0];
    if ($need === null) {
        return [$payload, $meta];
    }
    if ($need["type"] === "turnstile") {
        $cap = captcha($pageUrl, $need["sitekey"], "turnstile");
        if ($cap && !empty($cap["token"])) {
            $payload["captcha"] = "turnstile";
            $payload["cf-turnstile-response"] = $cap["token"];
            $meta = ["type" => "Turnstile", "token" => $cap["token"], "len" => strlen($cap["token"])];
        }
        // if solve fails, still return payload without captcha — caller may retry
        return [$payload, $meta];
    }
    if ($need["type"] === "hcaptcha") {
        $cap = captcha($pageUrl, $need["sitekey"], "hcaptcha");
        if ($cap && !empty($cap["token"])) {
            $payload["captcha"] = "hcaptcha";
            $payload["g-recaptcha-response"] = $cap["token"];
            $payload["h-captcha-response"] = $cap["token"];
            $meta = ["type" => "hCaptcha", "token" => $cap["token"], "len" => strlen($cap["token"])];
        }
        return [$payload, $meta];
    }
    if ($need["type"] === "recaptcha") {
        $cap = captcha($pageUrl, $need["sitekey"], "userrecaptcha");
        if ($cap && !empty($cap["token"])) {
            $payload["captcha"] = "recaptcha";
            $payload["g-recaptcha-response"] = $cap["token"];
            $meta = ["type" => "ReCaptcha", "token" => $cap["token"], "len" => strlen($cap["token"])];
        }
        return [$payload, $meta];
    }
    if ($need["type"] === "icaptcha" && function_exists("solveIconCaptcha") && $host) {
        $iconToken = $need["token"] ?? "";
        if ($iconToken) {
            $solved = solveIconCaptcha($iconToken, $host, $pageUrl, "https://{$host}/icaptcha/req", $email, $proxy);
            if ($solved) {
                $payload = array_merge($payload, $solved);
                $meta = ["type" => "IconCaptcha", "token" => $iconToken, "len" => strlen($iconToken)];
            }
        }
        return [$payload, $meta];
    }
    return [$payload, $meta];
}

if (!defined("GREEN")) {
    define("RED", "\033[1;31;40m"); define("GREEN", "\033[1;32;40m"); define("YELLOW", "\033[1;33;40m");
    define("CYAN", "\033[1;36;40m"); define("GREY", "\033[1;30;40m"); define("WHITE", "\033[1;37m"); define("RESET", "\033[0m");
}
enableCtrlC();
if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");
$GLOBALS['faucet_claims'] = 0;
$GLOBALS['faucet_totalclaims'] = 0;

function getAccountsFile() {
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) mkdir($infoDir, 0755, true);
    return $infoDir . "/hotfaucet.in.txt";
}
function loadAccounts() {
    $file = getAccountsFile();
    if (!file_exists($file)) {
        file_put_contents($file, "# Format: email|password|proxy(optional)\n");
        return [];
    }
    $accounts = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('|', trim($line));
        if (count($parts) >= 1) {
            $accounts[] = ['email' => trim($parts[0]), 'password' => isset($parts[1]) ? trim($parts[1]) : '', 'proxy' => isset($parts[2]) ? trim($parts[2]) : null];
        }
    }
    return $accounts;
}
function saveAccounts($accounts) {
    $content = "# Format: email|password|proxy(optional)\n";
    foreach ($accounts as $acc) {
        $line = $acc['email'] . '|' . ($acc['password'] ?? '');
        if (!empty($acc['proxy'])) $line .= '|' . $acc['proxy'];
        $content .= $line . "\n";
    }
    file_put_contents(getAccountsFile(), $content);
}
function addAccount($email, $password = '', $proxy = null) {
    $accounts = loadAccounts();
    foreach ($accounts as $acc) if ($acc['email'] === $email) return false;
    $accounts[] = ['email' => $email, 'password' => $password, 'proxy' => $proxy];
    saveAccounts($accounts);
    return true;
}
function deleteAccount($index) {
    $accounts = loadAccounts();
    if (!isset($accounts[$index])) return false;
    array_splice($accounts, $index, 1);
    saveAccounts($accounts);
    return true;
}

function showMenu() {
    $accs = loadAccounts();
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🔥  HOTFAUCET.IN BOT" . str_repeat(" ", max(1, 55 - strlen("🔥  HOTFAUCET.IN BOT"))) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  Accounts: " . GREEN . count($accs) . WHITE . str_repeat(" ", 48 - strlen((string)count($accs))) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  1. Run Account (Single)" . str_repeat(" ", 36) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  2. Run All Accounts (Smart)" . str_repeat(" ", 32) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  3. Add Account" . str_repeat(" ", 44) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  4. Delete Account" . str_repeat(" ", 41) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  5. View Accounts" . str_repeat(" ", 42) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  6. Exit" . str_repeat(" ", 51) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
}

function pickAccount() {
    $accounts = loadAccounts();
    if (!$accounts) { echo RED . "No accounts. Add one first.\n" . RESET; return null; }
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  📋  ACCOUNT LIST" . str_repeat(" ", 42) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    foreach ($accounts as $i => $a) {
        $line = "  " . ($i+1) . ". " . $a['email'];
        echo CYAN . "║" . WHITE . $line . str_repeat(" ", max(1, 61 - strlen($line))) . CYAN . "║\n" . RESET;
    }
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo WHITE . "Select account (1-" . count($accounts) . "): " . RESET;
    $n = intval(trim(fgets(STDIN)));
    if ($n < 1 || $n > count($accounts)) return null;
    return $accounts[$n - 1];
}

define("SITE", "https://hotfaucet.in");
define("HOST", "hotfaucet.in");

function headers() {
    return [
        "user-agent: " . saveData(APP_HOST, "user-agent"),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "accept-language: en-US,en;q=0.9",
    ];
}
function headerss() {
    return array_merge(headers(), [
        "content-type: application/x-www-form-urlencoded",
        "origin: " . SITE,
        "referer: " . SITE . "/",
    ]);
}

function extractIconToken($body) {
    if (preg_match('/_iconcaptcha-token["\']?\s*(?:value=)?["\']([a-f0-9]{40,})["\']/i', $body, $m)) return $m[1];
    if (preg_match('/iconcaptcha[_-]?token["\']?\s*[:=]\s*["\']([a-f0-9]{40,})["\']/i', $body, $m)) return $m[1];
    if (preg_match('/name=["\']_iconcaptcha-token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) return $m[1];
    return "";
}

function login($email, $password, $proxy) {
    $r = Run(SITE, headers(), null, null, $proxy, 0, $email);
    if (strpos($r['info']['redirect_url'] ?? '', '/dashboard') !== false) return true;
    if ((int)$r['info']['http_code'] !== 200) return false;
    $body = $r['body'];
    $form_time = time();
    $payload = [
        "wallet" => $email,
        "website" => "",
        "form_time" => $form_time,
        "ci_csrf_token" => "",
    ];
    $iconToken = extractIconToken($body);
    if ($iconToken) {
        $solved = solveIconCaptcha($iconToken, HOST, SITE . "/", SITE . "/icaptcha/req", $email, $proxy);
        if ($solved) $payload = array_merge($payload, $solved);
        // if solve fails, still try without captcha
    }
    $payload = http_build_query($payload);
    $r = Run(SITE . "/auth/login", headerss(), $payload, null, $proxy, 0, $email);
    $r2 = Run(SITE . "/dashboard", headers(), null, null, $proxy, 0, $email);
    return (int)($r2['info']['http_code'] ?? 0) === 200 && strpos($r2['body'], 'logout') !== false || strpos($r2['info']['url'] ?? '', 'dashboard') !== false;
}

function claimBoxOk($type, $token, $msg, $coin = "") {
    $GLOBALS['faucet_claims']++;
    $GLOBALS['faucet_totalclaims']++;
    $len = is_string($token) ? strlen($token) : 0;
    $prev = $len ? substr($token, 0, 22) . "..." : "";
    if (function_exists("claimBoxOpen")) {
        claimBoxOpen("✅  CLAIM SUCCESSFUL");
        claimBoxRow("🧩 Captcha", YELLOW . $type . RESET);
        if ($len) claimBoxRow("🔑 Token Len", GREEN . $len . RESET);
        if ($prev) claimBoxRow("🔐 Token", GREY . $prev . RESET);
        claimBoxRow("📊 Claim", GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . RESET);
        if ($coin) claimBoxRow("🪙 Coin", WHITE . strtoupper($coin) . RESET);
        claimBoxRow("🎁 Reward", GREEN . $msg . RESET);
        claimBoxClose();
    }
}

function faucet($email, $proxy) {
    $coins = ['ltc','btc','doge','trx','bnb','usdt','sol'];
    while (true) {
        foreach ($coins as $coin) {
            $r = Run(SITE . "/faucet/currency/" . $coin, headers(), null, null, $proxy, 0, $email);
            if ((int)$r['info']['http_code'] !== 200) continue;
            $body = $r['body'];

        // Real timer only — do NOT match random "wait"/"second" text on the page
        $waitSec = 0;
        if (preg_match('/(?:var|let|const)\s+wait\s*=\s*(\d+)/', $body, $m)) {
            $waitSec = intval($m[1]);
        } elseif (preg_match('/id=["\'](?:timer|countdown|claim-timer)["\'][^>]*>\s*(\d+)/i', $body, $m)) {
            $waitSec = intval($m[1]);
        } elseif (preg_match('/data-wait=["\'](\d+)["\']/', $body, $m)) {
            $waitSec = intval($m[1]);
        } elseif (preg_match('/(?:next claim in|please wait)\s*(\d+)\s*(?:second|sec)/i', $body, $m)) {
            $waitSec = intval($m[1]);
        }
        $hasToken = (bool)preg_match('/name=["\']token["\'][^>]*value=["\'][^"\']+["\']/i', $body)
            || (bool)preg_match('/name=["\']earn_ticket["\'][^>]*value=["\'][^"\']+["\']/i', $body);
        if ($waitSec > 0 && !$hasToken) {
            countdown($waitSec, "⏳ Waiting ");
            continue;
        }
        if ($waitSec > 5 && $hasToken) {
            countdown($waitSec, "⏳ Waiting ");
            continue;
        }

