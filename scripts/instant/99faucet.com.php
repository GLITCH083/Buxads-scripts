<?php
/**
 * 99faucet.com — solid login + faucet (claimpepe-family)
 * Login form (live HTML):
 *   email, captcha=hcaptcha, h-captcha-response, captcha_choosen,
 *   uf, utt, ls
 * Sitekey: e6ca07e2-687c-4fca-b47c-d5fc5ed2e188
 */
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

define("SITE", "https://99faucet.com");
define("HC_SITEKEY", "e6ca07e2-687c-4fca-b47c-d5fc5ed2e188");

$GLOBALS["faucet_claims"] = 0;
$GLOBALS["faucet_totalclaims"] = 0;
$GLOBALS["shortlink_claims"] = 0;
$GLOBALS["shortlink_totalclaims"] = 0;

function getAccountsFile() {
    $d = BASE_DIR . "/information";
    if (!is_dir($d)) mkdir($d, 0755, true);
    return $d . "/99faucet.com.txt";
}
function loadAccounts() {
    $f = getAccountsFile();
    if (!file_exists($f)) {
        file_put_contents($f, "# email|password|proxy\n");
        return [];
    }
    $out = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === "" || $line[0] === "#") continue;
        $p = explode("|", $line);
        $out[] = [
            "email" => trim($p[0] ?? ""),
            "password" => trim($p[1] ?? ""),
            "proxy" => isset($p[2]) && trim($p[2]) !== "" ? trim($p[2]) : null,
        ];
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

function headers() {
    return [
        "user-agent: " . (SaveData(APP_HOST, "UserAgent") ?: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36"),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "accept-language: en-US,en;q=0.9",
    ];
}
function headerss() {
    return [
        "user-agent: " . (SaveData(APP_HOST, "UserAgent") ?: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36"),
        "content-type: application/x-www-form-urlencoded",
        "origin: " . SITE,
        "referer: " . SITE . "/",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "accept-language: en-US,en;q=0.9",
    ];
}

/** Vernuable token may be in token OR request */
function capTok($cap) {
    if (!is_array($cap)) return "";
    foreach (["token", "request", "gRecaptchaResponse", "response"] as $k) {
        if (!empty($cap[$k]) && is_string($cap[$k]) && strlen($cap[$k]) > 30) {
            if (stripos($cap[$k], "ERROR_") === 0) continue;
            return $cap[$k];
        }
    }
    return "";
}

function fingerprint($proxy, $email) {
    $ipData = json_decode(Run("http://ip-api.com/json/?fields=query,timezone", [], null, "data", $proxy, 2, $email)["body"] ?? "{}", true);
    $ip = $ipData["query"] ?? "127.0.0.1";
    $timezone = $ipData["timezone"] ?? "UTC";
    $ls = "en-US,en";
    $userAgent = SaveData(APP_HOST, "UserAgent") ?: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36";
    $utt = $timezone;
    $uf = md5($ip . $userAgent . $ls . $utt);
    return [$uf, $utt, $ls];
}

function login($email, $password, $proxy) {
    // Already logged in?
    $r = Run(SITE . "/", headers(), null, null, $proxy, 0, $email);
    $redir = $r["info"]["redirect_url"] ?? "";
    if (strpos($redir, "dashboard") !== false) return true;
    if ((int)($r["info"]["http_code"] ?? 0) !== 200) return false;

    $body = $r["body"] ?? "";
    $sitekey = HC_SITEKEY;
    if (preg_match('/class="h-captcha"[^>]*data-sitekey="([^"]+)"/', $body, $m)) {
        $sitekey = $m[1];
    } elseif (preg_match('/data-sitekey="([^"]+)"/', $body, $m)) {
        $sitekey = $m[1];
    }

    $cap = captcha(SITE . "/", $sitekey, "hcaptcha");
    $tok = capTok($cap);
    if ($tok === "") {
        if (function_exists("themeLogin")) {
            themeLogin(false, $email, ["Reason" => "hCaptcha token empty"]);
        }
        return false;
    }

    list($uf, $utt, $ls) = fingerprint($proxy, $email);

    // Exact fields from live HTML + claimpepe pattern
    $payload = http_build_query([
        "email" => $email,
        "captcha" => "hcaptcha",
        "captcha_choosen" => "hcaptcha",
        "g-recaptcha-response" => $tok,
        "h-captcha-response" => $tok,
        "uf" => $uf,
        "utt" => $utt,
        "ls" => $ls,
    ]);

    Run(SITE . "/auth/login", headerss(), $payload, null, $proxy, 0, $email);

    // Verify session
    $check = Run(SITE . "/", headers(), null, null, $proxy, 0, $email);
    $redir = $check["info"]["redirect_url"] ?? "";
    if (strpos($redir, "dashboard") !== false) return true;

    $dash = Run(SITE . "/dashboard", headers(), null, null, $proxy, 0, $email);
    $db = $dash["body"] ?? "";
    $du = $dash["info"]["url"] ?? "";
    if (stripos($db, "logout") !== false) return true;
    if (stripos($du, "dashboard") !== false) return true;
    if (stripos($db, "Balance") !== false && stripos($du, "login") === false) return true;

    return false;
}

function extractReward($body) {
    if (!is_string($body) || $body === "") return "";
    // claimpepe style
    if (preg_match("/text:\\s*'([^']+)'/", $body, $m)) {
        $t = trim($m[1]);
        if ($t && stripos($t, "clipboard") === false) return $t;
    }
    if (preg_match('/Swal\\.fire\\(\\{[\\s\\S]*?(?:html|text):\\s*[\'"`]([^\'"`]+)[\'"`]/', $body, $m)) {
        $t = trim(html_entity_decode(strip_tags($m[1])));
        if ($t && stripos($t, "Ad Blocker") === false) return $t;
    }
    if (preg_match('/alert-success[^>]*>([^<]+)/i', $body, $m)) return trim($m[1]);
    return "";
}

function faucet($email, $proxy) {
    // Stay on ONE coin until problem, then shift to next (claimpepe style)
    $coins = ["ltc", "btc", "doge", "trx", "bnb", "usdt", "eth", "sol", "bch", "dgb"];
    while (true) {
        foreach ($coins as $coin) {
            $stuck = 0;
            while (true) {
                $r = Run(SITE . "/faucet/" . $coin, headers(), null, null, $proxy, 0, $email);
                $redir = $r["info"]["redirect_url"] ?? "";
                if (strpos($redir, "/links") !== false || strpos($redir, "short") !== false) {
                    shortlink($email, $proxy, $coin);
                    // after shortlinks, retry same coin
                    continue;
                }
                if ((int)($r["info"]["http_code"] ?? 0) !== 200) {
                    $stuck++;
                    if ($stuck >= 3) break; // shift coin
                    sleep(5);
                    continue;
                }
                $body = $r["body"] ?? "";

                // Real timer only — wait on SAME coin
                $timer = 0;
                if (preg_match('/let\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
                elseif (preg_match('/var\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
                elseif (preg_match('/countdown\s*\(\s*(\d+)\s*\)/', $body, $m)) $timer = intval($m[1]);
                if ($timer > 0) {
                    // long cooldown on this coin → shift to next currency
                    if ($timer > 120) {
                        if (function_exists("themeStatus")) {
                            themeStatus("⏭  SWITCH COIN", [
                                "From" => strtoupper($coin),
                                "Reason" => "cooldown {$timer}s — next currency",
                            ]);
                        }
                        break;
                    }
                    countdown($timer, "⏳ Waiting [" . strtoupper($coin) . "] ");
                    continue;
                }

                $csrf = "";
                $token = "";
                $cur = $coin;
                $rscaptcha_token = "";
                if (preg_match('/name=["\']ci_csrf_token["\'][^>]*value=["\']([^"\']*)["\']/i', $body, $m)) $csrf = $m[1];
                if (preg_match('/name=["\']token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $token = $m[1];
                if (preg_match('/name=["\']currency["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $cur = $m[1];
                if (preg_match('/name=["\']rscaptcha_token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $rscaptcha_token = $m[1];

                if ($token === "") {
                    $stuck++;
                    if ($stuck >= 3) break;
                    sleep(5);
                    continue;
                }
                $stuck = 0;

                $capType = "None";
                $capToken = "";
                $fields = [
                    "ci_csrf_token" => $csrf,
                    "token" => $token,
                    "currency" => $cur,
                ];

                if ($rscaptcha_token !== "" || stripos($body, "rscaptcha") !== false) {
                    $capType = "RSCaptcha";
                    $captcha = function_exists("rs_upsidedown")
                        ? rs_upsidedown($body, "https://rscaptcha.com/assets/generated_captcha/")
                        : [];
                    $rs = is_array($captcha) ? ($captcha["rs-response"] ?? "") : "";
                    if ($rs === "") { sleep(2); continue; }
                    $capToken = $rs;
                    list($uf, $utt, $ls) = fingerprint($proxy, $email);
                    $fields["captcha"] = "rscaptcha";
                    $fields["rscaptcha_token"] = $rscaptcha_token;
                    $fields["rscaptcha_response"] = $rs;
                    $fields["uf"] = $uf;
                    $fields["utt"] = $utt;
                    $fields["ls"] = $ls;
                } elseif (preg_match('/h-captcha|hcaptcha/i', $body)) {
                    $capType = "hCaptcha";
                    $sk = HC_SITEKEY;
                    if (preg_match('/data-sitekey=["\']([^"\']+)["\']/', $body, $m)) $sk = $m[1];
                    $cap = captcha(SITE . "/faucet/" . $coin, $sk, "hcaptcha");
                    $capToken = capTok($cap);
                    if ($capToken === "") { sleep(3); continue; }
                    list($uf, $utt, $ls) = fingerprint($proxy, $email);
                    $fields["captcha"] = "hcaptcha";
                    $fields["g-recaptcha-response"] = $capToken;
                    $fields["h-captcha-response"] = $capToken;
                    $fields["uf"] = $uf;
                    $fields["utt"] = $utt;
                    $fields["ls"] = $ls;
                }

                $post = Run(SITE . "/faucet/verify", headerss(), http_build_query($fields), null, $proxy, 0, $email);
                $msg = extractReward($post["body"] ?? "");
                if ($msg === "") {
                    $again = Run(SITE . "/faucet/" . $coin, headers(), null, null, $proxy, 0, $email);
                    $msg = extractReward($again["body"] ?? "");
                }
                if ($msg === "") $msg = "Claim submitted";

                // PROBLEM → shift to next currency
                $problem = false;
                $low = strtolower($msg);
                if (strpos($low, "sufficient funds") !== false) $problem = true;
                if (strpos($low, "invalid api key") !== false) $problem = true;
                if (strpos($low, "invalid") !== false && strpos($low, "has been sent") === false) $problem = true;
                if (strpos($low, "banned") !== false) $problem = true;
                if (strpos($low, "disabled") !== false) $problem = true;
                if (strpos($low, "not available") !== false) $problem = true;
                if (preg_match('/0\.0+0\s+\w+\s+has been sent/', $low)) $problem = true; // zero payout

                $GLOBALS["faucet_totalclaims"]++;
                $GLOBALS["faucet_claims"]++;

                if (function_exists("claimBoxOpen")) {
                    claimBoxOpen($problem ? "⚠  CLAIM ISSUE" : "✅  CLAIM SUCCESSFUL");
                    claimBoxRow("🧩 Captcha", YELLOW . $capType . RESET);
                    if ($capToken) {
                        claimBoxRow("🔑 Token Len", GREEN . strlen($capToken) . RESET);
                        claimBoxRow("🔐 Token", GREY . substr($capToken, 0, 24) . "..." . RESET);
                    }
                    claimBoxRow("📊 Claim", GREEN . $GLOBALS["faucet_claims"] . WHITE . " / " . YELLOW . $GLOBALS["faucet_totalclaims"] . RESET);
                    claimBoxRow("🪙 Coin", WHITE . strtoupper($coin) . RESET);
                    claimBoxRow("🎁 Reward", ($problem ? YELLOW : GREEN) . $msg . RESET);
                    claimBoxRow("💰 Balance", YELLOW . (function_exists("get_balance") ? get_balance() : "-") . RESET);
                    if ($problem) claimBoxRow("Action", "Switching to next currency");
                    claimBoxClose();
                }

                if ($problem) {
                    break; // next coin
                }
                sleep(2);
                // same coin again
            }
        }
        sleep(5); // all coins done once → short pause then restart from first coin
    }
}


function shortlink($email, $proxy, $coin = "ltc") {
    $r = Run(SITE . "/links", headers(), null, null, $proxy, 0, $email);
    if ((int)($r["info"]["http_code"] ?? 0) !== 200) return;
    preg_match_all('#href=["\']([^"\']*(?:go|link|short)[^"\']*)["\']#i', $r["body"] ?? "", $m);
    $urls = array_unique($m[1] ?? []);
    $n = 0;
    foreach (array_slice($urls, 0, 5) as $u) {
        if (strpos($u, "http") !== 0) $u = SITE . $u;
        Run($u, headers(), null, null, $proxy, 0, $email);
        $n++;
        $GLOBALS["shortlink_claims"]++;
        $GLOBALS["shortlink_totalclaims"]++;
        if (function_exists("claimBoxOpen")) {
            claimBoxOpen("✅  SHORTLINK");
            claimBoxRow("Coin", strtoupper($coin));
            claimBoxRow("Link", substr($u, 0, 40) . "...");
            claimBoxRow("Status", GREEN . "Opened" . RESET);
            claimBoxClose();
        }
        sleep(mt_rand(3, 6));
    }
}

function runAccount($account) {
    $email = $account["email"];
    $password = $account["password"] ?? "";
    $proxy = $account["proxy"] ?? null;
    $GLOBALS["faucet_claims"] = 0;
    $GLOBALS["faucet_totalclaims"] = 0;

    if (function_exists("themeAccount")) {
        themeAccount($email, $proxy);
    }

    while (true) {
        $ok = login($email, $password, $proxy);
        if (!$ok) {
            if (function_exists("themeLogin")) {
                themeLogin(false, $email, ["Next" => "retry 15s"]);
            } else {
                echo RED . "Login failed — retry 15s\n" . RESET;
            }
            sleep(15);
            continue;
        }
        if (function_exists("themeLogin")) {
            themeLogin(true, "", ["Account" => GREEN . $email . RESET, "Mode" => "Single loop"]);
        }
        faucet($email, $proxy); // infinite until Ctrl+C
    }
}

function runAllSmart($accounts) {
    while (true) {
        foreach ($accounts as $a) {
            $email = $a["email"];
            $proxy = $a["proxy"] ?? null;
            if (!login($email, $a["password"] ?? "", $proxy)) continue;
            // 1 claim per coin max then switch
            foreach (["ltc", "doge", "trx"] as $coin) {
                $r = Run(SITE . "/faucet/" . $coin, headers(), null, null, $proxy, 0, $email);
                $body = $r["body"] ?? "";
                $timer = 0;
                if (preg_match('/(?:var|let)\s+wait\s*=\s*(\d+)/', $body, $m)) $timer = intval($m[1]);
                if ($timer > 0) continue;
                $token = "";
                if (preg_match('/name=["\']token["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $m)) $token = $m[1];
                if ($token === "") continue;
                // try one claim
                $csrf = "";
                if (preg_match('/name=["\']ci_csrf_token["\'][^>]*value=["\']([^"\']*)["\']/i', $body, $m)) $csrf = $m[1];
                $sk = HC_SITEKEY;
                $cap = captcha(SITE . "/faucet/" . $coin, $sk, "hcaptcha");
                $tok = capTok($cap);
                if ($tok === "") continue;
                list($uf, $utt, $ls) = fingerprint($proxy, $email);
                $fields = [
                    "ci_csrf_token" => $csrf,
                    "token" => $token,
                    "currency" => $coin,
                    "captcha" => "hcaptcha",
                    "g-recaptcha-response" => $tok,
                    "h-captcha-response" => $tok,
                    "uf" => $uf,
                    "utt" => $utt,
                    "ls" => $ls,
                ];
                $post = Run(SITE . "/faucet/verify", headerss(), http_build_query($fields), null, $proxy, 0, $email);
                $msg = extractReward($post["body"] ?? "") ?: "OK";
                if (function_exists("claimBoxOpen")) {
                    claimBoxOpen("✅  CLAIM SUCCESSFUL");
                    claimBoxRow("Account", $email);
                    claimBoxRow("Coin", strtoupper($coin));
                    claimBoxRow("🎁 Reward", GREEN . $msg . RESET);
                    claimBoxClose();
                }
                break; // one claim then next account
            }
        }
        sleep(20);
    }
}

function menu() {
    $accounts = loadAccounts();
    while (true) {
        echo "\n" . CYAN . "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║  💎  99FAUCET.COM BOT                                         ║\n";
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
            foreach ($accounts as $i => $a) echo ($i + 1) . ". " . $a["email"] . "\n";
            echo "Select account (1-" . count($accounts) . "): ";
            $n = intval(trim(fgets(STDIN))) - 1;
            if (isset($accounts[$n])) runAccount($accounts[$n]);
        } elseif ($ch === "2") {
            if (!$accounts) { echo YELLOW . "No accounts.\n" . RESET; continue; }
            runAllSmart($accounts);
        } elseif ($ch === "3") {
            echo "Email (FaucetPay): "; $e = trim(fgets(STDIN));
            echo "Password (blank ok): "; $p = trim(fgets(STDIN));
            echo "Proxy (blank ok): "; $x = trim(fgets(STDIN));
            if ($e) {
                $accounts[] = ["email" => $e, "password" => $p, "proxy" => $x ?: null];
                saveAccounts($accounts);
                echo GREEN . "Added.\n" . RESET;
            }
        } elseif ($ch === "4") {
            foreach ($accounts as $i => $a) echo ($i + 1) . ". " . $a["email"] . "\n";
            echo "Delete #: ";
            $n = intval(trim(fgets(STDIN))) - 1;
            if (isset($accounts[$n])) {
                array_splice($accounts, $n, 1);
                saveAccounts($accounts);
                echo GREEN . "Deleted.\n" . RESET;
            }
        } elseif ($ch === "5") {
            foreach ($accounts as $i => $a) echo ($i + 1) . ". " . $a["email"] . "\n";
        } elseif ($ch === "6") {
            exit(0);
        }
        $accounts = loadAccounts();
    }
}
menu();
