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
    return $infoDir . "/limefaucet.com.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  limefaucet.com AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
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
        "content-type: content-type: application/json",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "origin: https://limefaucet.com",
        "referer: https://limefaucet.com/"
    ];
}

function login($email,$password = null,$proxy = null){
    
    $r = Run("https://limefaucet.com/api/auth/me",headers(),null,null,$proxy,2,$email);
    $js = json_decode($r['body'],true);
    if(!empty($js['user'])){return true;}
    
    $payload = json_encode(["email" => $email,"referral_code" => null]);
    $r = Run("https://limefaucet.com/api/auth/login",headerss(),$payload,null,$proxy,2,$email);
    $js = json_decode($r['body'],true);
    if($js['user']['id']){
        return true;
    }
    return false;
}



function target_at($data, $t_ms) {
    $period = max(1, (int)$data['period_ms']);
    $path = $data['path'];
    $n = (($t_ms % $period) + $period) % $period;
    $r = $path[0];
    $a = array_merge($path[0], ['at' => $period]);
    for ($i = count($path) - 1; $i >= 0; $i--) {
        if ($n >= $path[$i]['at']) {
            $r = $path[$i];
            $a = ($i + 1 < count($path))
                ? $path[$i + 1]
                : array_merge($path[0], ['at' => $period]);
            break;
        }
    }
    $span = max(1, $a['at'] - $r['at']);
    $s = max(0, min(1, ($n - $r['at']) / $span));
    return [
        'x' => $r['x'] + ($a['x'] - $r['x']) * $s,
        'y' => $r['y'] + ($a['y'] - $r['y']) * $s,
    ];
}

function solve_dot_captcha($challenge_json) {
    $data = json_decode($challenge_json, true);
    if (empty($data['session_id']) || empty($data['path'])) {
        throw new Exception('bad challenge');
    }

    $period = (int)$data['period_ms'];
    $hold   = (int)$data['hold_duration_ms'];
    $start  = $data['dot_start'];
    $samples = [];
    $t0 = microtime(true);

    // catch target ~0.3s
    for ($i = 1; $i <= 8; $i++) {
        $t_ms = (int)((microtime(true) - $t0) * 1000);
        $f = $i / 8.0;
        $pos = target_at($data, max(0, $t_ms));
        $samples[] = [
            'x' => $start['x'] + ($pos['x'] - $start['x']) * $f,
            'y' => $start['y'] + ($pos['y'] - $start['y']) * $f,
            't' => max(0, $t_ms),
            'down' => true,
        ];
        usleep(35000);
    }

    // KEY: realtime track for period + hold
    $end_ms = $period + $hold + 150;
    while (true) {
        $t_ms = (int)((microtime(true) - $t0) * 1000);
        if ($t_ms > $end_ms) break;
        $pos = target_at($data, $t_ms);
        $jx = (mt_rand(-8, 8) / 10000.0);
        $jy = (mt_rand(-8, 8) / 10000.0);
        if ($samples && $t_ms <= $samples[count($samples)-1]['t']) {
            $t_ms = $samples[count($samples)-1]['t'] + 1;
        }
        $samples[] = [
            'x' => max(0.02, min(0.98, $pos['x'] + $jx)),
            'y' => max(0.02, min(0.98, $pos['y'] + $jy)),
            't' => $t_ms,
            'down' => true,
        ];
        usleep(40000);
    }

    return json_encode([
        'session_id' => $data['session_id'],
        'action'     => 'faucet',
        'arena'      => ['width' => 420, 'height' => 260],
        'samples'    => $samples,
    ]);
}



function faucet($email, $proxy) {
    while (true) {
        // 1. Check if claim is available
        $r = Run("https://limefaucet.com/api/faucet/info", headers(), null, null, $proxy, 2, $email);
        $js = json_decode($r['body'], true);
        if ($js['time_remaining_seconds'] > 0) {
            countdown($js['time_remaining_seconds'], "⏳ Waiting ");
            continue;
        }

        // 2. Get new dot‑captcha challenge
        $payload = " ";
        $r = Run("https://limefaucet.com/api/faucet/ac-captcha/challenge", headerss(), $payload, null, $proxy, 2, $email);
        $r = json_decode($r['body'],true);

        $session_id = $r['session_id'];
        $id = $r['challenge']['id'];
        $challenge = $r['challenge']['image'];
        
        $image = explode(',', $challenge)[1];
        
        $verify_payload = motion($image);

        $verify_payload = json_encode(["session_id" => $session_id,"candidate_index" => $verify_payload['index']]);
        
        $r = Run("https://limefaucet.com/api/faucet/ac-captcha/verify", headerss(), $verify_payload, null, $proxy, 2, $email);
        
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

        // 5. Claim faucet with the token
        $claim_payload = json_encode(["captcha_token" => $verifyToken]);
        $r = Run("https://limefaucet.com/api/faucet/claim", headerss(), $claim_payload, null, $proxy, 2, $email);
        $js = json_decode($r['body'], true);
        $GLOBALS['faucet_totalclaims']++;
        if ($js['reward_crypto']) {
            $GLOBALS['failed'] = 0;
            $GLOBALS['faucet_claims']++;
            logClaim($GLOBALS['faucet_claims'], $GLOBALS['faucet_totalclaims'], "+" . number_format((float)$js['reward_crypto'], 6, ".", "") . " " . $js['currency'], get_balance());
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