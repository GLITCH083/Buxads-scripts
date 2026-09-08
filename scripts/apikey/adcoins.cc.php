<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
define("BASE_DIR", dirname(__DIR__));

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

if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

$GLOBALS['faucet_claims'] = 0;
$GLOBALS['faucet_totalclaims'] = 0;
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

function menu() {
    clear();
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🚀  adcoins.cc AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  1. Run Account" . str_repeat(" ", 45) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  2. Add Account" . str_repeat(" ", 45) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  3. Delete Account" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  4. View Accounts" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  5. Exit" . str_repeat(" ", 52) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
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
                echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . 
                     GREEN . "+{$result['reward']} Coins | " . RESET . " {$result['new_balance']} Coins " .
                     " | Api Balance: " . YELLOW . $balance . " Token" . RESET . "\n";
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

function runAccount($account) {
    $email = $account['email'];
    $password = isset($account['password']) ? $account['password'] : '';
    $proxy = isset($account['proxy']) ? $account['proxy'] : null;
    
    echo "\n" . CYAN . "═══════════════════════════════════════════════════════════════\n" . RESET;
    echo WHITE . "  👤 Running account: " . GREEN . $email . RESET . "\n";
    if ($proxy) {
        echo WHITE . "  🌐 Proxy: " . CYAN . $proxy . RESET . "\n";
    }
    if (empty($password)) {
        echo YELLOW . "  ⚠️  No password set for this account\n" . RESET;
    }
    echo CYAN . "═══════════════════════════════════════════════════════════════\n" . RESET;
    
    $login = login($email, $password, $proxy);
    if($login){
        echo GREEN . "✓ Login Successful\n" . RESET;
        $GLOBALS['faucet_claims'] = 0;
        $GLOBALS['faucet_totalclaims'] = 0;
        faucet($email, $proxy);
    } else {
        echo RED . "✗ Login Failed for: " . $email . "\n" . RESET;
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
                    echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                    fgets(STDIN);
                }
                break;
                
            case '2': // Add Account
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
                
            case '3': // Delete Account
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
                
            case '4': // View Accounts
                displayAccounts();
                echo "\n" . WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
                
            case '5': // Exit
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