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
    return $infoDir . "/faucetcrypto.net.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  faucetcrypto.net AUTO BOT" . str_repeat(" ", 20) . CYAN . "║
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
        "origin: https://faucetcrypto.net",
        "referer: https://faucetcrypto.net/"
    ];
}

function login($email,$password,$proxy){
    $r = Run("https://faucetcrypto.net/login",headers(),null,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://faucetcrypto.net/dashboard"){
        return true;
    }

    $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="', $r['body'])[1])[0];
    $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
    $cap = captcha("https://faucetcrypto.net/login", $sitekey, "hcaptcha");
    if(!$cap['token']){
        return false;
    }
    $token = $cap['token'];

    $payload = http_build_query([
        "csrf_token_name" => $csrf,
        "email" => $email,
        "password" => $password,
        "captcha" => "hcaptcha",
        "g-recaptcha-response" => $token,
        "h-captcha-response" => $token
    ]);

    $r = Run("https://faucetcrypto.net/auth/login",headerss(),$payload,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://faucetcrypto.net/dashboard"){
        return true;
    }
}

function balance($email, $proxy) {
    $html = Run("https://faucetcrypto.net/dashboard", headers(), null, null, $proxy, 2, $email)['body'];

    if (preg_match('/<h3[^>]*>(\d+)\s*tokens?<\/h3>/i', $html, $matches)) {
        return $matches[1];
    }

    if (preg_match('/Balance.*?(\d+)/s', $html, $matches)) {
        return $matches[1];
    }

    return 0;
}

function faucet($email,$proxy){
    while(true){
        $r = Run("https://faucetcrypto.net/faucet",headers(),null,null,$proxy,2,$email);

        if(str_contains($r['body'],'You need to claim at least 1 shortlink to claim faucet.')){
            ptc($email,$proxy);
            shortlinks($email,$proxy);
            continue;
        }

        $timer = explode(' - 1;',explode('var wait = ', $r['body'])[1])[0];
        if($timer){countdown($timer,"⏳ Waiting ");continue;}


        $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" id="token" value="', $r['body'])[1])[0];
        $_token = explode('"',explode('<input type="hidden" name="token" value="', $r['body'])[1])[0];
    
        $bot = antibot($r['body']);
        if(!$bot){continue;}
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://freesolana.top/faucet", $sitekey, "hcaptcha");
        if(!$cap['token']){continue;}
        $token = $cap['token'];

        $payload = http_build_query([
            "antibotlinks" => $bot,
            "csrf_token_name" => $csrf,
            "token" => $_token,
            "captcha" => "hcaptcha",
            "g-recaptcha-response" => $token,
            "h-captcha-response" => $token
        ]);

        $r = Run("https://faucetcrypto.net/faucet/verify",headerss(),$payload,null,$proxy,2,$email);
        $r = Run("https://faucetcrypto.net/faucet",headers(),null,null,$proxy,2,$email);

        $msg = explode("', 'success')",explode("Swal.fire('Good job!', '", $r['body'])[1])[0];
        
        if ($msg) {
            $GLOBALS['failed'] = 0;
            $GLOBALS['faucet_claims']++;
            logClaim($GLOBALS['faucet_claims'], $GLOBALS['faucet_totalclaims'], $msg, get_balance(), null, [
                        "Type" => isset($captchaType) ? $captchaType : "Unknown",
                        "Token Len" => isset($tokenLen) ? $tokenLen : 0,
                        "Token" => isset($tokenPreview) ? $tokenPreview : "",
                    ]);
        }else{
            $GLOBALS['failed']++;
            if($GLOBALS['failed'] == 3){
                exit("3 Incorrect Claims\n");
            }
        }
    }
}

function ptc($email,$proxy){
    $r = Run("https://faucetcrypto.net/ptc",headers(),null,null,$proxy,2,$email);
    while(true){
        
        $viewId = explode("'",explode("window.location = 'https://faucetcrypto.net/ptc/view/", $r['body'])[1])[0];
        if(empty($viewId)){return;}

        $r = Run("https://faucetcrypto.net/ptc/view/{$viewId}",headers(),null,null,$proxy,2,$email);
        $timer = explode(';',explode('var timer = ', $r['body'])[1])[0];
        countdown($timer,"⏳ Waiting ");
        $redirect_url = explode("';",explode("var url = '", $r['body'])[1])[0];
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://faucetcrypto.net/ptc/view/{$viewId}", $sitekey, "hcaptcha");
        if(!$cap['token']){
            return ptc($email,$proxy);
        }
        $token = $cap['token'];
        $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="', $r['body'])[1])[0];
        $_token = explode('"',explode('<input type="hidden" name="token" value="', $r['body'])[1])[0];
        $payload = http_build_query([
            "captcha" => "hcaptcha",
            "g-recaptcha-response" => $token,
            "h-captcha-response" => $token,
            "csrf_token_name" => $csrf,
            "token" => $_token
        ]);

        $r = Run("https://faucetcrypto.net/ptc/verify/{$viewId}",headerss(),$payload,null,$proxy,2,$email);
        $r = Run("https://faucetcrypto.net/ptc",headers(),null,null,$proxy,2,$email);

        $msg = explode("', 'success')",explode("Swal.fire('Good job!', '", $r['body'])[1])[0];
        $GLOBALS['ptc_totalclaims']++;
        if ($msg) {
            $GLOBALS['ptc_claims']++;
            logClaim($GLOBALS['ptc_claims'], $GLOBALS['ptc_totalclaims'], $msg, isset($balance) ? $balance : get_balance());
        }
    }
}

function shortlinks($email, $proxy) {
    $r = Run("https://faucetcrypto.net/links", headers(), null, null, $proxy, 2, $email);

    // 🔧 More robust pattern:
    // - <h4> with ANY class containing "card-title"
    // - <a> with ANY attributes, capturing href
    // - <span> with ANY class containing "badge", capturing the numerator
    preg_match_all(
        '/<h4[^>]*class="[^"]*card-title[^"]*"[^>]*>(.*?)<\/h4>.*?' .
        '<a[^>]*href="([^"]+)"[^>]*>.*?' .
        '<span[^>]*class="[^"]*badge[^"]*"[^>]*>(\d+)\/\d+<\/span>/s',
        $r['body'],
        $matches,
        PREG_SET_ORDER
    );

    $items = [];
    foreach ($matches as $match) {
        $items[] = [
            'name'  => trim($match[1]),
            'link'  => $match[2],
            'claim' => $match[3]
        ];
    }

    // If still empty, fallback to DOMDocument for reliability
    if (empty($items)) {
        $items = parseShortlinksWithDOM($r['body']);
    }

    foreach ($items as $detail) {
        $url = $detail['link'];
        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== 'faucetcrypto.net') continue;

        $allowed = ['Shorlinks Wall','Adlink', 'Shrinkme', 'Clk.sh', 'Shrinkearn'];
        $nameOk = false;
        foreach ($allowed as $needle) {
            if (stripos($detail['name'], $needle) !== false) {
                $nameOk = true;
                break;
            }
        }
        if (!$nameOk) continue;

        $r = Run($url, headers(), null, null, $proxy, 0, $email);
        $redirect = $r['info']['redirect_url'];
        $bypass = Bypass($redirect);
        if ($bypass['token']) {
            countdown(rand(45, 60), "");
            $r = Run($bypass['token'], headers(), null, null, $proxy, 0, $email);
            if ($r['info']['redirect_url']) {
                $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 0, $email);
                if ($r['info']['redirect_url']) {
                    $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 0, $email);
                }
            }
        }

        // Extract success message safely
        $msg = '';
        if (strpos($r['body'], "Swal.fire('Good job!', '") !== false) {
            $parts = explode("Swal.fire('Good job!', '", $r['body']);
            if (isset($parts[1])) {
                $msg = explode("', 'success')", $parts[1])[0] ?? '';
            }
        }

        $GLOBALS['shortlink_totalclaims']++;
        if ($msg) {
            $GLOBALS['shortlink_claims']++;
            logClaim($GLOBALS['shortlink_claims'], $GLOBALS['shortlink_totalclaims'], '| ' . WHITE . $redirect . ' | ' . GREEN . $msg, get_balance());
                    // single account = keep claiming this account (NOT 1 claim only)
                    sleep(2);
                    continue;
        }
    }
}

function parseShortlinksWithDOM($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $cards = $xpath->query("//div[contains(@class, 'col-lg-3')]");
    $items = [];

    foreach ($cards as $card) {
        $h4 = $xpath->query(".//h4[contains(@class, 'card-title')]", $card)->item(0);
        $a = $xpath->query(".//a[contains(@class, 'btn')]", $card)->item(0);
        $span = $xpath->query(".//span[contains(@class, 'badge')]", $card)->item(0);

        if ($h4 && $a && $span) {
            $name = trim($h4->textContent);
            $link = $a->getAttribute('href');
            preg_match('/(\d+)\/\d+/', $span->textContent, $match);
            $claim = $match[1] ?? null;
            $items[] = compact('name', 'link', 'claim');
        }
    }
    return $items;
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