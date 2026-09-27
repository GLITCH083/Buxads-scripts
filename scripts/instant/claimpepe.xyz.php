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
    return $infoDir . "/claimpepe.xyz.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  claimpepe.xyz AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
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
        "origin: https://claimpepe.xyz",
        "referer: https://claimpepe.xyz/"
    ];
}

function login($email, $password = '', $proxy) {
    $r = Run("https://claimpepe.xyz",headers(),null,null,$proxy,0,$email);
      
    if ($r['info']['redirect_url'] == "https://claimpepe.xyz/dashboard") {
        return true;
    }
    
    if ($r['info']['http_code'] == 200) {
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://claimpepe.xyz", $sitekey, "hcaptcha");
        
        if (!$cap) return  false;
        
        $ipData = json_decode(Run("http://ip-api.com/json/?fields=query,timezone", [], null, "data", $proxy, 2, $email)['body'] ?? '{}', true);
        $ip = $ipData['query'] ?? '';
        $timezone = $ipData['timezone'] ?? '';
        $ls = 'en-US,en';
        $userAgent = SaveData(APP_HOST, 'UserAgent');
        $utt = $timezone;
        $uf = md5($ip . $userAgent . $ls . $utt);
        
        $payload = http_build_query([
            "email" => $email,
            "captcha" => "hcaptcha",
            "g-recaptcha-response" => $cap['token'],
            "h-captcha-response" => $cap['token'],
            "uf" => $uf,
            "utt" => $utt,
            "ls" => $ls
        ]);
        
        $r = Run("https://claimpepe.xyz/auth/login",headerss(),$payload,null,$proxy,0,$email);
        
        $r = Run("https://claimpepe.xyz",headers(),null,null,$proxy,0,$email);

        if ($r['info']['redirect_url'] == "https://claimpepe.xyz/dashboard") {
            return true;
        }
    }
    return false;
}

function faucet($email, $proxy) {
    // forever: cycle all coins for THIS one account (Ctrl+C to stop)
    while (true) {
        $coins = ['ltc','usdt','dgb','sol','fey','pepe','doge'];
        foreach ($coins as $coin) {
        $claimCount = 0;
        while (true) {
        
            $r = Run("https://claimpepe.xyz/faucet/$coin",headers(),null,null,$proxy,0,$email);

            if ($r['info']['redirect_url']) {shortlink($email, $proxy,$coin);continue;}
        
        if ($r['info']['http_code'] == 200) {
            $body = $r['body'];
            
          
            $timer = 0;
            if (preg_match('/let\s+wait\s*=\s*(\d+)/', $body, $m)) {
                $timer = intval($m[1]);
            } elseif (preg_match('/var\s+wait\s*=\s*(\d+)/', $body, $m)) {
                $timer = intval($m[1]);
            } elseif (preg_match('/countdown\s*\(\s*(\d+)\s*\)/', $body, $m)) {
                $timer = intval($m[1]);
            }
            
            if ($timer) {countdown($timer,"⏳ Waiting ");continue;}
            
            // Extract form tokens
                $csrf = explode('"', explode('<input type="hidden" name="ci_csrf_token" id="token" value="', $body)[1])[0];
                $token = explode('"', explode('<input type="hidden" name="token" value="', $body)[1])[0];
                $cur = explode('"', explode('<input type="hidden" name="currency" value="', $body)[1])[0];
                $rscaptcha_token = explode('"', explode('<input type="hidden" name="rscaptcha_token" value="', $body)[1])[0];
                $captcha = rs_upsidedown($body, "https://rscaptcha.com/assets/generated_captcha/");
            
                if (!$captcha['rs-response']) {continue;}
                $ipData = json_decode(Run("http://ip-api.com/json/?fields=query,timezone", [], null, "data", $proxy, 2, $email)['body'] ?? '{}', true);
                $ip = $ipData['query'] ?? '';
                $timezone = $ipData['timezone'] ?? '';
                $ls = 'en-US,en';
                $userAgent = SaveData(APP_HOST, 'UserAgent');
                $utt = $timezone;
                $uf = md5($ip . $userAgent . $ls . $utt);
            
                $payload = http_build_query(["ci_csrf_token" => $csrf,"token" => $token,"currency" => $cur,"captcha" => "rscaptcha","rscaptcha_token" => $rscaptcha_token,"rscaptcha_response" => $captcha['rs-response'],"uf" => $uf,"utt" => $utt,"ls" => $ls]);
            
                $r = Run("https://claimpepe.xyz/faucet/verify",headerss(),$payload,null,$proxy,0,$email);
            
                $r = Run("https://claimpepe.xyz/faucet/$coin",headers(),null,null,$proxy,0,$email);
        
                $msg = explode("',", explode("text: '", $r['body'])[1])[0] ?? '';

                if($msg == "The faucet does not have sufficient funds for this transaction."){
                    break;
                }
            
                $GLOBALS['faucet_totalclaims']++;
                if ($msg) {
                    $GLOBALS['failed'] = 0;
                    $claimCount++;
                    $GLOBALS['faucet_claims']++;
                    $rsLen = isset($captcha['rs-response']) ? strlen($captcha['rs-response']) : 0;
                    $rsPrev = $rsLen ? substr($captcha['rs-response'], 0, 22) . "..." : "";
                    if (function_exists("claimBoxOpen")) {
                        claimBoxOpen("✅  CLAIM SUCCESSFUL");
                        claimBoxRow("🧩 Captcha", YELLOW . "RSCaptcha" . RESET);
                        if ($rsLen > 0) claimBoxRow("🔑 Token Len", GREEN . $rsLen . RESET);
                        if ($rsPrev) claimBoxRow("🔐 Token", GREY . $rsPrev . RESET);
                        claimBoxRow("📊 Claim", GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . RESET);
                        claimBoxRow("🪙 Coin", WHITE . strtoupper($coin) . RESET);
                        claimBoxRow("🎁 Reward", GREEN . "+" . $msg . RESET);
                        claimBoxRow("💰 Balance", YELLOW . get_balance() . RESET);
                        claimBoxClose();
                    } else {
                        logClaim($GLOBALS['faucet_claims'], $GLOBALS['faucet_totalclaims'], $msg, get_balance(), null, [
                            "Type" => "RSCaptcha",
                            "Token Len" => $rsLen,
                            "Token" => $rsPrev,
                        ]);
                    }
sleep(2);
                    continue;
            }
        }
    }
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