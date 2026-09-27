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


function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/kiddyearner.com.txt";
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
        echo YELLOW . "No accounts found!\n" . RESET;
        return;
    }
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🔄  RUN ALL ACCOUNTS (SMART)" . str_repeat(" ", 31) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    $i = 0;
    foreach ($accounts as $acc) {
        $i++;
        $label = is_array($acc) ? ($acc['email'] ?? $acc[0] ?? "acc$i") : $acc;
        echo "\n" . CYAN . "── Account $i / " . count($accounts) . " · " . $label . " ──\n" . RESET;
        if (function_exists('runAccount')) {
            runAccount($acc);
        }
        sleep(mt_rand(3, 8));
    }
    echo GREEN . "\n✔ All accounts finished\n" . RESET;
}

function menu() {
    clear();
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🚀  kiddyearner.com AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  1. Run Account (Single)" . str_repeat(" ", 36) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  2. Run All Accounts (Smart)" . str_repeat(" ", 32) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  3. Add Account" . str_repeat(" ", 45) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  4. Delete Account" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  5. View Accounts" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  6. Exit" . str_repeat(" ", 52) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
    echo WHITE . "Choose an option: " . RESET;
}
function headers($auth = null){
    $hd =[
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "accept-language: en-US,en;q=0.9",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
    ];
    if($auth){
        $hd[] = "authorization: Bearer $auth";
    }
    return $hd;
}

function headerss($email,$proxy,$login = false,$auth = null){
    $hd = [
        "accept-language: en-US,en;q=0.9",
        "content-type: application/json",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
        "accept: */*",
        "origin: https://kiddyearner.com",
        "referer: https://kiddyearner.com/"
    ];

    if($login){
        $x = signature();
        $hd[] = "x-claim-signature: " . $x['hash'] ;
        $hd[] = "x-claim-timestamp: " . $x['stamp'];

    }

    if($auth){
        $hd[] = "authorization: Bearer $auth";
    }

    return $hd;
}

function get_telemetry($host,$email,$proxy = null){
    $ipData = json_decode(Run("http://ip-api.com/json/?fields=query,timezone", [], null, "data", $proxy, 2, $email)['body'] ?? '{}', true);
    $ip   = $ipData['query'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $tz   = $ipData['timezone'] ?? 'Asia/Karachi';
    $ua = SaveData(APP_HOST, 'UserAgent');

    $osFamily = PHP_OS_FAMILY; // 'Windows', 'Linux', 'Darwin', etc.
    $platform = match ($osFamily) {
        'Windows' => 'Win32',
        'Linux'   => 'Linux',      // Works for Termux too
        'Darwin'  => 'MacIntel',
        default   => $osFamily,
    };

    $payload = [
        'v'   => '1.0',
        'ts'  => round(microtime(true) * 1000),               // current timestamp in ms
        'url' => "https://$host/",
        'signals' => [
            'userAgent'    => $ua,
            'language'     => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US,en',
            'timezone'     => $tz,
            'timezoneOffset' => (int) (date('Z') / 60),
            'timestamp'    => round(microtime(true) * 1000),
            'platform'     => $platform,
            'hardwareConcurrency' => 4,
            'deviceMemory' => 8,
            'webdriver'    => false,
            'cookieEnabled'=> true,
            'doNotTrack'   => 'unspecified',
            'plugins'      => [
                'PDF Viewer',
                'Chrome PDF Viewer',
                'Chromium PDF Viewer',
                'Microsoft Edge PDF Viewer',
                'WebKit built-in PDF'
            ],
            'screenWidth'  => 1366,
            'screenHeight' => 768,
            'colorDepth'   => 24,
            'canvasFingerprint' => '-7bc8b596',
            'webglVendor'  => 'Google Inc. (Intel)',
            'webglRenderer'=> 'ANGLE (Intel, Intel(R) HD Graphics 520 (0x00001916) Direct3D11 vs_5_0 ps_5_0, D3D11)',
        ]
    ];

    $encoded = base64_encode(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $encoded;
}

function signature($userId = "6a9efe3bbdb64c62a3153b7f"){
    $sec = "InstantFaucet2026!";
    $stamp = (string) (time() * 1000);
    $data = $userId . $stamp;
    $hash = hash_hmac('sha256',$data,$sec);
    return ['hash' => $hash,'stamp' => $stamp];
}

function generate_uuid() {
    // Generate 16 random bytes (128 bits)
    $data = random_bytes(16);
    
    // Set version to 4 (0100)
    $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
    
    // Set variant to RFC 4122 (10xx)
    $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);
    
    // Convert to hex and format with hyphens
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function login($email,$pass,$proxy){
    $r = Run("https://kiddyearner.com/",headers(),null,$email,2,$proxy);
    $captcha = captcha("https://kiddyearner.com/","8f60a5ea-4548-47fc-8ba6-9f027142b92a","hcaptcha");
    $token = $captcha['token'];
    if(!$token){return false;}
    $randomHash = bin2hex(random_bytes(16));
    $ipData = json_decode(Run("http://ip-api.com/json/?fields=query,timezone", [], null, "data", $proxy, 2, $email)['body'] ?? '{}', true);
    $ip   = $ipData['query'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $tz   = $ipData['timezone'] ?? 'Asia/Karachi';
    $ua = SaveData(APP_HOST, 'UserAgent');

    $payload = json_encode([
        "email" => $email,
        "password" => "",
        "referral" => "",
        "captchaToken" => $token,
        "captchaType" => "hcaptcha",
        "fpHash" => $randomHash,
        "deviceId" => generate_uuid(),
        "deviceTimezone" => $tz,
        "offerUid" => null,
        "offerSubid1" => null,
        "offerOid" => null
    ]);

    $r = Run("https://kiddyearner.com/api/auth/login",headerss($email,$proxy,0,null),$payload,null,$proxy,2,$email);
    $js = json_decode($r['body'],true);
    if($js['token']){
        return $js['token'];
    }else{
        return false;
    }
}


function faucet($auth,$email,$proxy){
    while(true){
        $r = Run("https://kiddyearner.com/faucets/DOGE",headers(),null,null,$proxy,2,$email);

        $r = Run("https://kiddyearner.com/api/faucets/stats",headers($auth),null,null,$proxy,2,$email);
        $js = json_decode($r['body'],true);
        if($js['claimsToday'] == 500){
            sleep(2); continue; // keep single-account loop
        }

        $token = solve_adslab("JFf53ZyoXCDhxcj6cnTwKPVKtTNLliXXXi6UOedc","kiddyearner.com")['token'];

        if(!$token){
            continue;
        }

        $payload = json_encode([
            "symbol" => "DOGE",
            "captchaToken" => $token,
            "captchaType" => "adslab_pro",
            "adBlockCheck" => true
        ]);

        $r = Run("https://kiddyearner.com/api/faucets/claim",headerss($email,$proxy,1,$auth),$payload,null,$proxy,2,$email);
        $js = json_decode($r['body'],true);
        print_r($js);exit;
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
            case '1': // Run Account (Single)
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
                
            case '4': // Delete Account
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
                
            case '5': // View Accounts
                displayAccounts();
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
                
            case '6': // Exit
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
