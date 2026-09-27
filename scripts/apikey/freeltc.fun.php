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
    define("GREY", "\033[1;30;40m");
    define("WHITE", "\033[1;37m");
    define("RESET", "\033[0m");
}
enableCtrlC();
if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

define("SITE", "https://freeltc.fun");
define("HOST", "freeltc.fun");
// HAR sitekey on login + faucet pages
define("TS_SITEKEY", "0x4AAAAAADyoxSp30PEmgUy1");

$GLOBALS["faucet_claims"] = 0;
$GLOBALS["faucet_totalclaims"] = 0;
$GLOBALS["ptc_claims"] = 0;


/** Vernuable may return token in "token" OR "request" */
function capToken($cap) {
    if (!is_array($cap)) return "";
    foreach (["token", "request", "gRecaptchaResponse", "response"] as $k) {
        if (!empty($cap[$k]) && is_string($cap[$k]) && strlen($cap[$k]) > 20) {
            // skip error codes
            if (stripos($cap[$k], "ERROR_") === 0) continue;
            if (stripos($cap[$k], "CAPCHA_") === 0) continue;
            return $cap[$k];
        }
    }
    return "";
}

function headers() {
    return [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.9",
    ];
}
function headerss($ref = "") {
    $h = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36",
        "Content-Type: application/x-www-form-urlencoded",
        "Origin: " . SITE,
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.9",
    ];
    if ($ref) $h[] = "Referer: " . $ref;
    return $h;
}

function getAccountsFile() {
    $d = BASE_DIR . "/information";
    if (!is_dir($d)) mkdir($d, 0755, true);
    return $d . "/freeltc.fun.txt";
}
function loadAccounts() {
    $f = getAccountsFile();
    if (!file_exists($f)) file_put_contents($f, "# email|password|proxy\n");
    $out = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === "" || $line[0] === "#") continue;
        $p = explode("|", $line);
        $out[] = ["email" => trim($p[0] ?? ""), "password" => trim($p[1] ?? ""), "proxy" => trim($p[2] ?? "") ?: null];
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

/** HAR: reward is in Swal.fire({ html: '...', text: '...' }) after 303 redirect page */
function extractSwalReward($body) {
    if (!is_string($body) || $body === "") return "";
    if (preg_match_all("/Swal\\.fire\\(\\{([\\s\\S]*?)\\}\\)/", $body, $blocks)) {
        foreach ($blocks[1] as $block) {
            if (stripos($block, "Ad Blocker") !== false) continue;
            if (stripos($block, "Copied!") !== false) continue;
            if (stripos($block, "Login Success") !== false) continue;
            // prefer html
            if (preg_match("/html:\\s*['`]([^'`]{3,250})['`]/", $block, $m)) {
                $t = trim(html_entity_decode(strip_tags($m[1])));
                if ($t !== "" && stripos($t, "Redirecting") === false) return $t;
            }
            if (preg_match("/text:\\s*['\"]([^'\"]{3,250})['\"]/", $block, $m)) {
                $t = trim(html_entity_decode(strip_tags($m[1])));
                if ($t !== "" && stripos($t, "clipboard") === false) return $t;
            }
        }
    }
    // claimpepe-style
    if (preg_match("/text:\\s*'([^']+)'/", $body, $m)) {
        $t = trim($m[1]);
        if ($t && stripos($t, "clipboard") === false && stripos($t, "Ad Blocker") === false) return $t;
    }
    return "";
}

function hidden($body, $name) {
    if (preg_match('/name=["\']' . preg_quote($name, '/') . '["\'][^>]*value=["\']([^"\']*)["\']/i', $body, $m)) return $m[1];
    if (preg_match('/value=["\']([^"\']*)["\'][^>]*name=["\']' . preg_quote($name, '/') . '["\']/i', $body, $m)) return $m[1];
    return "";
}

function sitekey($body) {
    if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $body, $m)) return $m[1];
    return TS_SITEKEY;
}

function deviceToken() {
    static $t = null;
    if ($t) return $t;
    $t = "dev_" . substr(md5(uniqid((string)mt_rand(), true)), 0, 16) . dechex(time());
    return $t;
}

function balance($email, $proxy) {
    $html = Run(SITE . "/dashboard", headers(), null, null, $proxy, 0, $email)["body"] ?? "";
    if (preg_match('/balance-amount[^>]*>\s*([\d.,]+)/i', $html, $m)) return $m[1];
    if (preg_match('/([\d.,]+)\s*<span[^>]*balance-unit/i', $html, $m)) return $m[1];
    if (preg_match('/TOTAL BALANCE[\s\S]{0,120}?([\d.,]+)/i', $html, $m)) return $m[1];
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:Coins?|Tokens?)/i', $html, $m)) return $m[1];
    return "-";
}

function login($email, $password, $proxy) {
    // HAR-accurate login for earnsolana family
    $r = Run(SITE . "/", headers(), null, null, $proxy, 0, $email);
    $body = $r["body"] ?? "";
    if ($body === "" || (int)($r["info"]["http_code"] ?? 0) !== 200) {
        $r = Run(SITE . "/auth/login", headers(), null, null, $proxy, 0, $email);
        $body = $r["body"] ?? "";
    }
    $csrf = hidden($body, "csrf_token_name");
    if ($csrf === "") {
        // sometimes id="token"
        if (preg_match('/id=["\']token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $csrf = $m[1];
        elseif (preg_match('/name=["\']csrf_token_name["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $csrf = $m[1];
    }
    $sk = sitekey($body);
    // ALWAYS turnstile on this family (HAR) — never hcaptcha
    $cap = captcha(SITE . "/", $sk, "turnstile");
    $ts = capToken($cap);
    if ($ts === "") {
        if (function_exists("themeLogin")) {
            themeLogin(false, $email, ["Reason" => "Turnstile token empty", "Next" => "retry"]);
        }
        return false;
    }
    if ($csrf === "") {
        if (function_exists("themeLogin")) {
            themeLogin(false, $email, ["Reason" => "CSRF missing on page", "Next" => "retry"]);
        }
        return false;
    }
    $payload = http_build_query([
        "wallet" => $email,
        "csrf_token_name" => $csrf,
        "device_token" => deviceToken(),
        "cf-turnstile-response" => $ts,
    ]);
    $lr = Run(SITE . "/auth/login", headerss(SITE . "/"), $payload, null, $proxy, 0, $email);
    // follow 303 Location if present
    $loc = SITE . "/dashboard";
    $hdr = $lr["header"] ?? [];
    if (is_array($hdr) && !empty($hdr["location"])) {
        $loc = is_array($hdr["location"]) ? $hdr["location"][0] : $hdr["location"];
    }
    if (!empty($lr["info"]["redirect_url"])) $loc = $lr["info"]["redirect_url"];
    $d = Run($loc, headers(), null, null, $proxy, 0, $email);
    $db = $d["body"] ?? "";
    $du = $d["info"]["url"] ?? "";
    $ok = false;
    if (stripos($db, "logout") !== false) $ok = true;
    if (stripos($du, "dashboard") !== false) $ok = true;
    if (stripos($db, "Earn Coins") !== false || stripos($db, "faucet/earn") !== false) $ok = true;
    if (preg_match('/balance-amount|TOTAL BALANCE|Member Access/i', $db) && stripos($du, "login") === false) $ok = true;
    // Swal Login Success on dashboard (HAR)
    if (stripos($db, "Login Success") !== false) $ok = true;
    if (!$ok && function_exists("themeLogin")) {
        $reason = "not on dashboard";
        if (stripos($db, "invalid") !== false) $reason = "invalid wallet/email";
        if (stripos($db, "captcha") !== false) $reason = "captcha rejected";
        if (stripos($du, "login") !== false) $reason = "still on login page";
        themeLogin(false, $email, ["Reason" => $reason, "URL" => substr($du, 0, 40)]);
    }
    return $ok;
}

function faucet($email, $proxy) {
    // claimpepe-style: forever loop this account
    while (true) {
        $r = Run(SITE . "/faucet/earn", headers(), null, null, $proxy, 0, $email);
        $code = (int)($r["info"]["http_code"] ?? 0);
        if ($code === 503) { sleep(5); continue; }
        if ($code !== 200) { sleep(10); continue; }
        $body = $r["body"] ?? "";

        // REAL timer only (same as claimpepe)
        $timer = 0;
        if (preg_match('/let\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
        elseif (preg_match('/var\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
        elseif (preg_match('/const\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
        if ($timer > 0) {
            countdown($timer, "⏳ Waiting ");
            continue;
        }

        $csrf = hidden($body, "csrf_token_name");
        $token = hidden($body, "token");
        $ticket = hidden($body, "earn_ticket");
        $fp = hidden($body, "fp_hash");
        if ($token === "") {
            // no claim form yet
            sleep(15);
            continue;
        }

        // HAR always sent captcha=turnstile on claim
        $sk = sitekey($body);
        $capType = "Turnstile";
        $capToken = "";
        $cap = captcha(SITE . "/faucet/earn", $sk, "turnstile");
        $capToken = capToken($cap);

        $fields = [
            "csrf_token_name" => $csrf,
            "token" => $token,
            "earn_ticket" => $ticket,
            "fp_hash" => $fp,
            "confirm_wallet" => "",
            "wallet" => $email,
        ];
        if ($capToken !== "") {
            $fields["captcha"] = "turnstile";
            $fields["cf-turnstile-response"] = $capToken;
        }

        $post = Run(SITE . "/faucet/earn", headerss(SITE . "/faucet/earn"), http_build_query($fields), null, $proxy, 0, $email);
        // HAR: 303 then GET same page for Swal message
        $loc = SITE . "/faucet/earn";
        $hdr = $post["header"] ?? [];
        if (is_array($hdr)) {
            if (!empty($hdr["location"])) $loc = is_array($hdr["location"]) ? $hdr["location"][0] : $hdr["location"];
        }
        if (!empty($post["info"]["redirect_url"])) $loc = $post["info"]["redirect_url"];
        $after = Run($loc, headers(), null, null, $proxy, 0, $email);
        $msg = extractSwalReward($after["body"] ?? "");
        if ($msg === "") $msg = extractSwalReward($post["body"] ?? "");
        if ($msg === "") $msg = "Claim posted (no Swal text)";

        $GLOBALS["faucet_totalclaims"]++;
        $GLOBALS["faucet_claims"]++;
        $bal = balance($email, $proxy);

        if (function_exists("claimBoxOpen")) {
            claimBoxOpen("✅  CLAIM SUCCESSFUL");
            claimBoxRow("🧩 Captcha", YELLOW . ($capToken ? $capType : "None") . RESET);
            if ($capToken) {
                claimBoxRow("🔑 Token Len", GREEN . strlen($capToken) . RESET);
                claimBoxRow("🔐 Token", GREY . substr($capToken, 0, 28) . "..." . RESET);
            }
            claimBoxRow("📊 Claim", GREEN . $GLOBALS["faucet_claims"] . WHITE . " / " . YELLOW . $GLOBALS["faucet_totalclaims"] . RESET);
            claimBoxRow("🎁 Reward", GREEN . $msg . RESET);
            claimBoxRow("💰 Balance", YELLOW . $bal . RESET);
            claimBoxClose();
        } else {
            themeOpen("✅  FAUCET CLAIM");
            themeRow("Captcha", $capToken ? ($capType . " Len " . strlen($capToken)) : "None");
            if ($capToken) themeRow("Token", substr($capToken, 0, 28) . "...");
            themeRow("Claim #", $GLOBALS["faucet_claims"] . " / " . $GLOBALS["faucet_totalclaims"]);
            themeRow("Reward", GREEN . $msg . RESET);
            themeRow("Balance", YELLOW . $bal . RESET);
            themeClose();
        }
        sleep(2);
    }
}

function ptc($email, $proxy) {
    $r = Run(SITE . "/ptc", headers(), null, null, $proxy, 0, $email);
    if ((int)($r["info"]["http_code"] ?? 0) !== 200) return;
    preg_match_all('#/ptc/window/(\d+)#', $r["body"] ?? "", $m);
    $ids = array_values(array_unique($m[1] ?? []));
    if (function_exists("themeStatus")) {
        themeStatus("🖱  PTC STATUS", [
            "Available" => (string)count($ids),
            "Action" => count($ids) ? "Claiming..." : "None — skip",
        ]);
    }
    foreach (array_slice($ids, 0, 15) as $id) {
        $w = Run(SITE . "/ptc/window/" . $id, headers(), null, null, $proxy, 0, $email);
        $body = $w["body"] ?? "";
        $wait = 12;
        if (preg_match('/\bcount\s*=\s*(\d+)/', $body, $mm)) $wait = max(5, intval($mm[1]));
        if (preg_match('/(\d+)\s*seconds?/i', $body, $mm)) $wait = max($wait, intval($mm[1]));

        if (function_exists("claimBoxOpen")) {
            claimBoxOpen("PTC CLAIM");
            claimBoxRow("Ad ID", $id);
            claimBoxRow("Watching", $wait . "s...");
        }
        sleep($wait + mt_rand(1, 2));

        $csrf = hidden($body, "csrf_token_name");
        $sk = sitekey($body);
        $cap = captcha(SITE . "/ptc/window/" . $id, $sk, "turnstile");
        $ts = capToken($cap);
        $fields = ["csrf_token_name" => $csrf];
        if ($ts) {
            $fields["captcha"] = "turnstile";
            $fields["cf-turnstile-response"] = $ts;
        }
        $vr = Run(SITE . "/ptc/verify/" . $id, headerss(SITE . "/ptc/window/" . $id), http_build_query($fields), null, $proxy, 0, $email);
        $loc = SITE . "/ptc";
        $hdr = $vr["header"] ?? [];
        if (is_array($hdr) && !empty($hdr["location"])) {
            $loc = is_array($hdr["location"]) ? $hdr["location"][0] : $hdr["location"];
        }
        if (!empty($vr["info"]["redirect_url"])) $loc = $vr["info"]["redirect_url"];
        $after = Run($loc, headers(), null, null, $proxy, 0, $email);
        $msg = extractSwalReward($after["body"] ?? "");
        if ($msg === "") $msg = "PTC completed";
        $GLOBALS["ptc_claims"]++;
        if (function_exists("claimBoxOpen")) {
            if ($ts) {
                claimBoxRow("🧩 Captcha", YELLOW . "Turnstile" . RESET);
                claimBoxRow("🔑 Token Len", GREEN . strlen($ts) . RESET);
            }
            claimBoxRow("🎁 Reward", GREEN . $msg . RESET);
            claimBoxRow("Status", GREEN . "SUCCESS" . RESET);
            claimBoxClose();
        }
        sleep(mt_rand(2, 4));
    }
}

function runAccount($account) {
    $email = $account["email"];
    $password = $account["password"] ?? "";
    $proxy = $account["proxy"] ?? null;
    $GLOBALS["faucet_claims"] = 0;
    $GLOBALS["faucet_totalclaims"] = 0;
    $GLOBALS["ptc_claims"] = 0;
    if (function_exists("themeAccount")) themeAccount($email, $proxy);

    while (true) {
        $ok = login($email, $password, $proxy);
        if (!$ok) {
            if (function_exists("themeLogin")) themeLogin(false, $email, ["Next" => "retry 20s"]);
            sleep(20);
            continue;
        }
        if (function_exists("themeLogin")) {
            themeLogin(true, "", ["Account" => GREEN . $email . RESET, "Mode" => "Single loop"]);
        } elseif (function_exists("claimBoxOpen")) {
            claimBoxOpen("✅  LOGIN");
            claimBoxRow("Account", $email);
            claimBoxRow("Status", GREEN . "Successful" . RESET);
            claimBoxClose();
        }
        // faucet loops forever on timers; PTC runs when faucet is in wait — so alternate:
        // run one faucet cycle is internal; after long wait we also want PTC.
        // Structure like claimpepe: faucet is infinite. Run PTC first then faucet forever.
        ptc($email, $proxy);
        faucet($email, $proxy); // never returns until Ctrl+C
    }
}

function menu() {
    $accounts = loadAccounts();
    while (true) {
        echo "\n" . CYAN . "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║  ◎  FREELTC.FUN BOT                                        ║\n";
        echo "╠═══════════════════════════════════════════════════════════════╣\n";
        echo "║  Accounts: " . str_pad((string)count($accounts), 50) . "║\n";
        echo "╠═══════════════════════════════════════════════════════════════╣\n";
        echo "║  1. Run Account (Single)                                    ║\n";
        echo "║  2. Run All Accounts (Smart)                                ║\n";
        echo "║  3. Add Account                                            ║\n";
        echo "║  4. Delete Account                                         ║\n";
        echo "║  5. View Accounts                                          ║\n";
        echo "║  6. Exit                                                   ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
        echo "Choose an option: ";
        $ch = trim(fgets(STDIN));
        if ($ch === "1") {
            if (!$accounts) { echo YELLOW . "No accounts.\n" . RESET; continue; }
            echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n";
            echo "║  📋  ACCOUNT LIST                                          ║\n";
            echo "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
            foreach ($accounts as $i => $a) echo "║  " . ($i + 1) . ". " . $a["email"] . "\n";
            echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
            echo "Select account (1-" . count($accounts) . "): ";
            $n = intval(trim(fgets(STDIN))) - 1;
            if (isset($accounts[$n])) runAccount($accounts[$n]);
        } elseif ($ch === "2") {
            if (!$accounts) { echo YELLOW . "No accounts.\n" . RESET; continue; }
            while (true) {
                foreach ($accounts as $a) {
                    // one faucet claim + ptc per account then next — soft smart
                    $email = $a["email"]; $proxy = $a["proxy"] ?? null;
                    if (!login($email, $a["password"] ?? "", $proxy)) continue;
                    ptc($email, $proxy);
                    // single claim then switch (smart)
                    $r = Run(SITE . "/faucet/earn", headers(), null, null, $proxy, 0, $email);
                    $body = $r["body"] ?? "";
                    $timer = 0;
                    if (preg_match('/(?:var|let|const)\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
                    if ($timer > 0) continue;
                    $csrf = hidden($body, "csrf_token_name");
                    $token = hidden($body, "token");
                    $ticket = hidden($body, "earn_ticket");
                    $fp = hidden($body, "fp_hash");
                    if ($token === "") continue;
                    $sk = sitekey($body);
                    $cap = captcha(SITE . "/faucet/earn", $sk, "turnstile");
                    $ts = capToken($cap);
                    $fields = ["csrf_token_name" => $csrf, "token" => $token, "earn_ticket" => $ticket, "fp_hash" => $fp, "confirm_wallet" => "", "wallet" => $email];
                    if ($ts) { $fields["captcha"] = "turnstile"; $fields["cf-turnstile-response"] = $ts; }
                    $post = Run(SITE . "/faucet/earn", headerss(SITE . "/faucet/earn"), http_build_query($fields), null, $proxy, 0, $email);
                    $after = Run(SITE . "/faucet/earn", headers(), null, null, $proxy, 0, $email);
                    $msg = extractSwalReward($after["body"] ?? "") ?: "OK";
                    if (function_exists("claimBoxOpen")) {
                        claimBoxOpen("✅  CLAIM SUCCESSFUL");
                        claimBoxRow("Account", $email);
                        claimBoxRow("🎁 Reward", GREEN . $msg . RESET);
                        claimBoxClose();
                    }
                }
                sleep(30);
            }
        } elseif ($ch === "3") {
            echo "Email/Wallet: "; $e = trim(fgets(STDIN));
            echo "Password (blank ok): "; $p = trim(fgets(STDIN));
            echo "Proxy (blank ok): "; $x = trim(fgets(STDIN));
            if ($e) { $accounts[] = ["email" => $e, "password" => $p, "proxy" => $x ?: null]; saveAccounts($accounts); echo GREEN . "Added.\n" . RESET; }
        } elseif ($ch === "4") {
            foreach ($accounts as $i => $a) echo ($i + 1) . ". " . $a["email"] . "\n";
            echo "Delete #: "; $n = intval(trim(fgets(STDIN))) - 1;
            if (isset($accounts[$n])) { array_splice($accounts, $n, 1); saveAccounts($accounts); echo GREEN . "Deleted.\n" . RESET; }
        } elseif ($ch === "5") {
            foreach ($accounts as $i => $a) echo ($i + 1) . ". " . $a["email"] . "\n";
        } elseif ($ch === "6") {
            exit(0);
        }
        $accounts = loadAccounts();
    }
}
menu();
