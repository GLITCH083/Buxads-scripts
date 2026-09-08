<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
define("BASE_DIR", dirname(__DIR__));

error_reporting(0);

if (!file_exists(BASE_DIR . "/functions/function.php")) {
    die("ERROR: functions/function.php not found!\n");
}

if (!file_exists(BASE_DIR . "/functions/captcha.php")) {
    die("ERROR: functions/captcha.php not found!\n");
}

include_once BASE_DIR . "/functions/function.php";
include_once BASE_DIR . "/functions/captcha.php";

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
$GLOBALS['ptc_claims'] = 0;
$GLOBALS['ptc_totalclaims'] = 0;

function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/makeyoutask.com.txt";
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
    echo CYAN . "║" . WHITE . "  🚀  makeyoutask.com AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
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

function headerss($host,$x = false){
    $hd = [
        "accept-language: en-US,en;q=0.9",
        "content-type: application/x-www-form-urlencoded",
        "user-agent: " . saveData(APP_HOST,'user-agent'),
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "origin: https://$host",
        "referer: https://$host/"
    ];
    if($x){
        $hd[] = "x-requested-with: XMLHttpRequest";
    }
    return $hd;
}

function login($email,$password,$proxy){
    $r = Run("https://makeyoutask.com/login",headers(),null,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://makeyoutask.com/dashboard"){
        return true;
    }

    $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="', $r['body'])[1])[0];
    $sitekey = explode('"',explode('sitekey="', $r['body'])[1])[0];
    $cap = captcha("https://makeyoutask.com/login", $sitekey, "turnstile");
    if(!$cap['token']){
        return false;
    }
    $token = $cap['token'];

    $payload = http_build_query(["csrf_token_name" => $csrf,"email" => $email,"password" => $password,"captcha" => "turnstile","cf-turnstile-response" => $token]);

    $r = Run("https://makeyoutask.com/auth/login",headerss("makeyoutask.com"),$payload,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://makeyoutask.com/dashboard"){
        return true;
    }
}

function balance($email, $proxy) {
    $html = Run("https://makeyoutask.com/dashboard", headers(), null, null, $proxy, 2, $email)['body'];

    // Match the <h3> with these classes and capture the number before "Token" or "tokens"
    // Example: match a number inside any element that contains "Balance" or "Token"
    if (preg_match('/<[^>]*class="[^"]*kpi-value[^"]*text-success[^"]*"[^>]*>\s*(\d+)\s*Tokens?/i', $html, $matches)) {
        return $matches[1];
    }

    // Fallback: just find any number near the word "Balance" or in the <h3>
    if (preg_match('/Balance.*?(\d+)/s', $html, $matches)) {
        return $matches[1];
    }

    return 0;
}

function ptc($email, $proxy) {
    while (true) {
        // 1. Get the PTC page
        $r = Run("https://makeyoutask.com/ptc", headers(), null, null, $proxy, 2, $email);
        $body = $r['body'];

        // 2. Extract the first "Watch Video" link (class="btn-watch-action")
        $watchUrl = '';
        // Pattern: any <a> tag with class containing "btn-watch-action", capture the href
        if (preg_match('/<a[^>]*class\s*=\s*["\']([^"\']*btn-watch-action[^"\']*)["\'][^>]*href\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
            $watchUrl = $matches[2]; // second capture group is href
        }

        // Fallback: if regex fails, try domain-specific extraction (for old & new domains)
        if (empty($watchUrl)) {
            // Try to find any link that starts with https:// and contains "tecnoblogs", "sefablog", or "turkblog"
            $patterns = [
                '/https?:\/\/tecnoblogs\.online\/[^\s"\']+/i',
                '/https?:\/\/sefablog\.online\/[^\s"\']+/i',
                '/https?:\/\/turkblog\.online\/[^\s"\']+/i',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $body, $matches)) {
                    $watchUrl = $matches[0];
                    break;
                }
            }
        }

        // If still empty, something is wrong – exit loop
        if (empty($watchUrl)) {
            echo "No watch link found on page.\n";
            return;
        }

        // 3. Visit the watch URL
        $r = Run($watchUrl, headers(), null, null, $proxy, 2, $email);

        // 4. Follow any redirect (if present)
        if (!empty($r['info']['redirect_url'])) {
            $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 2, $email);
        }

        // 5. Extract form action, CSRF token, timer, and sitekey
        $body = $r['body'];

        // Form action
        $action = '';
        if (preg_match('/<form[^>]*action\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
            $action = $matches[1];
        }

        // CSRF token
        $csrf = '';
        if (preg_match('/<input[^>]*name\s*=\s*["\']csrf_token_name["\'][^>]*value\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
            $csrf = $matches[1];
        }

        // Countdown timer
        $timer = 0;
        if (preg_match('/const\s+countdownTime\s*=\s*(\d+)/i', $body, $matches)) {
            $timer = (int)$matches[1];
            countdown($timer, "⏳ Waiting ");
        } else {
            // fallback: try other patterns
            if (preg_match('/countdownTime\s*=\s*(\d+)/i', $body, $matches)) {
                $timer = (int)$matches[1];
                countdown($timer, "⏳ Waiting ");
            }
        }

        // Sitekey (turnstile)
        $sitekey = '';
        if (preg_match('/sitekey\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
            $sitekey = $matches[1];
        }

        if (empty($sitekey)) {
            // Sometimes sitekey is in a data attribute or similar
            if (preg_match('/data-sitekey\s*=\s*["\']([^"\']+)["\']/i', $body, $matches)) {
                $sitekey = $matches[1];
            }
        }

        // If no sitekey, maybe captcha not required – skip solving
        $token = '';
        if (!empty($sitekey)) {
            $cap = captcha($r['info']['url'] ?? $watchUrl, $sitekey, "turnstile");
            if (!$cap || empty($cap['token'])) {
                return false;
            }
            $token = $cap['token'];
        }

        // 6. Submit the form
        $payload = http_build_query([
            "captcha" => "turnstile",
            "cf-turnstile-response" => $token,
            "csrf_token_name" => $csrf,
        ]);

        // Determine host for headers
        $host = parse_url($action, PHP_URL_HOST) ?: parse_url($watchUrl, PHP_URL_HOST);
        $r = Run($action, headerss($host), $payload, null, $proxy, 2, $email);

        // 7. Parse result
        $msg = '';
        if (strpos($r['body'], "html: '") !== false) {
            $msg = strip_tags(explode("',", explode("html: '", $r['body'])[1])[0]);
        }
        $redirect_url = '';
        if (strpos($r['body'], 'window.location.href = "') !== false) {
            $redirect_url = explode('"', explode('window.location.href = "', $r['body'])[1])[0];
        }

        // 8. Update global counters and output
        $GLOBALS['ptc_totalclaims']++;
        if ($msg) {
            $GLOBALS['ptc_claims']++;
            echo WHITE . '[ ' . GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . WHITE . ' ] ' . GREEN . $redirect_url . RESET . "\n";
            echo WHITE . '[ ' . GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . WHITE . ' ] ' . GREEN . $msg . WHITE . " | " . balance($email, $proxy) . " | " . YELLOW . get_balance() . RESET . "\n";
        }
    }
}

function autoWatch($email,$proxy){

    $r = Run("https://makeyoutask.com/SmmNew/watch",headers(),null,null,$proxy,2,$email);
    if(!$r['info']['redirect_url']) return;
    
    $r = Run($r['info']['redirect_url'],headers(),null,null,$proxy,2,$email);
    $r = Run($r['info']['redirect_url'],headers(),null,null,$proxy,2,$email);

    $csrf = explode("';",explode("let csrfHash = '", $r['body'])[1])[0];
    $action = explode("',",explode("url: '",$r['body'])[1])[0];
    if(!$action) return null;

    while(true){
        countdown(60,"⏳ Waiting ");
        $payload = http_build_query(["csrf_token_name" => $csrf]);
        
        $r = Run($action,headerss("sefablog.online",true),$payload,null,$proxy,2,$email);

        $js = json_decode($r['body'],true);

        $GLOBALS['ptc_totalclaims']++;
            if ($js['status'] == "success") {
            $GLOBALS['ptc_claims']++;
            echo WHITE . '[ ' . GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . WHITE . ' ] ' . GREEN . $js['message'] . WHITE . " | " . balance($email,$proxy) . " | " . YELLOW  . get_balance() . RESET . "\n";
            if($js['refresh']){
                return;
            }
        }else{
            return;
        }
        $csrf = $js['csrf_token'];
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
        while(true){
        ptc($email, $proxy);
        autoWatch($email,$proxy);
        countdown(60,"Retrying In ");
        }
    } else {
        echo RED . "✗ Login Failed for: " . $email . "\n" . RESET;
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