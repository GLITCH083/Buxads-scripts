<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");

// Auto-detect project root (works from scripts/apikey/, scripts/instant/, etc.)
$root = __DIR__;
while ($root !== "/" && !file_exists($root . "/functions/function.php")) {
    $root = dirname($root);
}
define("BASE_DIR", $root);

error_reporting(0);

// Include functions
if (!file_exists(BASE_DIR . "/functions/function.php")) {
    die("ERROR: functions/function.php not found!\n");
}
if (!file_exists(BASE_DIR . "/functions/captcha.php")) {
    die("ERROR: functions/captcha.php not found!\n");
}

include_once BASE_DIR . "/functions/function.php";
include_once BASE_DIR . "/functions/captcha.php";

// Check if color constants are defined
if (!defined("GREEN")) {
    // Fallback colors if not defined
    define("RED", "\033[1;31;40m");
    define("GREEN", "\033[1;32;40m");
    define("YELLOW", "\033[1;33;40m");
    define("BLUE", "\033[1;34;40m");
    define("PURPLE", "\033[1;35;40m");
    define("CYAN", "\033[1;36;40m");
    define("GREY", "\033[1;30;40m");
    define("WHITE", "\033[1;37m");
    define("RESET", "\033[0m");
}

enableCtrlC();

if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

$GLOBALS['faucet_claims'] = 0;
$GLOBALS['faucet_totalclaims'] = 0;
$GLOBALS['shortlink_claims'] = 0;
$GLOBALS['shortlink_totalclaims'] = 0;

function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/bnbpick.io.txt";
}

function loadAccounts() {
    $file = getAccountsFile();
    if (!file_exists($file)) {
        file_put_contents($file, "# Format: email|password|proxy(optional)\n# Example: user@email.com|pass123|proxy:port\n");
        return [];
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $accounts = [];
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;
        
        $parts = explode('|', trim($line));
        if (count($parts) >= 1) {
            $account = [
                'email' => trim($parts[0]),
                'password' => isset($parts[1]) ? trim($parts[1]) : '',
                'proxy' => isset($parts[2]) ? trim($parts[2]) : null
            ];
            $accounts[] = $account;
        }
    }
    
    return $accounts;
}

function saveAccounts($accounts) {
    $file = getAccountsFile();
    $content = "# Format: email|password|proxy(optional)\n# Example: user@email.com|pass123|proxy:port\n";
    
    foreach ($accounts as $acc) {
        $line = $acc['email'];
        if (isset($acc['password']) && $acc['password'] !== '') {
            $line .= '|' . $acc['password'];
        } else {
            $line .= '|'; // Empty password
        }
        if (!empty($acc['proxy'])) {
            $line .= '|' . $acc['proxy'];
        }
        $content .= $line . "\n";
    }
    
    file_put_contents($file, $content);
}

function addAccount($email, $password = '', $proxy = null) {
    $accounts = loadAccounts();
    
    // Check if account already exists
    foreach ($accounts as $acc) {
        if ($acc['email'] === $email) {
            return false; // Account already exists
        }
    }
    
    $accounts[] = [
        'email' => $email,
        'password' => $password,
        'proxy' => $proxy
    ];
    
    saveAccounts($accounts);
    return true;
}

function deleteAccount($email) {
    $accounts = loadAccounts();
    $newAccounts = [];
    $deleted = false;
    
    foreach ($accounts as $acc) {
        if ($acc['email'] !== $email) {
            $newAccounts[] = $acc;
        } else {
            $deleted = true;
        }
    }
    
    if ($deleted) {
        saveAccounts($newAccounts);
    }
    return $deleted;
}

function displayAccounts() {
    $accounts = loadAccounts();
    if (empty($accounts)) {
        echo YELLOW . "No accounts found. Add some first!\n" . RESET;
        return;
    }
    
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  📋  ACCOUNT LIST" . str_repeat(" ", 34) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    
    $i = 1;
    foreach ($accounts as $acc) {
        $proxyStatus = !empty($acc['proxy']) ? GREEN . "✓" . RESET : RED . "✗" . RESET;
        $passStatus = !empty($acc['password']) ? GREEN . "✓" . RESET : YELLOW . "○" . RESET;
        $line = sprintf("║  %2d. %-25s  Pass: %s  Proxy: %s  ║", 
            $i, 
            substr($acc['email'], 0, 25), 
            $passStatus,
            $proxyStatus
        );
        echo WHITE . $line . "\n" . RESET;
        $i++;
    }
    
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

function selectAccount() {
    $accounts = loadAccounts();
    if (empty($accounts)) {
        echo YELLOW . "No accounts available!\n" . RESET;
        return null;
    }
    
    displayAccounts();
    
    echo WHITE . "Select account (1-" . count($accounts) . "): " . RESET;
    $choice = trim(fgets(STDIN));
    
    if (!is_numeric($choice) || $choice < 1 || $choice > count($accounts)) {
        echo RED . "Invalid selection!\n" . RESET;
        return null;
    }
    
    return $accounts[$choice - 1];
}

function menu() {
    clear();
    echo "
";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🚀  bnbpick.io AUTO BOT" . str_repeat(" ", max(1, 40 - strlen("bnbpick.io"))) . CYAN . "║
" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  1. Run Account (Single)" . str_repeat(" ", 36) . CYAN . "║
" . RESET;
    echo CYAN . "║" . WHITE . "  2. Run All Accounts (Smart)" . str_repeat(" ", 32) . CYAN . "║
" . RESET;
    echo CYAN . "║" . WHITE . "  3. Add Account" . str_repeat(" ", 45) . CYAN . "║
" . RESET;
    echo CYAN . "║" . WHITE . "  4. Edit Account" . str_repeat(" ", 44) . CYAN . "║
" . RESET;
    echo CYAN . "║" . WHITE . "  5. Delete Account" . str_repeat(" ", 42) . CYAN . "║
" . RESET;
    echo CYAN . "║" . WHITE . "  6. View Accounts" . str_repeat(" ", 43) . CYAN . "║
" . RESET;
    echo CYAN . "║" . WHITE . "  7. Exit" . str_repeat(" ", 52) . CYAN . "║
" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "
";
    echo WHITE . "Choose an option: " . RESET;
}

function headers(){
    return [
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "accept-language: en-US,en;q=0.9",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
    ];
}

function headerss(){
    return [
        "accept-language: en-US,en;q=0.9",
        "content-type: application/x-www-form-urlencoded",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "origin: https://bnbpick.io",
        "referer: https://bnbpick.io/"
    ];
}


function payload($html, $email, $host, $url, $ua, $password = null, $proxy = null, $faucet = false, $login = false, $csrf = null, $key = null, $captcha_url = null) {
    
    // Check if option exists in HTML
    $option = 0;
    if (strpos($html, '<option value="') !== false) {
        $option = explode('"', explode('<option value="', $html)[1])[0];
    }
    
    $iconToken = '';
    if (strpos($html, "_iconcaptcha-token' value='") !== false) {
        $iconToken = explode("'", explode("<input type='hidden' name='_iconcaptcha-token' value='", $html)[1])[0];
    }
    
    $data = array_fill_keys([
        'g-recaptcha-response', '_iconcaptcha-token', 'ic-rq', 
        'ic-wid', 'ic-cid', 'ic-hp', 'h-captcha-response',
        'c_captcha_response', 'pcaptcha_token', 'cf-turnstile-response'
    ], '');
    
    if ($login) {
        $data['action'] = 'login';
        $data['email'] = $email;
        $data['password'] = $password;
    } elseif ($faucet) {
        $data['action'] = 'claim_hourly_faucet';
        // Only add hash if key is not empty
        if (!empty($key)) {
            $data['hash'] = generate_hash($key);
        }
    }
    
    switch ($option) {
        case 0:
            if (!empty($iconToken)) {
                $iconResponse = solveIconCaptcha($iconToken, $host, $url, $captcha_url, $email, $proxy);
                if (!$iconResponse) return null;
                $data['captcha_type'] = 0;
                $data['_iconcaptcha-token'] = $iconResponse['_iconcaptcha-token'];
                $data['ic-rq'] = $iconResponse['ic-rq'];
                $data['ic-wid'] = $iconResponse['ic-wid'];
                $data['ic-cid'] = $iconResponse['ic-cid'];
                $type = "IconCaptcha";
            } else {
                // No captcha found, try turnstile
                $captchaResult = captcha($url, "0x4AAAAAAA0_O3uScCqtpqXl", "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                    $data['captcha_type'] = 3;
                    $data['c_captcha_response'] = $captchaResult['token'];
                    $type = "TurnStile";
            }
            break;
        
        case 1:
            $captchaResult = captcha($url, "6Le5mIwlAAAAADmaHuGxaf5KTEiWwG9FhQex0JDc", "recaptchav2");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            $data['captcha_type'] = 1;
            $data['g-recaptcha-response'] = $captchaResult['token'];
            $type = "ReCaptchaV2";
            break;
        
        case 2:
            $captchaResult = captcha($url, "1cf1ac2d-e962-4efc-a097-265d3b11ed32", "hcaptcha");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            $data['captcha_type'] = 2;
            $data['h-captcha-response'] = $captchaResult['token'];
            $type = "Hcaptcha";
            break;
        
        case 3:
                $captchaResult = captcha($url, "0x4AAAAAAA0_O3uScCqtpqXl", "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                $data['captcha_type'] = 3;
                $data['c_captcha_response'] = $captchaResult['token'];
                $type = "TurnStile";
            break;
        
        case 4:
            $solve = p_captcha_handler($email, $proxy, $host);
            if (empty($solve) || empty($solve['token'])) return null;
            $data['captcha_type'] = 3;
            $data['pcaptcha_token'] = $solve['token'];
            $csrf = $solve['csrf'];
            $type = "Pcaptcha"; 
            break;
        
        default:
            // No valid option - try turnstile as fallback
            $captchaResult = captcha($url, "0x4AAAAAAA0_O3uScCqtpqXl", "turnstile");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                $data['captcha_type'] = 3;
                $data['c_captcha_response'] = $captchaResult['token'];
            $type = "TurnStile";
            break;
    }
    
    if ($login || $faucet) {
        if (!empty($csrf)) {
            $data['csrf_test_name'] = $csrf;
        }
        if ($login) $data['twofa'] = '';
        if ($faucet) $data['ft'] = null;
    }
    
    if (empty($type)) $type = "Unknown";
    $GLOBALS["last_captcha_type"] = $type;
    // best token for claim box
    foreach (['g-recaptcha-response','h-captcha-response','c_captcha_response','_iconcaptcha-token','pcaptcha_token','cf-turnstile-response','ic-rq'] as $__tk) {
        if (!empty($data[$__tk]) && is_string($data[$__tk]) && strlen($data[$__tk]) > 10) {
            $GLOBALS["last_captcha_token"] = $data[$__tk];
            $GLOBALS["last_captcha_len"] = strlen($data[$__tk]);
            break;
        }
    }
    return ["payload" => $data, "Type" => $type];
}


function login($email, $password, $proxy = null) {
    $host = "bnbpick.io";
    $url = "https://{$host}/login.php";
    $ua = saveData(APP_HOST, 'user-agent');
    $r = Run($url,headers(),null,null,$proxy,2,$email);

    if ($r['info']['http_code'] == 302) {
        return true;
    }
    
    if ($r['info']['http_code'] == 200) {
        $r = Run($url,headers(),null,null,$proxy,2,$email);
        $csrf = '';
        if (!empty($r['header']['set-cookie']) && strpos($r['header']['set-cookie'], 'csrf_cookie_name=') !== false) {
            $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
        }
        if ($csrf === '' && !empty($r['body']) && preg_match('/name=["\']csrf_test_name["\']\s+value=["\']([^"\']+)/', $r['body'], $cm)) {
            $csrf = $cm[1];
        }
        if ($csrf === '') { return false; }
                
        $data = payload($r['body'], $email, $host, $url, $ua, $password, $proxy, false, true, $csrf, null, "https://{$host}/iconcaptcha.php");
        if (!$data) return false;
        
        $payload = http_build_query($data['payload']);
        $r = Run("https://{$host}/process.php",headerss(),$payload,null,$proxy,2,$email);
        $postResponse = $r['body'];
        if ($r['info']['http_code'] == 200) {
            $js = json_decode($postResponse, true);
            return true;
        }
        
        return false;
    }
    
    return false;
}

function faucet($email, $proxy = null) {
    while(true){
        $host = "bnbpick.io";
        $url = "https://{$host}/faucet.php";
        $ua = saveData(APP_HOST, 'user-agent');
        $r =  Run($url,headers(),null,null,$proxy,2,$email);
        
        if ($r['info']['http_code'] == 302) {
            if (function_exists("logFail")) { logFail("SESSION", "Cookie expired — retrying"); }
            else { echo RED . "Cookie expired — retrying
" . RESET; }
            $pass = isset($GLOBALS['__run_password']) ? $GLOBALS['__run_password'] : '';
            if ($pass !== '' && function_exists('login')) {
                if (login($email, $pass, $proxy)) { sleep(2); continue; }
            }
            // NO password / re-login failed → wait and keep trying (do NOT exit single-account loop)
            sleep(20);
            continue;
        }
    
        if ($r['info']['http_code'] == 200) {
            $r =  Run($url,headers(),null,null,$proxy,2,$email);
            $csrf = '';
            if (!empty($r['header']['set-cookie']) && strpos($r['header']['set-cookie'], 'csrf_cookie_name=') !== false) {
                $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
            }
            if ($csrf === '' && !empty($r['body']) && preg_match('/name=["\']csrf_test_name["\']\s+value=["\']([^"\']+)/', $r['body'], $cm)) {
                $csrf = $cm[1];
            }
            if ($csrf === '') { sleep(2); continue; }

            $timer = 0;
            if (preg_match('/show_countdown_clock\((\d+)\)/', $r['body'], $timerMatch)) {
                $timer = intval($timerMatch[1]);
            }
        
            $key = '';
            if (preg_match("/get_hash\(event,'([^']+)'/", $r['body'], $matches)) {
                $key = $matches[1];
            } elseif (preg_match("/const hash = get_hash\(event, '([^']+)'/", $r['body'], $matches)) {
                $key = $matches[1];
            }
        
            if ($timer > 0) {
                countdown($timer,"⏳ Waiting ");
                continue;
            }
        
            $data = payload($r['body'], $email, $host, $url, $ua, null, $proxy, true, false, $csrf, $key, "https://{$host}/iconcaptcha.php");
            if (!$data) { 
                if (function_exists("logWait")) { logWait("Captcha/Payload failed — retry", 10); } else { echo YELLOW . "⚠️  Captcha/Payload failed, retrying in 10s...\n" . RESET; } 
                sleep(10); 
                continue; 
            }

            // ===== DETAILED LOG =====

            // captcha details for ONE claim box
            $captchaType = isset($data['Type']) ? $data['Type'] : 'Unknown';
            $tokenLen = 0;
            $tokenPreview = '';
            $tokenKeys = ['g-recaptcha-response','h-captcha-response','c_captcha_response','_iconcaptcha-token','pcaptcha_token','cf-turnstile-response','rscaptcha_response','ic-rq'];
            if (isset($data['payload']) && is_array($data['payload'])) {
                foreach ($tokenKeys as $tk) {
                    if (!empty($data['payload'][$tk]) && is_string($data['payload'][$tk])) {
                        $tokenLen = strlen($data['payload'][$tk]);
                        $tokenPreview = substr($data['payload'][$tk], 0, 22) . '...';
                        break;
                    }
                }
                if ($tokenLen === 0) {
                    foreach ($data['payload'] as $k => $v) {
                        if (is_string($v) && strlen($v) > $tokenLen && strlen($v) > 20) {
                            $tokenLen = strlen($v);
                            $tokenPreview = substr($v, 0, 22) . '...';
                            if ($captchaType === 'Unknown') $captchaType = (string)$k;
                        }
                    }
                }
            }
            // ========================
            $payload = http_build_query($data['payload']);
            $GLOBALS['faucet_totalclaims']++;
            $r = Run("https://{$host}/process.php",headerss(),$payload,null,$proxy,2,$email);
            $postResponse = $r['body'];
            if ($r['info']['http_code'] == 200) {
                preg_match_all('/\{.*?\}/', $postResponse, $matches);
                $js = null;
                foreach ($matches[0] as $json) {
                    $decoded = json_decode($json, true);
                    if (isset($decoded['ret']) && $decoded['ret'] == 1) {
                        $js = $decoded;
                        break;
                    }
                }
                if ($js) {
                    $GLOBALS['faucet_claims']++;
                    // LIVE claim box
                    if (function_exists("claimBoxOpen")) {
                        claimBoxOpen("✅  CLAIM SUCCESSFUL");
                        $ctype = isset($captchaType) ? $captchaType : (isset($GLOBALS["last_captcha_type"]) ? $GLOBALS["last_captcha_type"] : "");
                        $tlen = isset($tokenLen) ? (int)$tokenLen : (int)($GLOBALS["last_captcha_len"] ?? 0);
                        $tprev = isset($tokenPreview) ? $tokenPreview : "";
                        if ($tprev === "" && !empty($GLOBALS["last_captcha_token"])) {
                            $tprev = substr($GLOBALS["last_captcha_token"], 0, 22) . "...";
                            if ($tlen === 0) $tlen = strlen($GLOBALS["last_captcha_token"]);
                        }
                        if ($ctype && strcasecmp($ctype, "Unknown") !== 0) claimBoxRow("🧩 Captcha", YELLOW . $ctype . RESET);
                        if ($tlen > 0) claimBoxRow("🔑 Token Len", GREEN . $tlen . RESET);
                        if ($tprev !== "") claimBoxRow("🔐 Token", GREY . $tprev . RESET);
                        claimBoxRow("📊 Claim", GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . RESET);
                        claimBoxRow("🎁 Reward", GREEN . "+" . $js['mes'] . RESET);
                        claimBoxRow("💰 Balance", YELLOW . get_balance() . RESET);
                        claimBoxClose();
} else {
                        logClaim($GLOBALS['faucet_claims'], $GLOBALS['faucet_totalclaims'], $js['mes'], get_balance(), null, [
                            "Type" => isset($captchaType) ? $captchaType : "Unknown",
                            "Token Len" => isset($tokenLen) ? $tokenLen : 0,
                            "Token" => isset($tokenPreview) ? $tokenPreview : "",
                        ]);
                    }
                    sleep(2);
                }
            } else {
                // non-200 page — wait and retry
                sleep(5);
            }
        }
    }
}


function generate_hash($key) {
    $x = rand(40, 260);
    $y = rand(100, 380);
    $timestamp = time();
    $raw = "$x:$y:$timestamp";
    $encrypted = '';
    $len = strlen($key);

    for ($i = 0; $i < strlen($raw); $i++) {
        $encrypted .= chr(
            ord($raw[$i]) ^ ord($key[$i % $len])
        );
    }

    return base64_encode($encrypted);
}

function p_captcha_handler($email, $proxy, $host) {
    $r = Run("https://{$host}/generate_pcaptcha.php",headers(),null,null,$proxy,2,$email);
    
    $csrf = '';
    if (!empty($r['header']['set-cookie']) && strpos($r['header']['set-cookie'], 'csrf_cookie_name=') !== false) {
        $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
    }
    if ($csrf === '' && !empty($r['body']) && preg_match('/name=["\']csrf_test_name["\']\s+value=["\']([^"\']+)/', $r['body'], $cm)) {
        $csrf = $cm[1];
    }
    if ($csrf === '') { return null; }
    $r = json_decode($r['body'], true);

    $imageResponse = Run($r['image'],headers(),null,null,$proxy,2,$email);
    $imageData = $imageResponse['body'];
    
    $indices = pcaptcha_solve($imageData, $r['question']);
    
    if (empty($indices)) {
        return p_captcha_handler($email, $proxy, $host);
    }
    
    $selected = json_encode($indices);
    
    $req = "action=verify_pcaptcha&selected_boxes=" . urlencode($selected) . "&csrf_test_name=" . $csrf;
    $r = Run("https://{$host}/process.php",headerss(),$req,null,$proxy,2,$email);
    $csrf = '';
    if (!empty($r['header']['set-cookie']) && strpos($r['header']['set-cookie'], 'csrf_cookie_name=') !== false) {
        $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
    }
    if ($csrf === '' && !empty($r['body']) && preg_match('/name=["\']csrf_test_name["\']\s+value=["\']([^"\']+)/', $r['body'], $cm)) {
        $csrf = $cm[1];
    }
    if ($csrf === '') { return null; }
    $r = json_decode($r['body'], true);
    
    if ($r['message'] == "Success!") {
        return ["token" => $r['token'], "csrf" => $csrf];
    }
    return null;
}

function runAllAccountsSmart() {
    $accounts = loadAccounts();
    if (empty($accounts)) {
        echo YELLOW . "No accounts found. Add some first!\n" . RESET;
        return;
    }

    echo "\n" . CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🔄  SMART MULTI-ACCOUNT ROTATION" . str_repeat(" ", 28) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  Accounts loaded: " . GREEN . count($accounts) . str_repeat(" ", 40) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . GREY . "  Faucet: <30s=5 | >=30s=1  then next account" . str_repeat(" ", 14) . CYAN . "║
" . RESET;
    echo CYAN . "║" . GREY . "  PTC: 2 claims  |  Shortlink: 1  then switch" . str_repeat(" ", 16) . CYAN . "║
" . RESET;
    echo CYAN . "║" . GREY . "  Then switch to next account automatically" . str_repeat(" ", 18) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;

    $idx = 0;
    $totalAccounts = count($accounts);

    while (true) {
        $account = $accounts[$idx % $totalAccounts];
        $email = $account['email'];
        $password = isset($account['password']) ? $account['password'] : '';
        $proxy = isset($account['proxy']) ? $account['proxy'] : null;

        if (function_exists("themeStatus")) {
            themeStatus("🔄  ACCOUNT " . (($idx % $totalAccounts) + 1) . "/" . $totalAccounts, [
                "Email" => GREEN . $email . RESET,
                "Mode"  => WHITE . "Smart rotation" . RESET,
            ]);
        } else {
            echo "\n" . PURPLE . "═══════════════════════════════════════════════════════════════\n" . RESET;
            echo WHITE . "  🔄 Account " . GREEN . (($idx % $totalAccounts) + 1) . WHITE . "/" . $totalAccounts . " → " . GREEN . $email . RESET . "\n";
            echo PURPLE . "═══════════════════════════════════════════════════════════════\n" . RESET;
        }

        $login = login($email, $password, $proxy);
        if (!$login) {
            if (function_exists("themeLogin")) { themeLogin(false, "skipping to next account", ["Account" => isset($email) ? GREEN . $email . RESET : "-"]); } else { echo RED . "✗ Login Failed — skipping\n" . RESET; }
            $idx++;
            sleep(2);
            continue;
        }
        if (function_exists("themeLogin")) { themeLogin(true, "", ["Account" => isset($email) ? GREEN . $email . RESET : "-"]); } else { echo GREEN . "✓ Login Successful\n" . RESET; }

        // Check current timer
        $host = "bnbpick.io";
        $url = "https://{$host}/faucet.php";
        $r = Run($url, headers(), null, null, $proxy, 2, $email);
        $timer = 0;
        if (isset($r['body']) && preg_match('/show_countdown_clock\((\d+)\)/', $r['body'], $m)) {
            $timer = intval($m[1]);
        }

        if ($timer > 30) {
            $maxClaims = 1;
            if (function_exists("themeStatus")) { themeStatus("⏳  TIMER", ["Value" => YELLOW . $timer . "s" . RESET, "Plan" => "1 claim then switch"]); } else { echo YELLOW . "⏳ Timer {$timer}s → 1 claim\n" . RESET; }
        } else {
            $maxClaims = 5;
            if (function_exists("themeStatus")) { themeStatus("⚡  TIMER", ["Value" => GREEN . $timer . "s" . RESET, "Plan" => "up to 5 claims"]); } else { echo GREEN . "⚡ Timer {$timer}s → up to 5 claims\n" . RESET; }
        }

        $done = 0;
        $GLOBALS['faucet_claims'] = 0;
        $GLOBALS['faucet_totalclaims'] = 0;

        // Temporary limited faucet loop
        $start = time();
        while ($done < $maxClaims) {
            // Re-check timer each time
            $r = Run($url, headers(), null, null, $proxy, 2, $email);
            if ($r['info']['http_code'] == 302) {
                if (function_exists("logFail")) { logFail("SESSION", "Cookie expired"); } else { echo RED . "Cookie expired\n" . RESET; }
                break;
            }
            $timer = 0;
            if (preg_match('/show_countdown_clock\((\d+)\)/', $r['body'], $m)) {
                $timer = intval($m[1]);
            }
            if ($timer > 0) {
                if ($timer > 30) {
                    echo YELLOW . "Timer jumped to {$timer}s — switching account\n" . RESET;
                    break;
                }
                countdown($timer, "⏳ Waiting ");
            }

            $csrf = '';
            if (isset($r['header']['set-cookie']) && strpos($r['header']['set-cookie'], 'csrf_cookie_name=') !== false) {
                $csrf = '';
                if (!empty($r['header']['set-cookie']) && strpos($r['header']['set-cookie'], 'csrf_cookie_name=') !== false) {
                    $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
                }
                if ($csrf === '' && !empty($r['body']) && preg_match('/name=["\']csrf_test_name["\']\s+value=["\']([^"\']+)/', $r['body'], $cm)) {
                    $csrf = $cm[1];
                }
                if ($csrf === '') { sleep(2); continue; }
            }
            $key = '';
            if (preg_match("/get_hash\(event,'([^']+)'/", $r['body'], $matches)) {
                $key = $matches[1];
            }

            $ua = saveData(APP_HOST, 'user-agent');
            $data = payload($r['body'], $email, $host, $url, $ua, null, $proxy, true, false, $csrf, $key, "https://{$host}/iconcaptcha.php");
            if (!$data) {
                if (function_exists("logWait")) { logWait("Captcha failed — retry", 5); } else { echo YELLOW . "⚠️ Captcha failed, retrying...\n" . RESET; }
                sleep(5);
                continue;
            }

            // Show captcha info
            $captchaType = isset($data['Type']) ? $data['Type'] : 'Unknown';
            $tokenLen = 0;
            $tokenPreview = '';
            foreach (['g-recaptcha-response','h-captcha-response','c_captcha_response','_iconcaptcha-token','pcaptcha_token','cf-turnstile-response','rscaptcha_response'] as $tk) {
                if (!empty($data['payload'][$tk])) {
                    $tokenLen = strlen($data['payload'][$tk]);
                    $tokenPreview = substr($data['payload'][$tk], 0, 18) . '...';
                    break;
                }
            }
            // captcha details for claim box

            $payload = http_build_query($data['payload']);
            $GLOBALS['faucet_totalclaims']++;
            $r2 = Run("https://{$host}/process.php", headerss(), $payload, null, $proxy, 2, $email);
            if ($r2['info']['http_code'] == 200) {
                preg_match_all('/\{.*?\}/', $r2['body'], $matches);
                $js = null;
                foreach ($matches[0] as $json) {
                    $decoded = json_decode($json, true);
                    if (isset($decoded['ret']) && $decoded['ret'] == 1) {
                        $js = $decoded;
                        break;
                    }
                }
                if ($js) {
                    $GLOBALS['faucet_claims']++;
                    $done++;
                    logClaim($GLOBALS['faucet_claims'], $GLOBALS['faucet_totalclaims'], $js['mes'], get_balance(), null, [
                        "Type" => isset($captchaType) ? $captchaType : "Unknown",
                        "Token Len" => isset($tokenLen) ? $tokenLen : 0,
                        "Token" => isset($tokenPreview) ? $tokenPreview : "",
                    ]);
                }
            }
            sleep(2);
        }

        if (function_exists("themeStatus")) { themeStatus("🔄  NEXT ACCOUNT", ["Action" => CYAN . "Switching..." . RESET]); } else { echo CYAN . "→ Switching to next account...\n" . RESET; }
        $idx++;
        sleep(3);
    }
}



function runAccount($account) {
    $email = $account['email'];
    $password = isset($account['password']) ? $account['password'] : '';
    $proxy = isset($account['proxy']) ? $account['proxy'] : null;
    $GLOBALS['__run_password'] = $password;
    $GLOBALS['faucet_claims'] = 0;
    $GLOBALS['faucet_totalclaims'] = 0;

    if (function_exists("themeAccount")) {
        $ex = [];
        if (empty($password)) $ex["Note"] = YELLOW . "No password set" . RESET;
        themeAccount($email, $proxy ?? null, $ex);
    }

    // ===== SINGLE ACCOUNT LOOP — never returns to menu (Ctrl+C to stop) =====
    while (true) {
        $login = login($email, $password, $proxy);
        if (!$login) {
            if (function_exists("themeLogin")) {
                themeLogin(false, $email, ["Account" => GREEN . $email . RESET, "Next" => YELLOW . "retry in 15s" . RESET]);
            } else {
                echo RED . "Login failed — retry 15s\n" . RESET;
            }
            sleep(15);
            continue;
        }
        if (function_exists("themeLogin")) {
            themeLogin(true, "", ["Account" => GREEN . $email . RESET, "Mode" => WHITE . "Single loop" . RESET]);
        } else {
            echo GREEN . "Login OK\n" . RESET;
        }

        // faucet() is also while(true); if it ever returns, we re-login and continue
        if (function_exists("faucet")) {
            faucet($email, $proxy);
        }
        // also run other earners if present (ptc/shortlink) once per outer cycle
        if (function_exists("ptc")) {
            try { ptc($email, $proxy); } catch (Throwable $e) {}
        }
        if (function_exists("shortlink")) {
            try { shortlink($email, $proxy); } catch (Throwable $e) {}
        }

        if (function_exists("themeStatus")) {
            themeStatus("🔄  NEXT CYCLE", [
                "Account" => GREEN . $email . RESET,
                "Action"  => WHITE . "Re-check in 5s (Ctrl+C to stop)" . RESET,
            ]);
        } else {
            echo CYAN . "🔄 Next cycle in 5s...\n" . RESET;
        }
        sleep(5);
    }
}



// ============================================
// MAIN MENU LOOP
// ============================================
// ============================================
// MAIN MENU LOOP
// ============================================

// Check if running in CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line!\n");
}

// Main loop with error handling
while(true) {
    try {
        menu();
        $option = trim(fgets(STDIN));
        
        // If no input, continue
        if ($option === '') {
            continue;
        }
        
        switch($option) {
            case '1': // Run Account
                $account = selectAccount();
                if ($account) {
                    runAccount($account);
                    // single account loops forever — no Press Enter
                    break;
                }
                break;
                
            
            case '2': // Run All Accounts (Smart)
                runAllAccountsSmart();
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
case '3': // Add Account
                echo WHITE . "Enter email: " . RESET;
                $email = trim(fgets(STDIN));
                if (empty($email)) {
                    echo RED . "Email cannot be empty!\n" . RESET;
                    break;
                }
                echo WHITE . "Enter password (press Enter for empty): " . RESET;
                $password = trim(fgets(STDIN));
                // Password can be empty, so we keep it as is
                
                echo WHITE . "Enter proxy (optional, press Enter to skip): " . RESET;
                $proxy = trim(fgets(STDIN));
                $proxy = !empty($proxy) ? $proxy : null;
                
                if (addAccount($email, $password, $proxy)) {
                    echo GREEN . "✓ Account added successfully!\n" . RESET;
                } else {
                    echo RED . "✗ Account already exists!\n" . RESET;
                }
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
                
            case '4': // Edit Account
                $accounts = loadAccounts();
                if (empty($accounts)) {
                    echo YELLOW . "No accounts to edit.\n" . RESET;
                } else {
                    displayAccounts();
                    echo WHITE . "Enter email to edit: " . RESET;
                    $oldEmail = trim(fgets(STDIN));
                    $found = null;
                    foreach ($accounts as $a) {
                        if ($a['email'] === $oldEmail) { $found = $a; break; }
                    }
                    if (!$found) {
                        echo RED . "Account not found!\n" . RESET;
                    } else {
                        echo WHITE . "New email [" . $found['email'] . "]: " . RESET;
                        $ne = trim(fgets(STDIN));
                        if ($ne === '') $ne = $found['email'];
                        echo WHITE . "New password (Enter keep): " . RESET;
                        $np = trim(fgets(STDIN));
                        if ($np === '') $np = $found['password'] ?? '';
                        echo WHITE . "New proxy (Enter keep/skip): " . RESET;
                        $npr = trim(fgets(STDIN));
                        if ($npr === '') $npr = $found['proxy'] ?? null;
                        else $npr = $npr ?: null;
                        deleteAccount($oldEmail);
                        addAccount($ne, $np, $npr);
                        echo GREEN . "Account updated!\n" . RESET;
                    }
                }
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
                
            case '5': // Delete Account
                $accounts = loadAccounts();
                if (empty($accounts)) {
                    echo YELLOW . "No accounts to delete.\n" . RESET;
                } else {
                    displayAccounts();
                    echo WHITE . "Enter email to delete: " . RESET;
                    $email = trim(fgets(STDIN));
                    if (!empty($email) && deleteAccount($email)) {
                        echo GREEN . "✓ Account deleted successfully!\n" . RESET;
                    } else {
                        echo RED . "✗ Account not found or invalid!\n" . RESET;
                    }
                }
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
                
            case '6': // View Accounts
                displayAccounts();
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
                
            case '7': // Exit
                echo GREEN . "Goodbye!\n" . RESET;
                exit(0);
                break;
                
            default:
                echo RED . "Invalid option! Please try again.\n" . RESET;
                sleep(1);
        }
    } catch (Exception $e) {
        echo RED . "Error: " . $e->getMessage() . "\n" . RESET;
        echo WHITE . "Press Enter to continue..." . RESET;
        fgets(STDIN);
    }
}

exit;