<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
// Auto-detect project root
$root = __DIR__;
while ($root !== "/" && !file_exists($root . "/functions/function.php")) {
    $root = dirname($root);
}
define("BASE_DIR", $root);

error_reporting(0);

if (!file_exists(BASE_DIR . "/functions/function.php")) {
    die("ERROR: functions/function.php not found!\n");
}

if (!file_exists(BASE_DIR . "/functions/captcha.php")) {
    die("ERROR: functions/captcha.php not found!\n");
}

include_once BASE_DIR . "/functions/function.php";
include_once BASE_DIR . "/functions/captcha.php";

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
$GLOBALS['ptc_claims'] = 0;
$GLOBALS['ptc_totalclaims'] = 0;

function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/makeyoutask.com.txt";
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


function runAllAccountsSmart() {
    $accounts = loadAccounts();
    if (empty($accounts)) {
        echo YELLOW . "No accounts found. Add some first!\n" . RESET;
        return;
    }
    echo "\n" . CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  SMART MULTI-ACCOUNT ROTATION" . str_repeat(" ", 31) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  Accounts: " . GREEN . count($accounts) . RESET . str_repeat(" ", 47 - strlen((string)count($accounts))) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . GREY . "  Faucet: <30s=5 | >=30s=1 | PTC:2 | SL:1 then next" . str_repeat(" ", 10) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    $idx = 0;
    $total = count($accounts);
    while (true) {
        $acc = $accounts[$idx % $total];
        $email = is_array($acc) ? ($acc['email'] ?? $acc[0] ?? '') : $acc;
        $password = is_array($acc) ? ($acc['password'] ?? $acc[1] ?? '') : '';
        $proxy = is_array($acc) ? ($acc['proxy'] ?? $acc[2] ?? null) : null;
        echo "\n" . PURPLE . "======== Account " . (($idx % $total)+1) . "/$total -> $email ========\n" . RESET;
        // Reuse single-account runner if defined as runAccount / startBot etc.
        if (function_exists('runAccount')) {
            runAccount($acc);
        } elseif (function_exists('login')) {
            $ok = login($email, $password, $proxy);
            if (!$ok) {
                echo RED . "Login failed - next account\n" . RESET;
                $idx++;
                sleep(2);
                continue;
            }
            echo GREEN . "Login OK - running limited claims then switch\n" . RESET;
            // limited: user can Ctrl+C; site-specific loops stay in case 1 for deep logic
            echo YELLOW . "Tip: for full smart faucet/PTC/SL use *pick scripts. Switching account after short cycle.\n" . RESET;
            sleep(3);
        } else {
            echo YELLOW . "No runAccount/login helper - add accounts and use Single for now\n" . RESET;
            break;
        }
        $idx++;
        sleep(2);
    }
}

function menu() {
    clear();
    echo "
";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🚀  makeyoutask.com AUTO BOT" . str_repeat(" ", 21) . CYAN . "║
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

function headerss($host,$x = false){
    $hd = [
        "accept-language: en-US,en;q=0.9",
        "content-type: application/x-www-form-urlencoded",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "origin: https://$host",
        "referer: https://$host/"
    ];
    if($x){
        $hd[] = "x-requested-with: XMLHttpRequest";
    }
    return $hd;
}

function login($email,$password,$proxy){
    $r = Run("https://makeyoutask.com/login",headers(),null,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://makeyoutask.com/dashboard"){
        return true;
    }

    $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="', $r['body'])[1])[0];
    $sitekey = explode('"',explode('sitekey="', $r['body'])[1])[0];
    $cap = captcha("https://makeyoutask.com/login", $sitekey, "turnstile");
    if(!$cap['token']){
        return false;
    }
    $token = $cap['token'];

    $payload = http_build_query(["csrf_token_name" => $csrf,"email" => $email,"password" => $password,"captcha" => "turnstile","cf-turnstile-response" => $token]);

    $r = Run("https://makeyoutask.com/auth/login",headerss("makeyoutask.com"),$payload,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://makeyoutask.com/dashboard"){
        return true;
    }
}

function balance($email, $proxy) {
    $html = Run("https://makeyoutask.com/dashboard", headers(), null, null, $proxy, 2, $email)['body'];

    // Match the <h3> with these classes and capture the number before "Token" or "tokens"
    // Example: match a number inside any element that contains "Balance" or "Token"
    if (preg_match('/<[^>]*class="[^"]*kpi-value[^"]*text-success[^"]*"[^>]*>\s*(\d+)\s*Tokens?/i', $html, $matches)) {
        return $matches[1];
    }

    // Fallback: just find any number near the word "Balance" or in the <h3>
    if (preg_match('/Balance.*?(\d+)/s', $html, $matches)) {
        return $matches[1];
    }

    return 0;
}

function ptc($email, $proxy) {
    while (true) {
        // 1. Get the PTC listing page
        $r = Run("https://makeyoutask.com/ptc/index/youtube", headers(), null, null, $proxy, 2, $email);
        $body = $r['body'] ?? '';

        // Session check — if we got redirected to login, bail out
        if (empty($body) || stripos($body, 'btn-watch-action') === false) {
            // Try one more time in case of a transient error
            $r = Run("https://makeyoutask.com/ptc/index/youtube", headers(), null, null, $proxy, 2, $email);
            $body = $r['body'] ?? '';
        }

        // 2. Extract the first "Watch Video" link — order-independent
        $watchUrl = '';
        if (preg_match_all('/<a\b[^>]*class\s*=\s*["\'][^"\']*btn-watch-action[^"\']*["\'][^>]*>/i', $body, $matches)) {
            foreach ($matches[0] as $tag) {
                if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $tag, $hm)) {
                    $watchUrl = html_entity_decode($hm[1], ENT_QUOTES, 'UTF-8');
                    break;
                }
            }
        }

        // Fallback 1: extract ANY anchor tag containing "btn-watch-action" (attrs in any order)
        if (empty($watchUrl)) {
            if (preg_match_all('/<a\b[^>]*btn-watch-action[^>]*>/i', $body, $matches)) {
                foreach ($matches[0] as $tag) {
                    if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $tag, $hm)) {
                        $watchUrl = html_entity_decode($hm[1], ENT_QUOTES, 'UTF-8');
                        break;
                    }
                }
            }
        }

        // Fallback 2: any /single/ link on the known PTC host
        if (empty($watchUrl)) {
            if (preg_match('#https?://tecnoblogs\.online/single/[^\s"\'<>]+#i', $body, $m)) {
                $watchUrl = html_entity_decode($m[0], ENT_QUOTES, 'UTF-8');
            }
        }

        // Fallback 3: any /single/ link on any domain
        if (empty($watchUrl)) {
            if (preg_match('#https?://[a-z0-9\.\-]+/single/[^\s"\'<>]+#i', $body, $m)) {
                $watchUrl = html_entity_decode($m[0], ENT_QUOTES, 'UTF-8');
            }
        }

        if (empty($watchUrl)) {
            return false;
        }

      
        // 3. Visit the intermediate watch page
        $r = Run($watchUrl, headers(), null, null, $proxy, 2, $email);
        $body = $r['body'] ?? '';
        $currentUrl = $r['info']['url'] ?? $watchUrl;

        // 4. Follow any explicit redirect
        for ($i = 0; $i < 3; $i++) {
            if (empty($r['info']['redirect_url'])) break;
            $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 2, $email);
            $body = $r['body'] ?? '';
            $currentUrl = $r['info']['url'] ?? $currentUrl;
        }

        // 5. Extract form action (relative or absolute)
        $action = '';
        if (preg_match('/<form[^>]*action\s*=\s*["\']([^"\']+)["\']/i', $body, $m)) {
            $action = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (stripos($action, 'http') !== 0) {
                $parsed = parse_url($currentUrl);
                $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                if (strpos($action, '/') === 0) {
                    $action = $base . $action;
                } else {
                    $dir = rtrim(dirname($parsed['path'] ?? '/'), '/');
                    $action = $base . $dir . '/' . $action;
                }
            }
        }
        // Fallback: if no form, try the same URL (many PTC pages POST to themselves)
        if (empty($action)) {
            $action = $currentUrl;
        }

        // 6. Extract CSRF token (try multiple names & both value-before/after-name orders)
        $csrf = '';
        $csrfNames = ['csrf_token_name', 'csrf_token', '_token', 'token'];
        foreach ($csrfNames as $name) {
            // name before value
            if (preg_match('/<input[^>]*name\s*=\s*["\']' . preg_quote($name, '/') . '["\'][^>]*value\s*=\s*["\']([^"\']+)["\']/i', $body, $m)) {
                $csrf = $m[1]; break;
            }
            // value before name
            if (preg_match('/<input[^>]*value\s*=\s*["\']([^"\']+)["\'][^>]*name\s*=\s*["\']' . preg_quote($name, '/') . '["\']/i', $body, $m)) {
                $csrf = $m[1]; break;
            }
        }
        // Also check JS variables
        if ($csrf === '' && preg_match('/csrfHash\s*=\s*[\'"]([^\'"]+)[\'"]/i', $body, $m)) {
            $csrf = $m[1];
        }

        // 7. Extract countdown timer
        $timer = 0;
        foreach ([
            '/(?:const|let|var)\s+countdownTime\s*=\s*(\d+)/i',
            '/countdownTime\s*[=:]\s*(\d+)/i',
            '/(?:const|let|var)\s+seconds\s*=\s*(\d+)/i',
            '/timeLeft\s*[=:]\s*(\d+)/i',
        ] as $p) {
            if (preg_match($p, $body, $m)) { $timer = (int)$m[1]; break; }
        }
        if ($timer > 0) {
            countdown($timer + 1, "⏳ Waiting ");
        }

        // 8. Extract Turnstile sitekey
        $sitekey = '';
        foreach ([
            '/data-sitekey\s*=\s*["\']([^"\']+)["\']/i',
            '/sitekey\s*[=:]\s*["\']([^"\']+)["\']/i',
            '/render\s*[=:]\s*["\']([0-9A-Za-z_-]{10,})["\']/i',
            '/turnstile[^"\']*["\'][^"\']*["\'](0x[0-9A-Za-z_-]+)["\']/i',
        ] as $p) {
            if (preg_match($p, $body, $m)) { $sitekey = $m[1]; break; }
        }

        // 9. Solve Turnstile if present
        $token = '';
        if (!empty($sitekey)) {

            $cap = captcha($currentUrl, $sitekey, "turnstile");
            if (!$cap || empty($cap['token'])) {
                
                return false;
            }
            $token = $cap['token'];
        }

        // 10. Build payload — include all common field names
        $fields = [];
        if ($token !== '') {
            $fields["captcha"]               = "turnstile";
            $fields["cf-turnstile-response"] = $token;
            $fields["g-recaptcha-response"]  = $token;
        }
        if ($csrf !== '') {
            $fields["csrf_token_name"] = $csrf;
            $fields["csrf_token"]      = $csrf;
            $fields["_token"]          = $csrf;
        }

        // 11. Build headers with correct Referer/Origin
        $parsed  = parse_url($action);
        $host    = $parsed['host']  ?? parse_url($currentUrl, PHP_URL_HOST);
        $scheme  = $parsed['scheme'] ?? parse_url($currentUrl, PHP_URL_SCHEME) ?: 'https';

        $h = headers();
        // Remove any existing Referer/Origin/Content-Type then add ours
        $h = array_values(array_filter($h, function($v) {
            return stripos($v, 'referer:') !== 0
                && stripos($v, 'origin:') !== 0
                && stripos($v, 'content-type:') !== 0;
        }));
        $h[] = "Referer: " . $currentUrl;
        $h[] = "Origin: " . $scheme . "://" . $host;
        $h[] = "Content-Type: application/x-www-form-urlencoded";
        $h[] = "X-Requested-With: XMLHttpRequest";

        $r = Run($action, $h, http_build_query($fields), null, $proxy, 2, $email);
        $resp = $r['body'] ?? '';

        // 12. Parse response — support HTML flash, JS popup, and JSON
        $msg = '';
        $redirect_url = '';

        if (preg_match("/html:\s*'([^']+)'/", $resp, $m)) {
            $msg = strip_tags($m[1]);
        }
        if (preg_match('/window\.location\.href\s*=\s*["\']([^"\']+)["\']/', $resp, $m)) {
            $redirect_url = $m[1];
        }

        // JSON response (like autoWatch uses)
        if ($msg === '' && $redirect_url === '') {
            $js = json_decode($resp, true);
            if (is_array($js)) {
                if (!empty($js['message'])) $msg = strip_tags($js['message']);
                if (!empty($js['refresh'])) $redirect_url = 'refresh';
                if (!empty($js['url']))     $redirect_url = $js['url'];
            }
        }

        // Flash-style: "success" / "error" text
        if ($msg === '') {
            if (preg_match('/(?:flash|notification|alert)[^>]*>([^<]+)</i', $resp, $m)) {
                $msg = trim(strip_tags($m[1]));
            }
        }

        // 13. Log result
        $GLOBALS['ptc_totalclaims'] = ($GLOBALS['ptc_totalclaims'] ?? 0) + 1;
        if ($msg !== '' || $redirect_url !== '') {
            $GLOBALS['ptc_claims'] = ($GLOBALS['ptc_claims'] ?? 0) + 1;
            if (function_exists("claimBoxOpen")) {
                claimBoxOpen("✅  PTC CLAIM");
                if (!empty($currentUrl)) claimBoxRow("🔗 URL", CYAN . substr($currentUrl, 0, 45) . RESET);
                $ctype = $GLOBALS["last_captcha_type"] ?? "";
                $tlen = (int)($GLOBALS["last_captcha_len"] ?? 0);
                $tprev = !empty($GLOBALS["last_captcha_token"]) ? substr($GLOBALS["last_captcha_token"], 0, 22) . "..." : "";
                if ($ctype) claimBoxRow("🧩 Captcha", YELLOW . $ctype . RESET);
                if ($tlen > 0) claimBoxRow("🔑 Token Len", GREEN . $tlen . RESET);
                if ($tprev) claimBoxRow("🔐 Token", GREY . $tprev . RESET);
                claimBoxRow("📊 Claim", GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . RESET);
                claimBoxRow("🎁 Reward", GREEN . "+" . $msg . RESET);
                claimBoxRow("💰 Balance", YELLOW . (isset($balance) ? $balance : get_balance()) . RESET);
                claimBoxClose();
            } else {
                logClaim($GLOBALS['ptc_claims'], $GLOBALS['ptc_totalclaims'], $msg, isset($balance) ? $balance : get_balance(), null, [
                    "URL" => isset($currentUrl) ? substr($currentUrl, 0, 40) : "",
                    "Type" => $GLOBALS["last_captcha_type"] ?? "",
                    "Token Len" => $GLOBALS["last_captcha_len"] ?? 0,
                    "Token" => !empty($GLOBALS["last_captcha_token"]) ? substr($GLOBALS["last_captcha_token"], 0, 22) . "..." : "",
                ]);
            }
        }

        // Small cooldown to avoid hammering the endpoint
        sleep(1);
    }
}


function autoWatch($email, $proxy) {
    // 1. Hit the watch entry — follows redirects to the actual watch page
    $r = Run("https://makeyoutask.com/SmmNew/watch", headers(), null, null, $proxy, 2, $email);
    $body = $r['body'] ?? '';
    $currentUrl = $r['info']['url'] ?? '';

    $hops = 0;
    while (empty($body) && !empty($r['info']['redirect_url']) && $hops < 5) {
        $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 2, $email);
        $body = $r['body'] ?? '';
        $currentUrl = $r['info']['url'] ?? $currentUrl;
        $hops++;
    }
    // Also try any further redirect
    for ($i = 0; $i < 3; $i++) {
        if (empty($r['info']['redirect_url'])) break;
        $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 2, $email);
        $body = $r['body'] ?? '';
        $currentUrl = $r['info']['url'] ?? $currentUrl;
    }


    if (empty($body)) {
        return;
    }


    $csrf = '';
    if (preg_match('/csrfHash\s*=\s*[\'"]([^\'"]+)[\'"]/i', $body, $m)) {
        $csrf = $m[1];
    }
    // CSRF field name
    $csrfName = 'csrf_token_name';
    if (preg_match('/csrfName\s*=\s*[\'"]([^\'"]+)[\'"]/i', $body, $m)) {
        $csrfName = $m[1];
    }
    // Required seconds
    $requiredTime = 60;
    if (preg_match('/requiredTime\s*=\s*(\d+)/i', $body, $m)) {
        $requiredTime = (int)$m[1];
    }
    // Ad ID — from the claim URL
    $adId = 0;
    if (preg_match('#/SmmNew/api/claim_watch/(\d+)#i', $body, $m)) {
        $adId = (int)$m[1];
    }
    // Fallback: any numeric id in a data attribute
    if ($adId === 0 && preg_match('/claim_watch\/(\d+)/i', $body, $m)) {
        $adId = (int)$m[1];
    }

    if ($csrf === '' || $adId === 0) {
        return;
    }

    // Build the claim URL from the current host
    $parsed = parse_url($currentUrl);
    $host   = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'tecnoblogs.online');
    $claimUrl = $host . "/SmmNew/api/claim_watch/" . $adId;

    // 4. Claim loop — sleep requiredTime between claims and POST
    while (true) {
        countdown($requiredTime, "⏳ Waiting ");

        $payload = http_build_query([$csrfName => $csrf]);

        // Build headers with proper Referer/Origin
        $h = headers();
        $h = array_values(array_filter($h, function ($v) {
            return stripos($v, 'referer:') !== 0
                && stripos($v, 'origin:') !== 0
                && stripos($v, 'content-type:') !== 0
                && stripos($v, 'x-requested-with:') !== 0;
        }));
        $h[] = "Referer: " . $currentUrl;
        $h[] = "Origin: " . rtrim($host, '/');
        $h[] = "Content-Type: application/x-www-form-urlencoded";
        $h[] = "X-Requested-With: XMLHttpRequest";

        $r = Run($claimUrl, $h, $payload, null, $proxy, 2, $email);
        $resp = $r['body'] ?? '';

        // 5. Parse JSON response
        $js = json_decode($resp, true);
        if (!is_array($js)) {
            return;
        }

        $GLOBALS['ptc_totalclaims'] = ($GLOBALS['ptc_totalclaims'] ?? 0) + 1;

        if (($js['status'] ?? '') === 'success') {
            $GLOBALS['ptc_claims'] = ($GLOBALS['ptc_claims'] ?? 0) + 1;
            $msg = $js['message'] ?? 'Rewarded';
            if (function_exists("claimBoxOpen")) {
                claimBoxOpen("✅  PTC CLAIM");
                if (!empty($currentUrl)) claimBoxRow("🔗 URL", CYAN . substr($currentUrl, 0, 45) . RESET);
                $ctype = $GLOBALS["last_captcha_type"] ?? "";
                $tlen = (int)($GLOBALS["last_captcha_len"] ?? 0);
                $tprev = !empty($GLOBALS["last_captcha_token"]) ? substr($GLOBALS["last_captcha_token"], 0, 22) . "..." : "";
                if ($ctype) claimBoxRow("🧩 Captcha", YELLOW . $ctype . RESET);
                if ($tlen > 0) claimBoxRow("🔑 Token Len", GREEN . $tlen . RESET);
                if ($tprev) claimBoxRow("🔐 Token", GREY . $tprev . RESET);
                claimBoxRow("📊 Claim", GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . RESET);
                claimBoxRow("🎁 Reward", GREEN . "+" . $msg . RESET);
                claimBoxRow("💰 Balance", YELLOW . (isset($balance) ? $balance : get_balance()) . RESET);
                claimBoxClose();
            } else {
                logClaim($GLOBALS['ptc_claims'], $GLOBALS['ptc_totalclaims'], $msg, isset($balance) ? $balance : get_balance(), null, [
                    "URL" => isset($currentUrl) ? substr($currentUrl, 0, 40) : "",
                    "Type" => $GLOBALS["last_captcha_type"] ?? "",
                    "Token Len" => $GLOBALS["last_captcha_len"] ?? 0,
                    "Token" => !empty($GLOBALS["last_captcha_token"]) ? substr($GLOBALS["last_captcha_token"], 0, 22) . "..." : "",
                ]);
            }

            // Refresh the CSRF for next call
            if (!empty($js['csrf_token'])) {
                $csrf = $js['csrf_token'];
            }

            // If server says refresh, jump back to the watch page to get a new video
            if (!empty($js['refresh'])) {
                // try a new video by re-hitting /SmmNew/watch
                return autoWatch($email, $proxy);
            }
            // Otherwise keep claiming on the same video
        } else {
            $msg = $js['message'] ?? 'Unknown error';
            // If token refreshed, keep going
            if (!empty($js['csrf_token'])) {
                $csrf = $js['csrf_token'];
                continue;
            }
            return;
        }
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


if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line!\n");
}

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
                echo YELLOW . "Edit: delete then re-add for now, or use Single scripts.\n" . RESET;
                echo WHITE . "Press Enter..." . RESET;
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