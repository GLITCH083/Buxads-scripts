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
$GLOBALS['ptc_claims'] = 0;
$GLOBALS['ptc_totalclaims'] = 0;
$GLOBALS['shortlink_claims'] = 0;
$GLOBALS['shortlink_totalclaims'] = 0;
$GLOBALS['failed'] = 0;

// ============================================
// ACCOUNT MANAGEMENT FUNCTIONS
// ============================================

function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/adcoins.cc.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  adcoins.cc AUTO BOT" . str_repeat(" ", 26) . CYAN . "║
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

// ============================================
// MAIN BOT FUNCTIONS
// ============================================

function headers(){
    return [
        "accept-language: en-US,en;q=0.9",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
    ];
}

function login($email, $password = '', $proxy = null){
    try {
        $r = Run("https://adcoins.cc/", headers(), null, null, $proxy, 0, $email);

        if(isset($r['info']['redirect_url']) && str_contains($r['info']['redirect_url'], 'dashboard')){ 
            return true; 
        }
        if(!isset($r['info']['http_code']) || $r['info']['http_code'] != 200){ 
            return null; 
        }
        
        $payload = http_build_query([
            "action" => "login",
            "email" => $email,
            "csrf_token" => ""
        ]);
        
        $r = Run("https://adcoins.cc/api.php", headers(), $payload, null, $proxy, 0, $email);

        $js = json_decode($r['body'], true);

        if(isset($js['status']) && $js['status']){
            return true;
        }
        return false;
    } catch (Exception $e) {
        echo RED . "Login error: " . $e->getMessage() . "\n" . RESET;
        return false;
    }
}

function faucet($email, $proxy = null){
    while(true){
        try {
            $r = Run("https://adcoins.cc/faucet", headers(), null, null, $proxy, 0, $email);

            if(!isset($r['info']['http_code']) || $r['info']['http_code'] != 200){ 
                sleep(5);
                continue;
            }

                $timer = explode('"',explode(' data-cooldown="', $r['body'])[1])[0];
                $timer = intval($timer);
                if($timer > 0){
                    MakeyouTaskWatch($email,$proxy);
                    ptc($email,$proxy);
                    countdown($timer, "⏳ Waiting");
                    continue;
                }
            

            // Get captcha
            $image = Run("https://adcoins.cc/captcha/generate.php", headers(), " ", null, $proxy, 0, $email);
          
            $x_n1_token = isset($image['header']['x-n1-token']) ? $image['header']['x-n1-token'] : null;
            if(!$x_n1_token){
                sleep(3);
                continue;
            }
            
            $captcha = upsidedown(base64_encode($image['body']));

            $payload = [
                "answer" => "click",
                "click_x" => $captcha['x'],
                "click_y" => $captcha['y'],
                "token" => $x_n1_token
            ];

               
            $result = Run("https://adcoins.cc/captcha/verify.php", headers(), json_encode($payload), null, $proxy, 0, $email);

            $js = json_decode($result['body'], true);
          
            if(!isset($js['success']) || !$js['success']){
                $GLOBALS['failed']++;
                if($GLOBALS['failed'] == 3){
                    exit("3 Incorrect Claims\n");
                }
                sleep(3);
                continue;
            }
            
            $payload = http_build_query([
                "action" => "claim",
                "n1_token" => $x_n1_token,
                "n1_answer" => "verified"
            ]);

            $result = json_decode(Run("https://adcoins.cc/api.php", headers(), $payload, null, $proxy, 0, $email)['body'], true);
            $GLOBALS['faucet_totalclaims']++;
            
            if(isset($result['success']) && $result['success']){
                $GLOBALS['failed'] = 0;
                $GLOBALS['faucet_claims']++;
                $balance = get_balance($email, $proxy);
                logClaim(
                    $GLOBALS['faucet_claims'],
                    $GLOBALS['faucet_totalclaims'],
                    $result['reward'] . " Coins",
                    $result['new_balance'] . " Coins",
                    $balance . " Token"
                );
            } else {
                $GLOBALS['failed']++;
                if($GLOBALS['failed'] == 3){
                    exit("3 Incorrect Claims\n");
                }
                $msg = isset($result['message']) ? $result['message'] : 'Unknown error';
                echo RED . "[✗] Claim failed: " . $msg . "\n" . RESET;
            }
            
            // Small delay between claims
            sleep(2);
            
        } catch (Exception $e) {
            echo RED . "Error in faucet loop: " . $e->getMessage() . "\n" . RESET;
            sleep(5);
        }
    }
}

function ptc($email, $proxy = null){
    $base    = 'https://adcoins.cc';
    $apiUrl  = $base . '/api.php';
    $siteKey = '0x4AAAAAACyaNDdvQo-05xXY';   // Turnstile sitekey
    $pageUrl = $base . '/ptc';               // pageurl passed to solver

    // ── 1. Fetch PTC listing ─────────────────────────────────────────
    $r = Run($pageUrl, headers(), null, null, $proxy, 2, $email);
    if (empty($r['body'])) {
        echo "[$email] ❌ empty ptc body\n";
        return false;
    }

    $done = 0;

    // ── 2. IFRAME ads ────────────────────────────────────────────────
    // Pattern: <h3>Title</h3> ... <a href="/ptc_ad/ID">
    preg_match_all(
        '/<h3[^>]*>([^<]+)<\/h3>.*?<a\s+href="(\/ptc_ad\/(\d+))"/is',
        $r['body'],
        $iframes,
        PREG_SET_ORDER
    );

    foreach ($iframes as $row) {
        $title = trim($row[1]);
        $path  = $row[2];          // /ptc_ad/472
        $adId  = (int) $row[3];

        // 2a. Visit the viewing page — view_id/duration/ad_url live in its JS
        $viewResp = Run($base . $path, headers(), null, null, $proxy, 2, $email);
        $viewBody = $viewResp['body'] ?? '';

        preg_match('/var\s+viewId\s*=\s*(\d+)/',                 $viewBody, $vm);
        preg_match('/var\s+totalDuration\s*=\s*(\d+)/',           $viewBody, $dm);
        preg_match('/var\s+adUrl\s*=\s*[\'"]([^\'"]+)[\'"]/',     $viewBody, $um);

        $viewId   = $vm[1] ?? null;
        $duration = (int) ($dm[1] ?? 7);
        $adUrl    = $um[1] ?? null;

        if (!$viewId) {
            continue;
        }

        // 2b. Hit the ad URL (simulates iframe load)
        if ($adUrl) {
            Run($adUrl, headers(), null, null, $proxy, 2, $email);
        }

        // 2c. Wait the required duration
        countdown($duration, "");

        // 2d. Solve Turnstile (sitekey is on the page)
        $cap = captcha($pageUrl, $siteKey, 'turnstile')['token'] ?? null;
        if (empty($cap)) {
            continue;
        }

        // 2e. Claim
        $claim = Run(
            $apiUrl,
            headers(['Content-Type' => 'application/x-www-form-urlencoded']),
            http_build_query([
                'action'          => 'claim_ptc_view',
                'view_id'         => $viewId,
                'turnstile_token' => $cap,
            ]),
            null, $proxy, 2, $email
        );

        $cj = json_decode($claim['body'] ?? '', true);
        $GLOBALS['ptc_totalclaims']++;
        if (!empty($cj['success'])) {
            $GLOBALS['ptc_claims']++;
            $balance = get_balance($email, $proxy);
            logClaim($GLOBALS['ptc_claims'], $GLOBALS['ptc_totalclaims'], "IFRAME ad $adId credited (+" . ($cj['reward'] ?? '15') . ")", $balance . " Token", null, [
                        "Type" => isset($captchaType) ? $captchaType : "Unknown",
                        "Token Len" => isset($tokenLen) ? $tokenLen : 0,
                        "Token" => isset($tokenPreview) ? $tokenPreview : "",
                    ]);
                  $done++;
        }

        usleep(500000);
    }

    // ── 3. WINDOW ads ────────────────────────────────────────────────
    preg_match_all(
        '/<button[^>]*data-window-ad="(\d+)"[^>]*'
        . 'data-title="([^"]*)"[^>]*'
        . 'data-url="([^"]*)"[^>]*'
        . 'data-duration="(\d+)"/is',
        $r['body'],
        $windows,
        PREG_SET_ORDER
    );

    foreach ($windows as $row) {
        $adId     = (int) $row[1];
        $title    = html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5);
        $adUrl    = html_entity_decode($row[3], ENT_QUOTES | ENT_HTML5);
        $duration = (int) $row[4];

        // 3a. Create the view → returns view_id + ad_url
        $create = Run(
            $apiUrl,
            headers(['Content-Type' => 'application/x-www-form-urlencoded']),
            http_build_query([
                'action' => 'create_ptc_view',
                'ad_id'  => $adId,
            ]),
            null, $proxy, 2, $email
        );

        $cjson = json_decode($create['body'] ?? '', true);

        if (empty($cjson['success'])) {
            continue;
        }

        $viewId = $cjson['view_id'];
        $adUrl  = $cjson['ad_url'] ?? $adUrl;


        countdown($duration, "");

        // 3d. Solve Turnstile
        $cap = captcha($pageUrl, $siteKey, 'turnstile')['token'] ?? null;
        if (empty($cap)) {
            continue;
        }

        // 3e. Claim
        $claim = Run(
            $apiUrl,
            headers(['Content-Type' => 'application/x-www-form-urlencoded']),
            http_build_query([
                'action'          => 'claim_ptc_view',
                'view_id'         => $viewId,
                'turnstile_token' => $cap,
            ]),
            null, $proxy, 2, $email
        );

        $GLOBALS['ptc_totalclaims']++;
        $cj = json_decode($claim['body'] ?? '', true);
        if (!empty($cj['success'])) {
            $GLOBALS['ptc_claims']++;
            $balance = get_balance($email, $proxy);
            logClaim($GLOBALS['ptc_claims'], $GLOBALS['ptc_totalclaims'], "WINDOW ad $adId credited (+" . ($cj['reward'] ?? '15') . ")", $balance . " Token", null, [
                        "Type" => isset($captchaType) ? $captchaType : "Unknown",
                        "Token Len" => isset($tokenLen) ? $tokenLen : 0,
                        "Token" => isset($tokenPreview) ? $tokenPreview : "",
                    ]);
                  $done++;
            
        }

        usleep(500000);
    }
    return $done;
}


function MakeyouTaskWatch($email, $proxy = null){
    $html = Run("https://adcoins.cc/watch-earn", headers(), null, null, $proxy, 2, $email);

    if (empty($html['body'])) {
        echo "[$email] ❌ empty watch-earn body\n";
        return false;
    }

    // 1. Find watch_ptc links
    preg_match_all(
        '/href\s*=\s*["\'](https?:\/\/makeyoutask\.com\/wall\/watch_ptc\/\d+\?[^"\']+)["\']/i',
        $html['body'],
        $m
    );

    $urls = array_values(array_unique(array_map(
        fn($u) => html_entity_decode($u, ENT_QUOTES | ENT_HTML5),
        $m[1] ?? []
    )));

    if (empty($urls)) {
        echo "[$email] ❌ no watch_ptc links found\n";
        return false;
    }

    $done = 0;

    foreach ($urls as $url) {
        // 2. Hit watch_ptc page → capture redirect to the video page
        $step1    = Run($url, headers(), null, null, $proxy, 2, $email);
        $redirect = $step1['info']['redirect_url'] ?? null;

        if (!$redirect) {
            echo "[$email] ❌ no redirect for $url\n";
            continue;
        }

        // 3. Follow redirect so the video page is loaded (sets session/cookies if needed)
        Run($redirect, headers(), null, null, $proxy, 2, $email);

        // 4. Parse the video URL query string safely
        $parts = parse_url($redirect);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $videoId = $query['video_id']              ?? null;
        $timer   = (int) ($query['timer']          ?? 0);
        $taskId  = (int) ($query['task_id']        ?? 0);
        $userId  = (int) ($query['partner_user_id']?? 0);
        $token   = $query['token']                 ?? null;
        $backend = $query['backend']               ?? null;
        $sitekey = $query['turnstile_site_key']    ?? null;

        if (!$videoId || !$token || !$backend || !$taskId || !$userId) {
            echo "[$email] ❌ missing params in redirect: " . json_encode($query) . "\n";
            continue;
        }

        // 5. Strip trailing slashes from backend (JS does this too)
        $backend = rtrim($backend, '/');

        // 6. POST #1 — start the watch session
        $startResp = Run(
            $backend . '/publisher/start_ptc_external',
            headers(['Content-Type: application/x-www-form-urlencoded']),
            http_build_query([
                'partner_user' => $userId,
                'token'        => $token,
            ]),
            null, $proxy, 2, $email
        );

        $startJson = json_decode($startResp['body'] ?? '', true);

        if (($startJson['status'] ?? '') !== 'success') {
            echo "[$email] ❌ start failed for task $taskId: "
               . ($startJson['message'] ?? 'unknown error') . "\n";
            continue;
        }

        // 7. Wait (backend retry_after overrides URL timer)
        $wait = (int) ($startJson['retry_after'] ?? $timer);
        if ($wait > 0) {
            countdown($wait, "");
        }

        // 8. Build payload — solve Turnstile only if required
        $commonFields = [
            'task_id'      => $taskId,
            'partner_user' => $userId,
            'token'        => $token,
        ];

        if (empty($sitekey)) {
            $payload = http_build_query($commonFields);
        } else {
            $capResp = captcha($redirect, $sitekey, 'turnstile','ptc_complete');
            $cap     = $capResp['token'] ?? null;

            if (empty($cap)) {
                echo "[$email] ⚠ captcha failed for task $taskId — skipping\n";
                continue;
            }

            $payload = http_build_query($commonFields + [
                'cf-turnstile-response' => $cap,
            ]);
        }

        // 9. POST #2 — complete & claim reward
        $doneResp = Run(
            $backend . '/publisher/complete_ptc_external',
            headers(['Content-Type: application/x-www-form-urlencoded']),
            $payload,
            null, $proxy, 2, $email
        );

        $doneJson = json_decode($doneResp['body'] ?? '', true);
        $GLOBALS['ptc_totalclaims']++;
        if (($doneJson['status'] ?? '') === 'success') {
            $GLOBALS['ptc_claims']++;
            
                $balance = get_balance($email, $proxy);
                logClaim($GLOBALS['ptc_claims'], $GLOBALS['ptc_totalclaims'], "task $taskId credited (+{$doneJson['reward']} {$doneJson['unit']})", $balance . " Token", null, [
                        "Type" => isset($captchaType) ? $captchaType : "Unknown",
                        "Token Len" => isset($tokenLen) ? $tokenLen : 0,
                        "Token" => isset($tokenPreview) ? $tokenPreview : "",
                    ]);
            $done++;
        } else {
            echo "[$email] ❌ complete failed for task $taskId: "
               . ($doneJson['message'] ?? 'unknown error') . "\n";
        }

        usleep(500000); // 0.5s between tasks
    }

    return $done;
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