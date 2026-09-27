<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
define("BASE_DIR", __DIR__);

// ============================================
// INCLUDES
// ============================================

include_once BASE_DIR . "/functions/connecter.php";
include_once BASE_DIR . "/functions/session.php";

if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

// ============================================
// ACCOUNT STORAGE (text file)
// ============================================

function accountFile() {
    $dir = BASE_DIR . "/information";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . "/ourcoincash.xyz.txt";
}

function loadAccounts() {
    $file = accountFile();
    if (!file_exists($file)) {
        // Create the file with header if it doesn't exist
        file_put_contents($file, "# Account file for ourcoincash.xyz\n# Format: email|password|proxy (proxy optional)\n# Example: user1@gmail.com|pass123|user:pass@ip:port\n");
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
        $proxy = $parts[2] ?? null;
        
        // Skip if email or password is missing
        if (empty($email) || empty($password)) continue;
        
        if (empty($proxy)) $proxy = null;
        
        $accounts[] = [
            'email' => $email,
            'password' => $password,
            'proxy' => $proxy
        ];
    }
    
    return $accounts;
}

// ============================================
// CORE FUNCTIONS (Your original working code)
// ============================================

function login($url, $host, $email, $password, $proxy) {
    $r = safeRequest($url, $host, $email, $proxy);
    
    if ($r['info']['redirect_url'] == "https://ourcoincash.xyz/dashboard") {
        return ["success" => true, "url" => $r['info']['redirect_url']];
    }
    
    if ($r['info']['http_code'] == 200) {
        $body = $r['body'];
        
        $csrf = explode('"', explode('<input type="hidden" name="csrf_token_name" value="', $body)[1])[0];
        $payload = http_build_query(["email" => $email, "password" => $password, "csrf_token_name" => $csrf]);
        
        $r = safeRequestpost("https://ourcoincash.xyz/auth/login", $host, $payload, $body, $email, $proxy);
        
        if ($r['info']['redirect_url'] == "https://ourcoincash.xyz/dashboard") {
            return ["success" => true, "url" => $r['info']['redirect_url']];
        }
    }
    
    return ["success" => false];
}

function dashboard($url, $host, $email, $proxy) {
    $r = safeRequest($url, $host, $email, $proxy);
    
    if ($r['info']['http_code'] == 200) {
        $body = $r['body'];
        
        $balance = explode('</p>', explode('<p class="acc-amount"><i class="fas fa-coins"></i> ', $body)[1])[0];
        return $balance;
    }
    
    return "0";
}

function faucet($url, $host, $email, $proxy, $maxClaims = null) {
    $claimCount = 0;
    
    while (true) {
        // Check if max claims reached
        if ($maxClaims !== null && $claimCount >= $maxClaims) {
            echo "✅ Completed $claimCount claims for $email\n";
            return $claimCount;
        }
        
        $r = safeRequest($url, $host, $email, $proxy);
        
        if ($r['info']['http_code'] == 200) {
            $body = $r['body'];
            
            // Check timer
            if (strpos($body, 'let wait = ') !== false) {
                $timer = explode(' - 1;', explode('let wait = ', $body)[1])[0];
                if ($timer > 0) {
                    echo "⏰ Timer: $timer seconds for $email\n";
                    
                    // If timer > 30 seconds, return to switch account
                    if ($timer > 30 && $maxClaims !== null) {
                        echo "⏭️ Timer too long, switching account\n";
                        return $claimCount;
                    }
                    
                    sleep($timer);
                    continue;
                }
            }
            
            // Get CSRF and token
            $csrf = explode('"', explode('<input type="hidden" name="csrf_token_name" id="token" value="', $body)[1])[0];
            $token = explode('"', explode('<input type="hidden" name="token" value="', $body)[1])[0];
            $bot = solve_f_a($body);
            
            if ($bot) {
                $payload = http_build_query(["antibotlinks" => $bot, "csrf_token_name" => $csrf, "token" => $token]);
                $r = safeRequestpost("https://ourcoincash.xyz/faucet/verify", $host, $payload, $body, $email, $proxy);
                
                // Get response
                $r = safeRequest($url, $host, $email, $proxy);
                $msg = explode("',", explode("text: '", $r['body'])[1])[0];
                $balance = dashboard("https://ourcoincash.xyz/dashboard", $host, $email, $proxy);
                
                $claimCount++;
                echo "💰 Faucet | $msg | $balance Coins | $email | Claim #$claimCount\n";
                
                // Small delay before next check
                sleep(5);
            }
        }
    }
}

// ============================================
// MULTI-ACCOUNT PROCESSING
// ============================================

function processSingleAccount($account) {
    $email = $account['email'];
    $password = $account['password'];
    $proxy = $account['proxy'] ?? "";
    $host = "ourcoincash.xyz";
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "👤 Processing: $email\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $response = login("https://ourcoincash.xyz/login", $host, $email, $password, $proxy);
    
    if ($response['success']) {
        $balance = dashboard($response['url'], $host, $email, $proxy);
        echo "💰 Balance: $balance Coins\n";
        
        // Run faucet continuously for this account
        faucet("https://ourcoincash.xyz/faucet", $host, $email, $proxy);
    } else {
        echo "❌ Login failed for $email\n";
    }
}

function processAccountsWithRotation($accounts) {
    $host = "ourcoincash.xyz";
    
    echo "🚀 Starting rotation mode (5 claims per account)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Login all accounts first
    $loggedInAccounts = [];
    
    foreach ($accounts as $account) {
        $email = $account['email'];
        $password = $account['password'];
        $proxy = $account['proxy'] ?? "";
        
        echo "🔐 Logging in: $email\n";
        $response = login("https://ourcoincash.xyz/login", $host, $email, $password, $proxy);
        
        if ($response['success']) {
            $balance = dashboard($response['url'], $host, $email, $proxy);
            echo "✅ Login successful | Balance: $balance Coins\n";
            
            $loggedInAccounts[] = [
                'email' => $email,
                'proxy' => $proxy,
                'balance' => $balance
            ];
        } else {
            echo "❌ Login failed for $email\n";
        }
        echo "\n";
    }
    
    if (empty($loggedInAccounts)) {
        echo "❌ No accounts logged in successfully!\n";
        return;
    }
    
    // Process 5 claims per account in rotation
    $roundNum = 1;
    
    while (true) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🔄 Round $roundNum\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        $allAccountsDone = true;
        
        foreach ($loggedInAccounts as $acc) {
            echo "\n👤 Processing: {$acc['email']}\n";
            
            // Try to make claims for this account
            $claimCount = faucet("https://ourcoincash.xyz/faucet", $host, $acc['email'], $acc['proxy'], 5);
            
            if ($claimCount > 0) {
                $allAccountsDone = false;
                echo "✅ {$acc['email']} completed $claimCount claims\n";
            } else {
                echo "⏭️ {$acc['email']} couldn't claim (timer too long)\n";
            }
            
            echo "\n";
        }
        
        if ($allAccountsDone) {
            echo "✅ All accounts completed their claims!\n";
            break;
        }
        
        $roundNum++;
        
        // Wait before next round
        echo "⏳ Waiting 60 seconds before next round...\n";
        sleep(60);
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
        echo "║     OurCoinCash Multi-Account Bot    ║\n";
        echo "╠══════════════════════════════════════╣\n";
        echo "║  1. View Account File Location       ║\n";
        echo "║  2. View Accounts                    ║\n";
        echo "║  3. Start Single Account (24/7)      ║\n";
        echo "║  4. Start All Accounts (Rotation)    ║\n";
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
    echo "user1@gmail.com|pass123\n";
    echo "user2@gmail.com|pass456|user:pass@ip:port\n\n";
    echo "Press Enter to continue...";
    fgets(STDIN);
}

function startSingleAccount() {
    $accounts = loadAccounts();
    
    if (empty($accounts)) {
        echo "❌ No accounts configured!\n";
        echo "Please edit the account file first.\n";
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