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
    return $infoDir . "/gamefaucet.fun.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  gamefaucet.fun AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
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
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
    ];
}

function headerss(){
    return [
        "accept-language: en-US,en;q=0.9",
        "content-type: application/x-www-form-urlencoded",
        "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "origin: https://gamefaucet.fun",
        "referer: https://gamefaucet.fun/"
    ];
}

function login($email, $password = '', $proxy) {
    $r = Run("https://gamefaucet.fun",headers(),null,null,$proxy,0,$email);
      
    if ($r['info']['redirect_url'] == "https://gamefaucet.fun/dashboard") {
        return true;
    }
    
    if ($r['info']['http_code'] == 200) {
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://gamefaucet.fun", $sitekey, "hcaptcha");
        
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
        
        $r = Run("https://gamefaucet.fun/auth/login",headerss(),$payload,null,$proxy,0,$email);
        
        $r = Run("https://gamefaucet.fun",headers(),null,null,$proxy,0,$email);

        if ($r['info']['redirect_url'] == "https://gamefaucet.fun/dashboard") {
            return true;
        }
    }
    return false;
}

function faucet($email, $proxy) {
  
    
    while (true) {
       
        $r = Run("https://gamefaucet.fun/faucet/doge",headers(),null,null,$proxy,0,$email);

        if ($r['info']['redirect_url']) {
            shortlink($email, $proxy);
            continue;
        }
        
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
            
            if ($timer) {
                countdown($timer,"⏳ Waiting ");
                continue;
            }
            
            // Extract form tokens
            $csrf = explode('"', explode('<input type="hidden" name="ci_csrf_token" id="token" value="', $body)[1])[0];
            $token = explode('"', explode('<input type="hidden" name="token" value="', $body)[1])[0];
            $cur = explode('"', explode('<input type="hidden" name="currency" value="', $body)[1])[0];
            $rscaptcha_token = explode('"', explode('<input type="hidden" name="rscaptcha_token" value="', $body)[1])[0];
            $captcha = rs_upsidedown($body, "https://rscaptcha.com/assets/generated_captcha/");
            
            if (!$captcha['rs-response']) {
                continue;
            }
            
            // Prepare payload
            $ipData = json_decode(Run("http://ip-api.com/json/?fields=query,timezone", [], null, "data", $proxy, 2, $email)['body'] ?? '{}', true);
            $ip = $ipData['query'] ?? '';
            $timezone = $ipData['timezone'] ?? '';
            $ls = 'en-US,en';
            $userAgent = SaveData(APP_HOST, 'UserAgent');
            $utt = $timezone;
            $uf = md5($ip . $userAgent . $ls . $utt);
            
            $payload = http_build_query([
                "ci_csrf_token" => $csrf,
                "token" => $token,
                "currency" => $cur,
                "captcha" => "rscaptcha",
                "rscaptcha_token" => $rscaptcha_token,
                "rscaptcha_response" => $captcha['rs-response'],
                "uf" => $uf,
                "utt" => $utt,
                "ls" => $ls
            ]);
            
            $r = Run("https://gamefaucet.fun/faucet/verify",headerss(),$payload,null,$proxy,0,$email);
            
            $r = Run("https://gamefaucet.fun/faucet/doge",headers(),null,null,$proxy,0,$email);
        
            $msg = explode("',", explode("text: '", $r['body'])[1])[0] ?? '';
            
            $GLOBALS['faucet_totalclaims']++;
            if ($msg) {
                $claimCount++;
                $GLOBALS['faucet_claims']++;
                echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . GREEN . $msg . RESET . "\n";
                sleep(5);
            }
        }
    }
}

function shortlink($email, $proxy) {
    while (true) {
        $r = Run("https://gamefaucet.fun/links/doge",headers(),null,null,$proxy,0,$email);

        preg_match_all('/<h5>(.*?)<\/h5>/', $r['body'], $names);
        preg_match_all('/<\/i>\s*(\d+)\/\d+\s*<\/div>/', $r['body'], $claims);
        preg_match_all('/<a href="(.*?)" class="btn_sl" >Click To Visit<\/a>/', $r['body'], $urls);

        $items = [];
        foreach ($names[1] as $index => $name) {
            $items[] = [
                'name'  => trim($name),
                'claim' => $claims[1][$index] ?? null,
                'link'  => $urls[1][$index] ?? null
            ];
        }

    
        foreach ($items as $detail) {
            $url = $detail['link'];
            $host = parse_url($url, PHP_URL_HOST);

            if ($host !== 'gamefaucet.fun') continue;

            $allowed = ['Adlink', 'Shrinkme', 'Clksh', 'Shrinkearn'];
            $nameOk = false;
            foreach ($allowed as $needle) {
                if (stripos($detail['name'], $needle) !== false) {
                    $nameOk = true;
                    break;
                }
            }
            if (!$nameOk) continue;
            $r = Run($url,headers(),null,null,$proxy,0,$email);

            $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
            $cap = captcha($url, $sitekey, "hcaptcha")['token'];
            if (!$cap) return;

            $csrf = explode('"', explode('<input type="hidden" name="ci_csrf_token" id="token" value="', $r['body'])[1])[0];
            $token = explode('"', explode('<input type="hidden" name="token" value="', $r['body'])[1])[0];

            $payload = http_build_query([
                "ci_csrf_token" => $csrf,
                "token" => $token,
                "captcha" => "hcaptcha",
                "g-recaptcha-response" => $cap,
                "h-captcha-response" => $cap
            ]);
            $r = Run($url,headerss(),$payload,null,$proxy,0,$email);

            $redirect = $r['info']['redirect_url'];
            $bypass = Bypass($redirect);
            if ($bypass['original_url']) {
                countdown(rand(45,60), "");
                $r = Run($bypass['original_url'],headers(),null,null,$proxy,0,$email);
                if ($r['info']['redirect_url']) {
                    $r = Run($r['info']['redirect_url'],headers(),null,null,$proxy,0,$email);
                    if ($r['info']['redirect_url']) {
                        $r = Run($r['info']['redirect_url'],headers(),null,null,$proxy,0,$email);
                    }
                }
            }
            $msg = explode("',", explode("text: '", $r['body'])[1])[0] ?? '';

            $GLOBALS['shortlink_totalclaims']++;
            if ($msg) {
                $GLOBALS['shortlink_claims']++;
                echo WHITE . '[ ' . GREEN . $GLOBALS['shortlink_claims'] . WHITE . " / " . YELLOW . $GLOBALS['shortlink_totalclaims'] . WHITE . ' ] ' . '| ' . WHITE . $redirect . ' | ' . GREEN . $msg . RESET . "\n";
                return;
            }
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