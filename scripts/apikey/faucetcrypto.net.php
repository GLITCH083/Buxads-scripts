<?php

if(!function_exists('bx_root')) {
function bx_root($directory) {
    for($i=0;$i<6;$i++){if(is_file($directory.'/functions/function.php'))return $directory;$directory=dirname($directory);}
    throw new RuntimeException('Keep this script inside the Buxads-scripts folder.');
}
function bx_storage_root($root,$home,$platform) {
    return $platform!=='Windows'&&is_string($home)&&$home!==''?rtrim($home,'/').'/.buxads':$root;
}
function bx_initialize($root,$storage=null) {
    if(defined('BUXADS_DATA_DIR'))return BUXADS_DATA_DIR;
    $data=$storage??bx_storage_root($root,getenv('HOME'),PHP_OS_FAMILY);
    foreach(['information','configs'] as $folder){
        $target=$data.'/'.$folder;
        if(!is_dir($target)&&!@mkdir($target,0700,true)&&!is_dir($target))throw new RuntimeException('Cannot create private storage: '.$target);
        if($data!==$root&&is_dir($root.'/'.$folder)&&!is_file($data.'/.migrated')){
            $source=$root.'/'.$folder;
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                if(!$file->isFile()||$file->isLink())continue;
                $relative=substr($file->getPathname(),strlen($source)+1);
                if(str_starts_with(str_replace('\\','/',$relative),'instant-backups/'))continue;
                $destination=$target.'/'.$relative;if(file_exists($destination))continue;
                $parent=dirname($destination);if(!is_dir($parent)&&!@mkdir($parent,0700,true)&&!is_dir($parent))throw new RuntimeException('Cannot create storage folder.');
                if(!@copy($file->getPathname(),$destination))throw new RuntimeException('Cannot migrate existing saved files.');
                @chmod($destination,0600);
            }
        }
    }
    if($data!==$root&&!is_file($data.'/.migrated'))file_put_contents($data.'/.migrated','1',LOCK_EX);
    define('BUXADS_DATA_DIR',$data);return $data;
}
function bx_card($title,$rows,$color="\033[96m") {
    $width=max(38,min(68,(int)(getenv('COLUMNS')?:68)));$inner=$width-4;
    echo $color.'╭'.str_repeat('─',$width-2)."╮\n";
    foreach(array_merge([strtoupper($title),''],(array)$rows) as $row){
        foreach(explode("\n",wordwrap((string)$row,$inner,"\n",true)) as $line)echo $color.'│ '."\033[97m".str_pad($line,$inner).$color." │\n";
    }
    echo $color.'╰'.str_repeat('─',$width-2)."╯\033[0m\n";
}
}



date_default_timezone_set("Asia/Karachi");

if(!defined("APP_HOST"))define("APP_HOST", "buxads-Bot");
if(!defined("BASE_DIR"))define("BASE_DIR", bx_root(__DIR__));
bx_initialize(BASE_DIR);

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
$GLOBALS['ptc_claims'] = 0;
$GLOBALS['ptc_totalclaims'] = 0;

function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BUXADS_DATA_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/faucetcrypto.net.txt";
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
    $rows=[];foreach(loadAccounts() as $i=>$account){$label=$account['email']??$account['name']??('Account '.($i+1));$rows[]=($i+1).'. '.$label.' | '.(!empty($account['proxy'])?'proxy':'direct');}
    bx_card('Saved accounts', $rows?:['No accounts saved.']);
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
    bx_card('FaucetCrypto / BuxAds', ['ACCOUNTS & REWARDS', '1  Run account', '2  Add account', '3  Delete account', '4  View accounts', '5  Exit']);
    echo "Choose: ";
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
        "origin: https://faucetcrypto.net",
        "referer: https://faucetcrypto.net/"
    ];
}

function login($email,$password,$proxy){
    $r = Run("https://faucetcrypto.net/login",headers(),null,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://faucetcrypto.net/dashboard"){
        return true;
    }

    $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="', $r['body'])[1])[0];
    $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
    $cap = captcha("https://faucetcrypto.net/login", $sitekey, "hcaptcha");
    if(!$cap['token']){
        return false;
    }
    $token = $cap['token'];

    $payload = http_build_query([
        "csrf_token_name" => $csrf,
        "email" => $email,
        "password" => $password,
        "captcha" => "hcaptcha",
        "g-recaptcha-response" => $token,
        "h-captcha-response" => $token
    ]);

    $r = Run("https://faucetcrypto.net/auth/login",headerss(),$payload,null,$proxy,2,$email);

    if($r['info']['redirect_url'] == "https://faucetcrypto.net/dashboard"){
        return true;
    }
}

function balance($email, $proxy) {
    $html = Run("https://faucetcrypto.net/dashboard", headers(), null, null, $proxy, 2, $email)['body'];

    if (preg_match('/<h3[^>]*>(\d+)\s*tokens?<\/h3>/i', $html, $matches)) {
        return $matches[1];
    }

    if (preg_match('/Balance.*?(\d+)/s', $html, $matches)) {
        return $matches[1];
    }

    return 0;
}

function faucet($email,$proxy){
    while(true){
        $r = Run("https://faucetcrypto.net/faucet",headers(),null,null,$proxy,2,$email);

        if(str_contains($r['body'],'You need to claim at least 1 shortlink to claim faucet.')){
            ptc($email,$proxy);
            shortlinks($email,$proxy);
            continue;
        }

        $timer = explode(' - 1;',explode('var wait = ', $r['body'])[1])[0];
        if($timer){countdown($timer,"⏳ Waiting ");continue;}


        $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" id="token" value="', $r['body'])[1])[0];
        $_token = explode('"',explode('<input type="hidden" name="token" value="', $r['body'])[1])[0];
    
        $bot = antibot($r['body']);
        if(!$bot){continue;}
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://freesolana.top/faucet", $sitekey, "hcaptcha");
        if(!$cap['token']){continue;}
        $token = $cap['token'];

        $payload = http_build_query([
            "antibotlinks" => $bot,
            "csrf_token_name" => $csrf,
            "token" => $_token,
            "captcha" => "hcaptcha",
            "g-recaptcha-response" => $token,
            "h-captcha-response" => $token
        ]);

        $r = Run("https://faucetcrypto.net/faucet/verify",headerss(),$payload,null,$proxy,2,$email);
        $r = Run("https://faucetcrypto.net/faucet",headers(),null,null,$proxy,2,$email);

        $msg = explode("', 'success')",explode("Swal.fire('Good job!', '", $r['body'])[1])[0];
        
        if ($msg) {
            $GLOBALS['failed'] = 0;
            $GLOBALS['faucet_claims']++;
            echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . GREEN . $msg . WHITE . " | " . balance($email,$proxy) . " | " . YELLOW  . get_balance() . RESET . "\n";
        }else{
            $GLOBALS['failed']++;
            if($GLOBALS['failed'] == 3){
                exit("3 Incorrect Claims\n");
            }
        }
    }
}

function ptc($email,$proxy){
    $r = Run("https://faucetcrypto.net/ptc",headers(),null,null,$proxy,2,$email);
    while(true){
        
        $viewId = explode("'",explode("window.location = 'https://faucetcrypto.net/ptc/view/", $r['body'])[1])[0];
        if(empty($viewId)){return;}

        $r = Run("https://faucetcrypto.net/ptc/view/{$viewId}",headers(),null,null,$proxy,2,$email);
        $timer = explode(';',explode('var timer = ', $r['body'])[1])[0];
        countdown($timer,"⏳ Waiting ");
        $redirect_url = explode("';",explode("var url = '", $r['body'])[1])[0];
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://faucetcrypto.net/ptc/view/{$viewId}", $sitekey, "hcaptcha");
        if(!$cap['token']){
            return ptc($email,$proxy);
        }
        $token = $cap['token'];
        $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="', $r['body'])[1])[0];
        $_token = explode('"',explode('<input type="hidden" name="token" value="', $r['body'])[1])[0];
        $payload = http_build_query([
            "captcha" => "hcaptcha",
            "g-recaptcha-response" => $token,
            "h-captcha-response" => $token,
            "csrf_token_name" => $csrf,
            "token" => $_token
        ]);

        $r = Run("https://faucetcrypto.net/ptc/verify/{$viewId}",headerss(),$payload,null,$proxy,2,$email);
        $r = Run("https://faucetcrypto.net/ptc",headers(),null,null,$proxy,2,$email);

        $msg = explode("', 'success')",explode("Swal.fire('Good job!', '", $r['body'])[1])[0];
        $GLOBALS['ptc_totalclaims']++;
        if ($msg) {
            $GLOBALS['ptc_claims']++;
            echo WHITE . '[ ' . GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . WHITE . ' ] ' . GREEN . $redirect_url . RESET . "\n";
            echo WHITE . '[ ' . GREEN . $GLOBALS['ptc_claims'] . WHITE . " / " . YELLOW . $GLOBALS['ptc_totalclaims'] . WHITE . ' ] ' . GREEN . $msg . WHITE . " | " . balance($email,$proxy) . " | " . YELLOW  . get_balance() . RESET . "\n";
        }
    }
}

function shortlinks($email, $proxy) {
    $r = Run("https://faucetcrypto.net/links", headers(), null, null, $proxy, 2, $email);

    // 🔧 More robust pattern:
    // - <h4> with ANY class containing "card-title"
    // - <a> with ANY attributes, capturing href
    // - <span> with ANY class containing "badge", capturing the numerator
    preg_match_all(
        '/<h4[^>]*class="[^"]*card-title[^"]*"[^>]*>(.*?)<\/h4>.*?' .
        '<a[^>]*href="([^"]+)"[^>]*>.*?' .
        '<span[^>]*class="[^"]*badge[^"]*"[^>]*>(\d+)\/\d+<\/span>/s',
        $r['body'],
        $matches,
        PREG_SET_ORDER
    );

    $items = [];
    foreach ($matches as $match) {
        $items[] = [
            'name'  => trim($match[1]),
            'link'  => $match[2],
            'claim' => $match[3]
        ];
    }

    // If still empty, fallback to DOMDocument for reliability
    if (empty($items)) {
        $items = parseShortlinksWithDOM($r['body']);
    }

    foreach ($items as $detail) {
        $url = $detail['link'];
        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== 'faucetcrypto.net') continue;

        $allowed = ['Shorlinks Wall','Adlink', 'Shrinkme', 'Clk.sh', 'Shrinkearn'];
        $nameOk = false;
        foreach ($allowed as $needle) {
            if (stripos($detail['name'], $needle) !== false) {
                $nameOk = true;
                break;
            }
        }
        if (!$nameOk) continue;

        $r = Run($url, headers(), null, null, $proxy, 0, $email);
        $redirect = $r['info']['redirect_url'];
        $bypass = Bypass($redirect);
        if ($bypass['token']) {
            countdown(rand(45, 60), "");
            $r = Run($bypass['token'], headers(), null, null, $proxy, 0, $email);
            if ($r['info']['redirect_url']) {
                $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 0, $email);
                if ($r['info']['redirect_url']) {
                    $r = Run($r['info']['redirect_url'], headers(), null, null, $proxy, 0, $email);
                }
            }
        }

        // Extract success message safely
        $msg = '';
        if (strpos($r['body'], "Swal.fire('Good job!', '") !== false) {
            $parts = explode("Swal.fire('Good job!', '", $r['body']);
            if (isset($parts[1])) {
                $msg = explode("', 'success')", $parts[1])[0] ?? '';
            }
        }

        $GLOBALS['shortlink_totalclaims']++;
        if ($msg) {
            $GLOBALS['shortlink_claims']++;
            echo WHITE . '[ ' . GREEN . $GLOBALS['shortlink_claims'] . WHITE . " / " . YELLOW . $GLOBALS['shortlink_totalclaims'] . WHITE . ' ] ' . '| ' . WHITE . $redirect . ' | ' . GREEN . $msg . RESET . "\n";
            return;
        }
    }
}

function parseShortlinksWithDOM($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $cards = $xpath->query("//div[contains(@class, 'col-lg-3')]");
    $items = [];

    foreach ($cards as $card) {
        $h4 = $xpath->query(".//h4[contains(@class, 'card-title')]", $card)->item(0);
        $a = $xpath->query(".//a[contains(@class, 'btn')]", $card)->item(0);
        $span = $xpath->query(".//span[contains(@class, 'badge')]", $card)->item(0);

        if ($h4 && $a && $span) {
            $name = trim($h4->textContent);
            $link = $a->getAttribute('href');
            preg_match('/(\d+)\/\d+/', $span->textContent, $match);
            $claim = $match[1] ?? null;
            $items[] = compact('name', 'link', 'claim');
        }
    }
    return $items;
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