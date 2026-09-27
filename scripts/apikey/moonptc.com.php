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
    return $infoDir . "/moonptc.com.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  moonptc.com AUTO BOT" . str_repeat(" ", 25) . CYAN . "║
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
        "content-type: content-type: application/json",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "origin: https://moonptc.com",
        "referer: https://moonptc.com/"
    ];
}

function login($email,$password = null,$proxy = null){
    
    $r = Run("https://moonptc.com/api/auth/me",headers(),null,null,$proxy,2,$email);
    $js = json_decode($r['body'],true);
    if(!empty($js['user'])){return true;}
    
    $payload = json_encode(["email" => $email]);
    $r = Run("https://moonptc.com/api/auth/email-login",headerss(),$payload,null,$proxy,2,$email);
    $js = json_decode($r['body'],true);
    if($js['ok']){
        return true;
    }
    return false;
}

function faucet($email,$proxy){
    while(true){
        $r = Run("https://moonptc.com/api/faucet/status",headers(),null,null,$proxy,2,$email);
        $js = json_decode($r['body'],true);
        if(!$js['canClaim'] || $js['remainingSeconds'] > 0){
            countdown($js['remainingSeconds'],"⏳ Waiting ");
            continue;
        }

        $payload = json_encode([]);
        $r = Run("https://moonptc.com/api/faucet/rotation-captcha/challenge", headerss(), $payload, null, $proxy, 2, $email);
        $r = json_decode($r['body'],true);

        $session_id = $r['session_id'];
        $id = $r['challenge']['id'];
        $image = base64_encode(Run("https://moonptc.com/captcha/moon-base.webp",headers(),null,null,$proxy,2,$email)['body']);
        $piece = base64_encode(Run("https://moonptc.com" . $r['challenge']['image'],headers(),null,null,$proxy,2,$email)['body']);
        $crop = $r['challenge']['crop'];
        
        $verify_payload = rotation($image,$piece,$crop);
        
        $verify_payload = json_encode(["session_id" => $session_id,"angle" => (int)$verify_payload['token']]);
        
        $r = Run("https://moonptc.com/api/faucet/rotation-captcha/verify", headerss(), $verify_payload, null, $proxy, 2, $email);

        $js = json_decode($r['body'], true);

        if (isset($js['ok']) && $js['ok'] === true) {
            $verifyToken = $js['token'];
        } else {
            $GLOBALS['failed']++;
            if($GLOBALS['failed'] == 3){
                exit("3 Incorrect Claims\n");
            }
            continue;
        }

        $payload = json_encode(["captcha_token" => $verifyToken]);
        $r =  Run("https://moonptc.com/api/faucet/claim",headerss(),$payload,null,$proxy,2,$email);
        $js = json_decode($r['body'],true);
        $GLOBALS['faucet_totalclaims']++;
        if($js['ok']){
            $GLOBALS['failed'] = 0;
            $GLOBALS['faucet_claims']++;
            logClaim($GLOBALS['faucet_claims'], $GLOBALS['faucet_totalclaims'], "+{$js['finalReward']}", $js['newBalance'] . " | " . get_balance());
        }else{
            $GLOBALS['failed']++;
            if($GLOBALS['failed'] == 3){
                exit("3 Incorrect Claims\n");
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