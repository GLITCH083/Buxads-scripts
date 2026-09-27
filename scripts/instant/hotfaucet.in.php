<?php
date_default_timezone_set("Asia/Karachi");
define("APP_HOST", "buxads-Bot");
$root = __DIR__;
while ($root !== "/" && !file_exists($root . "/functions/function.php")) {
    $root = dirname($root);
}
define("BASE_DIR", $root);
error_reporting(E_ALL);
ini_set("display_errors", "1");
if (!file_exists(BASE_DIR . "/functions/function.php")) die("ERROR: functions/function.php not found!\n");
if (!file_exists(BASE_DIR . "/functions/captcha.php")) die("ERROR: functions/captcha.php not found!\n");
include_once BASE_DIR . "/functions/function.php";
include_once BASE_DIR . "/functions/captcha.php";
if (!defined("GREEN")) {
    define("RED", "\033[1;31;40m");
    define("GREEN", "\033[1;32;40m");
    define("YELLOW", "\033[1;33;40m");
    define("CYAN", "\033[1;36;40m");
    define("WHITE", "\033[1;37m");
    define("RESET", "\033[0m");
}
enableCtrlC();
if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

define("SITE", "https://hotfaucet.in");
define("HOST", "hotfaucet.in");

function getAccountsFile() {
    $d = BASE_DIR . "/information";
    if (!is_dir($d)) mkdir($d, 0755, true);
    return $d . "/hotfaucet.in.txt";
}
function loadAccounts() {
    $f = getAccountsFile();
    if (!file_exists($f)) { file_put_contents($f, "# email|password|proxy\n"); return []; }
    $out = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), "#") === 0) continue;
        $p = explode("|", trim($line));
        if (count($p) >= 1 && $p[0] !== "") {
            $out[] = ["email" => $p[0], "password" => $p[1] ?? "", "proxy" => $p[2] ?? null];
        }
    }
    return $out;
}
function saveAccounts($accounts) {
    $lines = ["# email|password|proxy"];
    foreach ($accounts as $a) {
        $lines[] = $a["email"] . "|" . ($a["password"] ?? "") . "|" . ($a["proxy"] ?? "");
    }
    file_put_contents(getAccountsFile(), implode("\n", $lines) . "\n");
}
function addAccount($email, $password = "", $proxy = null) {
    $acc = loadAccounts();
    $acc[] = ["email" => $email, "password" => $password, "proxy" => $proxy];
    saveAccounts($acc);
}
function deleteAccount($index) {
    $acc = loadAccounts();
    if (isset($acc[$index])) { array_splice($acc, $index, 1); saveAccounts($acc); return true; }
    return false;
}
function showMenu() {
    $n = count(loadAccounts());
    echo "\n" . CYAN . "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  🔥  HOTFAUCET.IN BOT                                         ║\n";
    echo "╠═══════════════════════════════════════════════════════════════╣\n";
    echo "║  Accounts: " . str_pad((string)$n, 49) . "║\n";
    echo "╠═══════════════════════════════════════════════════════════════╣\n";
    echo "║  1. Run Account (Single)                                    ║\n";
    echo "║  2. Run All Accounts (Smart)                                ║\n";
    echo "║  3. Add Account                                            ║\n";
    echo "║  4. Delete Account                                         ║\n";
    echo "║  5. View Accounts                                          ║\n";
    echo "║  6. Exit                                                   ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
}
function pickAccount() {
    $acc = loadAccounts();
    if (!$acc) { echo RED . "No accounts.\n" . RESET; return null; }
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  📋  ACCOUNT LIST                                          ║\n";
    echo "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    foreach ($acc as $i => $a) {
        echo WHITE . "║  " . ($i + 1) . ". " . $a["email"] . str_repeat(" ", max(1, 50 - strlen($a["email"]))) . "║\n" . RESET;
    }
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "Select account (1-" . count($acc) . "): ";
    $c = trim(fgets(STDIN));
    $idx = intval($c) - 1;
    return isset($acc[$idx]) ? $acc[$idx] : null;
}
function headers() {
    return [
        "user-agent: " . (saveData(APP_HOST, "user-agent") ?: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36"),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "accept-language: en-US,en;q=0.9",
    ];
}
function headerss() {
    return array_merge(headers(), [
        "content-type: application/x-www-form-urlencoded",
        "origin: " . SITE,
        "referer: " . SITE . "/",
        "x-requested-with: XMLHttpRequest",
    ]);
}
function extractIconToken($body) {
    if (preg_match('/name=["\']_iconcaptcha-token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) return $m[1];
    if (preg_match('/_iconcaptcha-token["\']?\s*(?:value=)?["\']([a-f0-9]{40,})["\']/i', $body, $m)) return $m[1];
    return "";
}
function parseReward($body) {
    if (function_exists("parseRewardMsg")) {
        $m = parseRewardMsg($body);
        if ($m) return $m;
    }
    if (preg_match('/Swal\.fire\(\s*\{[^}]*html\s*:\s*[\'"]([^\'"]+)[\'"]/is', $body, $m)) return html_entity_decode(strip_tags($m[1]));
    if (preg_match('/([0-9.]+)\s*(LTC|BTC|DOGE|BNB|BCH|TRX|SOL|ETH)[^.!]{0,40}(?:sent|added|credited)/i', $body, $m)) {
        return $m[0];
    }
    if (preg_match('/has been sent to your[^<!.]{0,60}/i', $body, $m)) return trim($m[0]);
    return "";
}
function login($email, $password, $proxy) {
    $r = Run(SITE . "/", headers(), null, null, $proxy, 0, $email);
    $body = $r["body"] ?? "";
    $csrf = "";
    if (preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $csrf = $m[1];
    elseif (preg_match('/name=["\']_token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $csrf = $m[1];

    $iconToken = extractIconToken($body);
    $capType = "None";
    $capTok = "";
    if ($iconToken && function_exists("solveIconCaptcha")) {
        $solved = solveIconCaptcha($iconToken, HOST, SITE . "/", SITE . "/icaptcha/req", $email, $proxy);
        if (is_array($solved) && !empty($solved["success"])) {
            $capTok = $iconToken;
            $capType = "IconCaptcha";
        }
    }
    $payload = [
        "wallet" => $email,
        "email" => $email,
        "csrf_token" => $csrf,
        "_token" => $csrf,
    ];
    if ($password) $payload["password"] = $password;
    if ($capTok) {
        $payload["_iconcaptcha-token"] = $capTok;
        $payload["captcha"] = "iconcaptcha";
    }
    $r = Run(SITE . "/auth/login", headerss(), $payload, null, $proxy, 0, $email);
    $r2 = Run(SITE . "/dashboard", headers(), null, null, $proxy, 0, $email);
    $ok = (strpos($r2["body"] ?? "", "logout") !== false) || (strpos($r2["url"] ?? "", "dashboard") !== false);
    return $ok;
}
function claimBox($capType, $token, $msg, $coin, $n) {
    $title = (stripos($msg, "fail") !== false || stripos($msg, "invalid") !== false) ? "❌  CLAIM" : "✅  CLAIM SUCCESSFUL";
    if (function_exists("themeOpen")) {
        themeOpen($title);
        themeRow("🧩 Captcha", $capType ?: "-");
        themeRow("🔑 Token Len", $token ? (string)strlen($token) : "0");
        if ($token) themeRow("🔐 Token", substr($token, 0, 28) . "...");
        themeRow("📊 Claim", $n . " / " . $n);
        if ($coin) themeRow("🪙 Coin", strtoupper($coin));
        themeRow("🎁 Reward", $msg ?: "OK");
        if (function_exists("themeClose")) themeClose();
        else echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
    } else {
        echo GREEN . "✔ " . $msg . "\n" . RESET;
    }
}
function faucet($email, $proxy) {
    $coins = ["ltc", "btc", "doge", "bch", "bnb", "trx", "eth", "sol", "usdt"];
    $n = 0;
    $ci = 0;
    $fail = 0;
    while (true) {
        $coin = $coins[$ci % count($coins)];
        $r = Run(SITE . "/faucet/currency/" . $coin, headers(), null, null, $proxy, 0, $email);
        $code = (int)($r["info"]["http_code"] ?? 0);
        $body = $r["body"] ?? "";
        if ($code !== 200) {
            $fail++;
            if ($fail >= 3) { $ci++; $fail = 0; }
            sleep(3);
            continue;
        }
        $waitSec = 0;
        if (preg_match('/(?:var|let|const)\s+wait\s*=\s*(\d+)/', $body, $m)) $waitSec = intval($m[1]);
        elseif (preg_match('/id=["\'](?:timer|countdown)["\'][^>]*>\s*(\d+)/i', $body, $m)) $waitSec = intval($m[1]);
        $hasForm = (bool)preg_match('/name=["\']token["\']/i', $body) || (bool)preg_match('/name=["\']earn_ticket["\']/i', $body);
        if ($waitSec > 120) {
            $ci++;
            $fail = 0;
            sleep(2);
            continue;
        }
        if ($waitSec > 5 && !$hasForm) {
            if (function_exists("countdown")) countdown($waitSec, "⏳ Waiting ");
            else sleep($waitSec);
            continue;
        }
        $csrf = "";
        if (preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $csrf = $m[1];
        $token = "";
        if (preg_match('/name=["\']token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $token = $m[1];
        $ticket = "";
        if (preg_match('/name=["\']earn_ticket["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $ticket = $m[1];

        $iconToken = extractIconToken($body);
        $capType = "Not required";
        $capTok = "";
        if ($iconToken && function_exists("solveIconCaptcha")) {
            $solved = solveIconCaptcha($iconToken, HOST, SITE . "/faucet/currency/" . $coin, SITE . "/icaptcha/req", $email, $proxy);
            if (is_array($solved) && !empty($solved["success"])) {
                $capTok = $iconToken;
                $capType = "IconCaptcha";
            }
        }
        $payload = [
            "token" => $token,
            "earn_ticket" => $ticket,
            "csrf_token" => $csrf,
            "currency" => $coin,
        ];
        if ($capTok) {
            $payload["_iconcaptcha-token"] = $capTok;
            $payload["captcha"] = "iconcaptcha";
        }
        $r = Run(SITE . "/faucet/verify/" . $coin, headerss(), $payload, null, $proxy, 0, $email);
        // follow for Swal reward
        $body2 = $r["body"] ?? "";
        $r3 = Run(SITE . "/faucet/currency/" . $coin, headers(), null, null, $proxy, 0, $email);
        $body3 = $r3["body"] ?? "";
        $msg = parseReward($body2) ?: parseReward($body3);
        if (!$msg) {
            if (preg_match('/success|added|sent|credited/i', $body2 . $body3)) $msg = "Claim successful";
            else $msg = "Claim submitted";
        }
        $n++;
        $fail = 0;
        claimBox($capType, $capTok, $msg, $coin, $n);
        // stay on same coin unless problem
        if (preg_match('/invalid|sufficient|disabled|banned|0\.000000/i', $msg)) {
            $ci++;
        }
        sleep(rand(4, 8));
    }
}
function links($email, $proxy) {
    $r = Run(SITE . "/links", headers(), null, null, $proxy, 0, $email);
    $body = $r["body"] ?? "";
    $ids = [];
    if (preg_match_all('/\/links\/(?:go|view|visit)\/([a-zA-Z0-9_-]+)/', $body, $m)) {
        $ids = array_values(array_unique($m[1]));
    }
    // If 0 or 1 link only → stop (user request)
    if (count($ids) <= 1) {
        if (function_exists("themeOpen")) {
            themeOpen("🔗  LINKS STATUS");
            themeRow("Available", (string)count($ids));
            themeRow("Action", "≤1 link — skipped");
            if (function_exists("themeClose")) themeClose();
            else echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
        }
        return;
    }
    foreach ($ids as $id) {
        $r = Run(SITE . "/links/go/" . $id, headers(), null, null, $proxy, 0, $email);
        sleep(rand(8, 15));
        $r2 = Run(SITE . "/links/verify/" . $id, headerss(), ["id" => $id], null, $proxy, 0, $email);
        $msg = parseReward($r2["body"] ?? "") ?: "Link done";
        claimBox("Links", "", $msg, "", 1);
        sleep(2);
    }
}
function runOne($acc) {
    $email = $acc["email"];
    $pass = $acc["password"] ?? "";
    $proxy = $acc["proxy"] ?? null;
    if (function_exists("themeOpen")) {
        themeOpen("👤  ACCOUNT");
        themeRow("Account", $email);
        if (function_exists("themeClose")) themeClose();
        else echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
    }
    if (!login($email, $pass, $proxy)) {
        if (function_exists("themeOpen")) {
            themeOpen("❌  LOGIN FAILED");
            themeRow("Account", $email);
            themeRow("Status", "Failed — retry 15s");
            if (function_exists("themeClose")) themeClose();
            else echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
        }
        sleep(15);
        return false;
    }
    if (function_exists("themeOpen")) {
        themeOpen("✅  LOGIN");
        themeRow("Account", $email);
        themeRow("Mode", "Single loop");
        themeRow("Status", "Successful");
        if (function_exists("themeClose")) themeClose();
        else echo CYAN . "└" . str_repeat("─", 61) . "┘\n" . RESET;
    }
    while (true) {
        faucet($email, $proxy);
        links($email, $proxy);
        sleep(30);
    }
}
function runAll() {
    $accs = loadAccounts();
    if (!$accs) { echo RED . "No accounts.\n" . RESET; return; }
    foreach ($accs as $a) {
        runOne($a);
    }
}

while (true) {
    showMenu();
    echo "Choose an option: ";
    $c = trim(fgets(STDIN));
    if ($c === "1") {
        $a = pickAccount();
        if ($a) runOne($a);
    } elseif ($c === "2") {
        runAll();
    } elseif ($c === "3") {
        echo "Email/Wallet: "; $e = trim(fgets(STDIN));
        echo "Password (blank ok): "; $p = trim(fgets(STDIN));
        echo "Proxy (blank ok): "; $x = trim(fgets(STDIN));
        if ($e) { addAccount($e, $p, $x ?: null); echo GREEN . "Added.\n" . RESET; }
    } elseif ($c === "4") {
        $a = pickAccount();
        if ($a) {
            $acc = loadAccounts();
            foreach ($acc as $i => $x) {
                if ($x["email"] === $a["email"]) { deleteAccount($i); echo GREEN . "Deleted.\n" . RESET; break; }
            }
        }
    } elseif ($c === "5") {
        foreach (loadAccounts() as $i => $a) echo ($i+1) . ". " . $a["email"] . "\n";
    } elseif ($c === "6" || $c === "0") {
        exit(0);
    }
}
