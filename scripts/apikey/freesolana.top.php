<?php
if (!function_exists(__NAMESPACE__.'\\bx_live_start')) {
function bx_live_width($text) {
    return function_exists('mb_strwidth') ? mb_strwidth($text,'UTF-8') : preg_match_all('/./us',$text);
}
function bx_live_render() {
    $s=&$GLOBALS['BUXADS_LIVE_BOX'];
    $width=max(38,min(78,(int)(getenv('COLUMNS')?:68)));
    $inside=$width-4;$limit=max(6,min(20,(int)(getenv('LINES')?:26)-6));
    $rows=[];
    foreach(array_merge($s['history'],[$s['pending']]) as $row) {
        $chars=preg_split('//u',$row,-1,PREG_SPLIT_NO_EMPTY);$part='';
        foreach($chars?:[] as $char) {
            if(bx_live_width($part.$char)>$inside) {$rows[]=$part;$part='';}
            $part.=$char;
        }
        $rows[]=$part;
    }
    $rows=array_slice($rows,-$limit);
    $title='BUXADS • LIVE ACTIVITY';
    $out='';
    if($s['drawn']) $out="\r\033[".($s['height']-2+($s['shift']??0))."A\033[J";
    $out.="\033[96m╭".str_repeat('─',$width-2)."╮\n";
    $out.='│ '."\033[97m".$title.str_repeat(' ',max(0,$inside-bx_live_width($title)))."\033[96m │\n";
    $out.='├'.str_repeat('─',$width-2)."┤\n";
    foreach($rows as $row) {
        $color=preg_match('/\b(error|failed|failure|ERR)\b/i',$row)?'91':(preg_match('/\b(wait|waiting|cooldown|pending|next turn)\b/i',$row)?'93':(preg_match('/\b(OK|success|successfully|confirmed|reward|you got)\b/i',$row)?'92':'97'));
        $out.="\033[96m│ \033[{$color}m".$row.str_repeat(' ',max(0,$inside-bx_live_width($row)))."\033[96m │\n";
    }
    $out.='╰'.str_repeat('─',$width-2)."╯\033[0m\n";
    $s['height']=count($rows)+4;$s['drawn']=true;$s['shift']=0;
    // Keep the input cursor inside the final content row.
    $out.="\033[2A\033[".(3+min($inside,bx_live_width($s['pending']))).'G';
    return $out;
}
function bx_live_output($chunk,$phase=0) {
    $s=&$GLOBALS['BUXADS_LIVE_BOX'];
    $finish=str_contains($chunk,"\x1eBX_END\x1e");
    if($chunk===''&&($phase & PHP_OUTPUT_HANDLER_FINAL)&&empty($s['drawn'])) return '';
    $chunk=str_replace("\x1eBX_END\x1e",'',$chunk);
    $clean=preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/','',$chunk);
    $clean=str_replace(["\x08","\x07"],'',$clean);
    foreach(preg_split('/(\r\n|\n|\r)/',$clean,-1,PREG_SPLIT_DELIM_CAPTURE) as $piece) {
        if($piece==="\r") {$s['pending']='';continue;}
        if($piece==="\n"||$piece==="\r\n") {
            $line=trim($s['pending']);$s['pending']='';
            $line=preg_replace('/^[│║]\s*|\s*[│║]$/u','',$line);
            if($line!==''&&!preg_match('/^[╭╮╰╯┌┐└┘├┤╔╗╚╝═─━┬┴┼╠╣╦╩╬\s]+$/u',$line)) {
                if(!preg_match('/^\[(INFO|WAIT|OK|ERR)\]/',$line)&&preg_match('/^\[|\b(failed|error|cooldown|waiting|pending|confirmed|successfully|you got|next turn)\b/i',$line)) {
                    $tag=preg_match('/\b(error|failed|failure)\b/i',$line)?'ERR':(preg_match('/\b(wait|waiting|cooldown|pending|next turn)\b/i',$line)?'WAIT':(preg_match('/\b(OK|success|successfully|confirmed|reward|you got)\b/i',$line)?'OK':'INFO'));
                    $line='['.$tag.'] '.$line;
                }
                $s['history'][]=$line;
                if(empty($s['manual'])&&preg_match('/you got|claim.{0,25}success|success.{0,25}claim|reward confirmed|\bearned\b|\bfailed\b/i',$line)) $finish=true;
            }
            $s['history']=array_slice($s['history'],-80);
        } else $s['pending'].=$piece;
    }
    $out=bx_live_render();
    if($finish||($phase & PHP_OUTPUT_HANDLER_FINAL)) {
        $out.="\033[2B\r\n";
        $s=['history'=>[],'pending'=>'','drawn'=>false,'height'=>0,'shift'=>0,'manual'=>false];
    }
    return $out;
}
function bx_interrupt_setup() {
    if(PHP_OS_FAMILY==='Windows') return;
    if(function_exists('pcntl_async_signals')&&function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        $stop=function($signal) {
            echo "\n[INFO] Stopped by user.\n";
            bx_live_finish();
            exit(128+$signal);
        };
        pcntl_signal(SIGINT,$stop,false);
        pcntl_signal(SIGTERM,$stop,false);
    }
    if(function_exists('stream_isatty')&&stream_isatty(STDIN)&&function_exists('exec')) @exec("stty isig intr '^C' -ixon 2>/dev/null");
}
function bx_interrupt_exec($handle) {
    if(PHP_OS_FAMILY!=='Windows'&&function_exists('pcntl_signal_dispatch')) {
        curl_setopt($handle,CURLOPT_NOPROGRESS,false);
        curl_setopt($handle,CURLOPT_XFERINFOFUNCTION,function($curl,$downloadSize,$downloaded,$uploadSize,$uploaded){pcntl_signal_dispatch();return 0;});
    }
    return curl_exec($handle);
}
function bx_interrupt_read() {
    if(PHP_OS_FAMILY==='Windows'||!function_exists('pcntl_signal_dispatch')) return \readline('');
    stream_set_blocking(STDIN,false);$result='';
    try {
        while(true) {
            pcntl_signal_dispatch();
            $part=fgets(STDIN);
            if($part!==false) {$result.=$part;if(str_contains($part,"\n")) return rtrim($result,"\r\n");}
            elseif(feof(STDIN)) return $result===''?false:$result;
            usleep(100000);
        }
    } finally {stream_set_blocking(STDIN,true);}
}

function bx_live_start() {
    if(isset($GLOBALS['BUXADS_LIVE_BOX'])) return;
    $GLOBALS['BUXADS_LIVE_BOX']=['history'=>[],'pending'=>'','drawn'=>false,'height'=>0,'shift'=>0];
    ob_start(__NAMESPACE__.'\\bx_live_output',1);
    bx_interrupt_setup();
}
function bx_live_readline($prompt='') {
    echo $prompt;
    $value=bx_interrupt_read();
    if(function_exists('stream_isatty')&&stream_isatty(STDIN)) $GLOBALS['BUXADS_LIVE_BOX']['shift']=1;
    echo "\n";
    return $value;
}
function bx_live_finish() {
    if(!empty($GLOBALS['BUXADS_LIVE_BOX']['drawn'])) echo "\x1eBX_END\x1e";
}
function bx_live_begin() {
    bx_live_finish();
    $GLOBALS['BUXADS_LIVE_BOX']['manual']=true;
}
}
bx_live_start();











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


function getAccountsFile() {
    // Create information folder if it doesn't exist
    $infoDir = BUXADS_DATA_DIR . "/information";
    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0755, true);
    }
    return $infoDir . "/freesolana.top.txt";
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
    bx_card('FreeSolana / BuxAds', ['ACCOUNTS & REWARDS', '1  Run account', '2  Add account', '3  Delete account', '4  View accounts', '5  Exit']);
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
        "origin: https://freesolana.top",
        "referer: https://freesolana.top/"
    ];
}

function balance($email,$proxy){

    $html = Run("https://freesolana.top/dashboard", headers(), null, null, $proxy, 2, $email)['body'];
    
    $ex = explode('"', explode('<div class="display-4 fs-2 mb-2 fw-normal font-sans-serif text-warning preview-balance" value="', $html)[1])[0];
    $displayValue = explode('</div>', explode('<div class="display-4 fs-2 mb-2 fw-normal font-sans-serif text-warning preview-balance" value="' . $ex . '">', $html)[1])[0];

    return $displayValue;
}

function login($email,$password,$proxy){

    $r = Run("https://freesolana.top/login",headers(),null,null,$proxy,0,$email);

    if($r['info']['redirect_url'] == "https://freesolana.top/dashboard"){
        return true;
    }

    $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" value="',$r['body'])[1])[0];
    $rs_token = explode('"',explode('<input type="hidden" name="rscaptcha_token" value="', $r['body'])[1])[0];
    $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
    $cap = captcha("https://freesolana.top/login", $sitekey, "hcaptcha");
    if(!$cap['token']){
        return false;
    }

    $payload = http_build_query(["csrf_token_name" => $csrf,"email" => $email,"password" => $password,"captcha" => "hcaptcha","g-recaptcha-response" => $cap['token'],"h-captcha-response" => $cap['token'],"rscaptcha_token" => $rs_token,"rscaptcha_response" => ""]);

    $r = Run("https://freesolana.top/auth/login",headerss(),$payload,null,$proxy,0,$email);

    if($r['info']['redirect_url'] == "https://freesolana.top/dashboard"){
        return true;
    }
}

function faucet($email,$proxy){
    while(true){

        $r = Run("https://freesolana.top/faucet",headers(),null,null,$proxy,0,$email);

        $keywords = ['You need to complete at least','need to claim at least 1 shortlink','shortlink to claim faucet'];
        $html = strtolower($r['body']);
        foreach($keywords as $key){
            if(str_contains($html,strtolower($key))){
                shortlink($email,$proxy);
                continue;
            }
        }

        $timer = explode(' - 1;',explode('var wait = ', $r['body'])[1])[0];
        if($timer){countdown($timer,"⏳ Waiting ");continue;}

        $csrf = explode('"',explode('<input type="hidden" name="csrf_token_name" id="token" value="', $r['body'])[1])[0];
        $token = explode('"',explode('<input type="hidden" name="token" value="', $r['body'])[1])[0];

        $rs_token = explode('"',explode('<input type="hidden" name="rscaptcha_token" value="', $r['body'])[1])[0];
        $bot = antibot($r['body']);
        if(!$bot){continue;}
        $sitekey = explode('"', explode('<div class="h-captcha" data-sitekey="', $r['body'])[1])[0];
        $cap = captcha("https://freesolana.top/faucet", $sitekey, "hcaptcha");
        if(!$cap['token']){continue;}

        $payload = http_build_query(["antibotlinks" => $bot,"csrf_token_name" => $csrf,"token" => $token,"captcha" => "hcaptcha","g-recaptcha-response" => $cap['token'],"h-captcha-response" => $cap['token'],"rscaptcha_token" => $rs_token,"rscaptcha_response" => ""]);

        $r = Run("https://freesolana.top/faucet/verify",headerss(),$payload,null,$proxy,0,$email);
        $r = Run("https://freesolana.top/faucet",headers(),null,null,$proxy,0,$email);
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


function shortlink($email,$proxy){

    $r = Run("https://freesolana.top/links",headers(),null,null,$proxy,2,$email);

    $linkid = explode('"', explode('<a href="https://freesolana.top/links/go/', $r['body'])[1])[0];

    $r = Run("https://freesolana.top/links/go/{$linkid}",headers(),null,null,$proxy,2,$email);

    $redirect = $r['info']['redirect_url'];

    if($redirect){
        animation("Bypassing: $redirect");
        $bypass = bypass($redirect);
        if($bypass['token']){
            countdown(rand(45,90),"⏳ Waiting ");
            $r = Run($bypass['token'], headers(), null, null, $proxy, 0, $email);
            if ($r['info']['redirect_url']) {
                $r = Run($r['info']['redirect_url'],headers(),null,null,$proxy,0,$email);
                if ($r['info']['redirect_url']) {
                    $r = Run($r['info']['redirect_url'],headers(),null,null,$proxy,0,$email);
                }
            }
        }
        $msg = explode("', 'success')",explode("Swal.fire('Good job!', '", $r['body'])[1])[0];
        $GLOBALS['shortlink_totalclaims']++;
        if ($msg) {
            $GLOBALS['shortlink_claims']++;
            echo WHITE . '[ ' . GREEN . $GLOBALS['shortlink_claims'] . WHITE . " / " . YELLOW . $GLOBALS['shortlink_totalclaims'] . WHITE . ' ] ' . '| ' . WHITE . $redirect . ' | ' . GREEN . $msg . RESET . "\n";
            return;
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