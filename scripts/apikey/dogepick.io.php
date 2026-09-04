<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
define("BASE_DIR", __DIR__);

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


function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/dogepick.io.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  dogepick.io AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
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
        "origin: https://dogepick.io",
        "referer: https://dogepick.io/"
    ];
}


function payload($html, $email, $host, $url, $ua, $password = null, $proxy = null, $faucet = false, $login = false, $csrf = null, $key = null, $captcha_url = null) {
    
    // Check if option exists in HTML
    $option = 0;
    if (strpos($html, '<option value="') !== false) {
        $option = explode('"', explode('<option value="', $html)[1])[0];
    }
    
    $iconToken = '';
    if (strpos($html, "_iconcaptcha-token' value='") !== false) {
        $iconToken = explode("'", explode("<input type='hidden' name='_iconcaptcha-token' value='", $html)[1])[0];
    }
    
    $data = array_fill_keys([
        'g-recaptcha-response', '_iconcaptcha-token', 'ic-rq', 
        'ic-wid', 'ic-cid', 'ic-hp', 'h-captcha-response',
        'c_captcha_response', 'pcaptcha_token', 'cf-turnstile-response'
    ], '');
    
    if ($login) {
        $data['action'] = 'login';
        $data['email'] = $email;
        $data['password'] = $password;
    } elseif ($faucet) {
        $data['action'] = 'claim_hourly_faucet';
        // Only add hash if key is not empty
        if (!empty($key)) {
            $data['hash'] = generate_hash($key);
        }
    }
    
    switch ($option) {
        case 0:
            if (!empty($iconToken)) {
                $iconResponse = solveIconCaptcha($iconToken, $host, $url, $captcha_url, $email, $proxy);
                if (!$iconResponse) return null;
                $data['captcha_type'] = 0;
                $data['_iconcaptcha-token'] = $iconResponse['_iconcaptcha-token'];
                $data['ic-rq'] = $iconResponse['ic-rq'];
                $data['ic-wid'] = $iconResponse['ic-wid'];
                $data['ic-cid'] = $iconResponse['ic-cid'];
                $type = "IconCaptcha";
            } else {
                // No captcha found, try turnstile
                $captchaResult = captcha($url, "0x4AAAAAABbyeJO9QkW9czUo", "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                    $data['captcha_type'] = 3;
                    $data['c_captcha_response'] = $captchaResult['token'];
                    $type = "TurnStile";
            }
            break;
        
        case 1:
            $captchaResult = captcha($url, "6LfVA0obAAAAAI8bLZBdotcvg-ms4heUAP1ebfjO", "recaptchav2");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            $data['captcha_type'] = 1;
            $data['g-recaptcha-response'] = $captchaResult['token'];
            $type = "ReCaptchaV2";
            break;
        
        case 2:
            $captchaResult = captcha($url, "e7342ebf-604d-4884-a967-0181277f2c5f", "hcaptcha");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            $data['captcha_type'] = 2;
            $data['h-captcha-response'] = $captchaResult['token'];
            $type = "Hcaptcha";
            break;
        
        case 3:
                $captchaResult = captcha($url, "0x4AAAAAABbyeJO9QkW9czUo", "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                $data['captcha_type'] = 3;
                $data['c_captcha_response'] = $captchaResult['token'];
                $type = "TurnStile";
            break;
        
        case 4:
            $solve = p_captcha_handler($email, $proxy, $host);
            if (empty($solve) || empty($solve['token'])) return null;
            $data['captcha_type'] = 3;
            $data['pcaptcha_token'] = $solve['token'];
            $csrf = $solve['csrf'];
            $type = "Pcaptcha"; 
            break;
        
        default:
            // No valid option - try turnstile as fallback
            $captchaResult = captcha($url, "0x4AAAAAABbyeJO9QkW9czUo", "turnstile");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                $data['captcha_type'] = 3;
                $data['c_captcha_response'] = $captchaResult['token'];
            $type = "TurnStile";
            break;
    }
    
    if ($login || $faucet) {
        if (!empty($csrf)) {
            $data['csrf_test_name'] = $csrf;
        }
        if ($login) $data['twofa'] = '';
        if ($faucet) $data['ft'] = null;
    }
    
    return ["payload" => $data, "Type" => $type];
}


function login($email, $password, $proxy = null) {
    $host = "dogepick.io";
    $url = "https://{$host}/login.php";
    $ua = saveData(APP_HOST, 'user-agent');
    $r = Run($url,headers(),null,null,$proxy,2,$email);

    if ($r['info']['http_code'] == 302) {
        return true;
    }
    
    if ($r['info']['http_code'] == 200) {
        $r = Run($url,headers(),null,null,$proxy,2,$email);
        $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
                
        $data = payload($r['body'], $email, $host, $url, $ua, $password, $proxy, false, true, $csrf, null, "https://{$host}/iconcaptcha.php");
        if (!$data) return false;
        
        $payload = http_build_query($data['payload']);
        $r = Run("https://{$host}/process.php",headerss(),$payload,null,$proxy,2,$email);
        $postResponse = $r['body'];
        if ($r['info']['http_code'] == 200) {
            $js = json_decode($postResponse, true);
            return true;
        }
        
        return false;
    }
    
    return false;
}

function faucet($email, $proxy = null) {
    while(true){
        $host = "dogepick.io";
        $url = "https://{$host}/faucet.php";
        $ua = saveData(APP_HOST, 'user-agent');
        $r =  Run($url,headers(),null,null,$proxy,2,$email);
        
        if ($r['info']['http_code'] == 302) {
            echo RED . "You're Cookie Has Been Expired Please Re-Login Again Thanks.\n";
            return false;
        }
    
        if ($r['info']['http_code'] == 200) {
            $r =  Run($url,headers(),null,null,$proxy,2,$email);
            $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
            if(empty($csrf)){continue;}
            
            $timer = 0;
            if (preg_match('/show_countdown_clock\((\d+)\)/', $r['body'], $timerMatch)) {
                $timer = intval($timerMatch[1]);
            }
        
            $key = '';
            if (preg_match("/get_hash\(event,'([^']+)'/", $r['body'], $matches)) {
                $key = $matches[1];
            } elseif (preg_match("/const hash = get_hash\(event, '([^']+)'/", $r['body'], $matches)) {
                $key = $matches[1];
            }
        
            if ($timer > 0) {
                countdown($timer,"⏳ Waiting ");
                continue;
            }
        
            $data = payload($r['body'], $email, $host, $url, $ua, null, $proxy, true, false, $csrf, $key, "https://{$host}/iconcaptcha.php");
            if (!$data) return ['success' => false, 'timer' => 0];
        
            $payload = http_build_query($data['payload']);
            $GLOBALS['faucet_totalclaims']++;
            $r = Run("https://{$host}/process.php",headerss(),$payload,null,$proxy,2,$email);
            $postResponse = $r['body'];
            if ($r['info']['http_code'] == 200) {
                preg_match_all('/\{.*?\}/', $postResponse, $matches);
                $js = null;
                foreach ($matches[0] as $json) {
                    $decoded = json_decode($json, true);
                    if (isset($decoded['ret']) && $decoded['ret'] == 1) {
                        $js = $decoded;
                        break;
                    }
                }
                if ($js) {
                    $GLOBALS['faucet_claims']++;
                    echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . GREEN . $js['mes'] . WHITE . ' | ' . YELLOW . get_balance() . RESET .  "\n";
                }
            }
        }
    }
}


function generate_hash($key) {
    $x = rand(40, 260);
    $y = rand(100, 380);
    $timestamp = time();
    $raw = "$x:$y:$timestamp";
    $encrypted = '';
    $len = strlen($key);

    for ($i = 0; $i < strlen($raw); $i++) {
        $encrypted .= chr(
            ord($raw[$i]) ^ ord($key[$i % $len])
        );
    }

    return base64_encode($encrypted);
}

function p_captcha_handler($email, $proxy, $host) {
    $r = Run("https://{$host}/generate_pcaptcha.php",headers(),null,null,$proxy,2,$email);
    
    $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
    $r = json_decode($r['body'], true);

    $imageResponse = Run($r['image'],headers(),null,null,$proxy,2,$email);
    $imageData = $imageResponse['body'];
    
    $indices = pcaptcha_solve($imageData, $r['question']);
    
    if (empty($indices)) {
        return p_captcha_handler($email, $proxy, $host);
    }
    
    $selected = json_encode($indices);
    
    $req = "action=verify_pcaptcha&selected_boxes=" . urlencode($selected) . "&csrf_test_name=" . $csrf;
    $r = Run("https://{$host}/process.php",headerss(),$req,null,$proxy,2,$email);
    $csrf = explode(';', explode('csrf_cookie_name=', $r['header']['set-cookie'])[1])[0];
    $r = json_decode($r['body'], true);
    
    if ($r['message'] == "Success!") {
        return ["token" => $r['token'], "csrf" => $csrf];
    }
    return null;
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