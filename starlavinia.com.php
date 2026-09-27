<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
define("BASE_DIR", __DIR__);
define("CONFIG_FILE", BASE_DIR . "/config/accounts_gamefaucet.json");

// ============================================
// INCLUDES
// ============================================
include_once BASE_DIR . "/functions/connecter.php";
include_once BASE_DIR . "/functions/session.php";

/*
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
*/

if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

// Color constants
if (!defined("WHITE")) define("WHITE", "\033[0m");
if (!defined("GREEN")) define("GREEN", "\033[32m");
if (!defined("RED")) define("RED", "\033[31m");
if (!defined("YELLOW")) define("YELLOW", "\033[33m");
if (!defined("RESET")) define("RESET", "\033[0m");

// ============================================
// ACCOUNT STORAGE (text file – email|password|proxy)
// ============================================

function accountFile() {
    $dir = BASE_DIR . "/information";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . "/starlavinia.com.txt";
}

function loadAccounts() {
    $file = accountFile();
    if (!file_exists($file)) {
        // Create the file with header if it doesn't exist
        file_put_contents($file, "# Account file for starlavinia.com\n# Format: email|password|proxy (proxy optional)\n# Example: user1@gmail.com|password123\n# Example: user2@gmail.com|password456|user:pass@ip:port\n");
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $accounts = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        
        $parts = array_map('trim', explode('|', $line));
        $email = $parts[0] ?? '';
        $password = $parts[1] ?? '';
        $proxy = $parts[2] ?? "";
        
        if (empty($proxy)) $proxy = "";
        if (empty($email) || empty($password)) continue;
        
        $accounts[] = [
            'email' => $email,
            'password' => $password,
            'proxy' => $proxy
        ];
    }
    
    return $accounts;
}

// ============================================
// CORE FUNCTIONS (starlavinia.com)
// ============================================

function captcha_starlavinia($email, $proxy, &$csrf, $referer = "https://starlavinia.com/login") {
    
    $session = "starlavinia_" . md5($email);
    
    // Get captcha page
    $header = ["host: starlavinia.com", "user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36", "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"];
    $r = Run("https://starlavinia.com/captcha", $header, null, null, $proxy, 2, $session);
    if ($r['info']['http_code'] != 200) return false;
    
    // Get fresh CSRF from captcha page
    if (preg_match('/name="_token" value="([^"]+)"/', $r['body'], $m)) {
        $csrf = $m[1];
    }
    
    // Get captcha script URL
    $captchaOnload = explode('";', explode('scriptTag.src = "', $r['body'])[1])[0];
    
    // Load captcha script
    $header = ["host: starlavinia.com", "user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36", "accept: */*", "referer: $referer"];
    $r = Run("https://starlavinia.com" . $captchaOnload, $header, null, null, $proxy, 2, $session);
    if ($r['info']['http_code'] != 200) return false;
    
    // Extract data
    $target_image = explode('"', explode('<img src="data:image/png;base64,', $r['body'])[1])[0];
    
    $options = json_decode(explode('};', explode('const captchaData =', $r['body'])[1])[0] . '}', true);
    
    $cdata = explode('", true);', explode('xhr.open("POST", "', $r['body'])[1])[0];
    
    $token_name = trim(explode(';', explode('response.', $r['body'])[2])[0]);
    
    $token_name_ = trim(explode('"', explode('<input type="hidden" id="', $r['body'])[1])[0]);
    
    $xhrsend = explode(';', explode('xhr.send(', $r['body'])[1])[0];
    
    // Solve captcha
    $json = json_encode(["apikey" => anticaptcha_key, "mode" => "iconcaptcha", "type" => "awsomefont", "target" => $target_image, "options" => $options['options']]);
    
    $api = json_decode(Run(api_endpoint, ["Content-Type: application/json"], $json)['body'], true);
    $poll = poll($api['jobId']);
    
    $index = $poll['index'];
    if($index == ""){return false;}
    
    // Build payload
    $xpost = trim(explode('" +', explode('csrfToken+"', $xhrsend)[1])[0]);
    $payload = "_token=$csrf" . $xpost . "$index";
    
    // Send solution
    $header = ["host: starlavinia.com", "user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36", "accept: */*", "content-type: application/x-www-form-urlencoded", "origin: https://starlavinia.com", "x-requested-with: XMLHttpRequest", "referer: $referer"];
    $r = Run("https://starlavinia.com" . $cdata, $header, $payload, null, $proxy, 2, $session);
    
    if ($r['info']['http_code'] != 200) {
        echo RED . "Captcha HTTP Error: " . $r['info']['http_code'] . RESET . "\n";
        return false;
    }
    
    // Get token from response
    $response = json_decode($r['body'], true);
    
    if (isset($response[$token_name]) && !empty($response[$token_name])) {
        return ["name" => $token_name_, "value" => $response[$token_name], "token" => $csrf];
    }
    
    return false;
}

function login($email, $password, $proxy = "") {
    
    $session = "starlavinia_" . md5($email);
    
    // Get login page
    $header = ["host: starlavinia.com", "user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36", "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"];
    $r = Run("https://starlavinia.com/login", $header, null, null, $proxy, 2, $session);
    
    if ($r['info']['http_code'] == 302) {
        return true;
    }
    
    if ($r['info']['http_code'] != 200) return false;
    
    // Get CSRF token
    $token = explode('"', explode('<input type="hidden" name="_token" value="', $r['body'])[1])[0];
    
    // Solve captcha
    $captcha = captcha_starlavinia($email, $proxy, $token, "https://starlavinia.com/login");
    
    if (!$captcha) {
        echo RED . "Captcha failed!" . RESET . "\n";
        return false;
    }
    
    $payload = http_build_query([
        "_token" => $captcha['token'],
        "email" => $email,
        "password" => $password,
        $captcha['name'] => $captcha['value'],
        "otp" => "",
        "remember" => "on"
    ]);
    
    $header = [
        "host: starlavinia.com",
        "user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "content-type: application/x-www-form-urlencoded",
        "referer: https://starlavinia.com/login",
        "origin: https://starlavinia.com",
        "accept-language: en-US,en;q=0.9",
    ];
    
    $r = Run("https://starlavinia.com/login", $header, $payload, "data", $proxy, 2, $session);
    
    if ($r['info']['http_code'] == 302) {
        return true;
    }
    
    echo RED . "Login failed. HTTP: " . $r['info']['http_code'] . RESET . "\n";
    return false;
}

function faucet($email, $proxy = "") {
    
    while(true){
        
        $session = "starlavinia_" . md5($email);
    
        $header = ["host: starlavinia.com", "user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36", "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"];
        $r = Run("https://starlavinia.com/faucet/coin/doge", $header, null, null, $proxy, 2, $session);
        
        if ($r['info']['http_code'] != 200) {
            echo RED . "Failed to load faucet page. HTTP: " . $r['info']['http_code'] . RESET . "\n";
            return false;
        }
        
        $token = '';
        if (preg_match('/name="_token" value="([^"]+)"/', $r['body'], $m)) {
            $token = $m[1];
        }
        
        if (empty($token)) {
            continue;
        }
    
        $captcha = captcha_starlavinia($email, $proxy, $token, "https://starlavinia.com/faucet/coin/doge");
        
        if (!$captcha) {
            continue;
        }
    
        $payload = http_build_query([
        "_token" => $captcha['token'],
        "currency" => "DOGE",
        $captcha['name'] => $captcha['value']
        ]);
    
        $headers = ["host: starlavinia.com","user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36","accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8","content-type: application/x-www-form-urlencoded","referer: https://starlavinia.com/faucet/coin/doge","origin: https://starlavinia.com","accept-language: en-US,en;q=0.9",];
        
        $r = Run("https://starlavinia.com/faucet/claim", $headers, $payload, "data", $proxy, 2, $session);
    
        $r = Run("https://starlavinia.com/faucet/coin/doge", $header, null, null, $proxy, 2, $session);
        
        if ($r['info']['http_code'] == 200) {
            
            if (preg_match('/flashMessage\s*=\s*"(.*?)";/s', $r['body'], $m)) {
                $message = json_decode('"' . $m[1] . '"');
            
                if (preg_match('/(\d+\.\d+)\s*DOGE/i', $message, $amountMatch)) {
                    $amount = $amountMatch[0];
               
                    $GLOBALS['faucet_totalclaims']++;
                    $GLOBALS['faucet_claims']++;
                    echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . GREEN . "+$amount" . RESET . " | $email\n";
                }
                
                if (stripos($message, 'error') !== false || stripos($message, 'fail') !== false || stripos($message, 'wait') !== false || stripos($message, 'later') !== false) {
                    continue;
                }
            }
        }
    }
}

// ============================================
// GLOBAL COUNTERS
// ============================================

$GLOBALS['faucet_claims'] = 0;
$GLOBALS['faucet_totalclaims'] = 0;

// ============================================
// MULTI-ACCOUNT PROCESSING
// ============================================

function processSingleAccount($account) {
    $email = $account['email'];
    $password = $account['password'];
    $proxy = $account['proxy'];
    
    echo GREEN . "Processing: $email" . RESET . "\n";
    
    $loginResult = login($email, $password, $proxy);
    
    if ($loginResult) {
        echo GREEN . "Login successful!" . RESET . "\n";
        sleep(2);
        faucet($email, $proxy);
    } else {
        echo RED . "Login failed, cannot proceed to faucet" . RESET . "\n";
    }
}

function processAccountsWithRotation($accounts) {
    $loggedInAccounts = [];
    
    // Login all accounts
    foreach ($accounts as $account) {
        $email = $account['email'];
        $password = $account['password'];
        $proxy = $account['proxy'];
        
        echo GREEN . "Logging in: $email" . RESET . "\n";
        
        $loginResult = login($email, $password, $proxy);
        
        if ($loginResult) {
            echo GREEN . "Login successful: $email" . RESET . "\n";
            $loggedInAccounts[] = [
                'email' => $email,
                'password' => $password,
                'proxy' => $proxy
            ];
        } else {
            echo RED . "Login failed: $email" . RESET . "\n";
        }
        
        sleep(2);
    }
    
    if (empty($loggedInAccounts)) {
        echo RED . "No accounts logged in successfully!" . RESET . "\n";
        return;
    }
    
    // Process faucet for each account
    foreach ($loggedInAccounts as $acc) {
        echo GREEN . "Starting faucet for: {$acc['email']}" . RESET . "\n";
        faucet($acc['email'], $acc['proxy']);
    }
}

// ============================================
// MENU SYSTEM
// ============================================

function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

function displayAccounts() {
    $accounts = loadAccounts();
    
    if (empty($accounts)) {
        echo "📭 No accounts configured\n";
        return;
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 Configured Accounts:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    foreach ($accounts as $index => $acc) {
        echo "[" . ($index + 1) . "] {$acc['email']}\n";
        echo "    Password: {$acc['password']}\n";
        if (!empty($acc['proxy'])) {
            echo "    Proxy: {$acc['proxy']}\n";
        } else {
            echo "    Proxy: None\n";
        }
        echo "\n";
    }
}

function mainMenu() {
    while (true) {
        clearScreen();
        echo "╔══════════════════════════════════════╗\n";
        echo "║   StarLavinia Multi-Account Bot      ║\n";
        echo "╠══════════════════════════════════════╣\n";
        echo "║  1. View Account File Location       ║\n";
        echo "║  2. View Accounts                    ║\n";
        echo "║  3. Start Single Account (24/7)      ║\n";
        echo "║  4. Start All Accounts               ║\n";
        echo "║  5. Exit                             ║\n";
        echo "╚══════════════════════════════════════╝\n";
        echo "\nSelect option: ";
        
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                showAccountFileLocation();
                break;
                
            case '2':
                clearScreen();
                displayAccounts();
                echo "\nPress Enter to continue...";
                fgets(STDIN);
                break;
                
            case '3':
                startSingleAccount();
                break;
                
            case '4':
                startAllAccounts();
                break;
                
            case '5':
                echo "👋 Goodbye!\n";
                exit(0);
                
            default:
                echo "❌ Invalid option!\n";
                sleep(1);
        }
    }
}

function showAccountFileLocation() {
    clearScreen();
    $file = accountFile();
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📄 Account File Location\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "File: $file\n\n";
    echo "Edit this file to add accounts.\n";
    echo "Format: email|password|proxy (proxy optional)\n";
    echo "Example:\n";
    echo "user1@gmail.com|password123\n";
    echo "user2@gmail.com|password456|user:pass@ip:port\n\n";
    echo "Press Enter to continue...";
    fgets(STDIN);
}

function startSingleAccount() {
    $accounts = loadAccounts();
    
    if (empty($accounts)) {
        echo "❌ No accounts configured!\n";
        echo "Please edit the account file first.\n";
        echo "File: " . accountFile() . "\n";
        sleep(2);
        return;
    }
    
    clearScreen();
    displayAccounts();
    
    if (count($accounts) == 1) {
        $index = 0;
        echo "\nUsing account [1]\n";
    } else {
        echo "\nSelect account number (1-" . count($accounts) . "): ";
        $index = intval(trim(fgets(STDIN))) - 1;
        
        if ($index < 0 || $index >= count($accounts)) {
            echo "❌ Invalid selection!\n";
            sleep(2);
            return;
        }
    }
    
    clearScreen();
    processSingleAccount($accounts[$index]);
    
    echo "\nPress Enter to continue...";
    fgets(STDIN);
}

function startAllAccounts() {
    $accounts = loadAccounts();
    
    if (empty($accounts)) {
        echo "❌ No accounts configured!\n";
        echo "Please edit the account file first.\n";
        echo "File: " . accountFile() . "\n";
        sleep(2);
        return;
    }
    
    clearScreen();
    processAccountsWithRotation($accounts);
    
    echo "\nPress Enter to continue...";
    fgets(STDIN);
}

// ============================================
// MAIN ENTRY POINT
// ============================================

mainMenu();

?>