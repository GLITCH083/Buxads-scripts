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
    while(count($rows)<$limit) array_unshift($rows,'');
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
    $s['height']=$limit+4;$s['drawn']=true;$s['shift']=0;
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

// ================== FIX 1: ADD API_URL ==================
define("API_URL", "https://mini.keran.co/api.php");
// ========================================================

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


if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
if (!defined("api_endpoint")) define("api_endpoint", "http://37.60.224.60:7860/api");

function generateDeviceId() {
    return bin2hex(random_bytes(32)); // 64 hex chars
}


function getStatsFile() {
    $infoDir = BUXADS_DATA_DIR . "/information";
    if (!is_dir($infoDir)) mkdir($infoDir, 0755, true);
    return $infoDir . "/account_stats.json";
}

function loadStats($accountId) {
    $file = getStatsFile();
    $all = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $all = json_decode($content, true) ?: [];
    }
    return $all[$accountId] ?? ['claims' => 0, 'totalclaims' => 0];
}

function saveStats($accountId, $stats) {
    $file = getStatsFile();
    $all = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $all = json_decode($content, true) ?: [];
    }
    $all[$accountId] = $stats;
    file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT));
}

function updateStats($accountId, $claimed, $total) {
    $stats = loadStats($accountId);
    $stats['claims'] += $claimed;
    $stats['totalclaims'] += $total;
    saveStats($accountId, $stats);
    return $stats;
}

// ---------- Account Management ----------

function getAccountsFile() {
    $infoDir = BUXADS_DATA_DIR . "/information";
    if (!is_dir($infoDir)) mkdir($infoDir, 0755, true);
    return $infoDir . "/faucetfi-mini.keran.co.txt";
}

function loadAccounts() {
    $file = getAccountsFile();
    if (!file_exists($file)) {
        file_put_contents($file, "# Format: initData|name|deviceId|proxy(optional)\n");
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $accounts = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('|', trim($line));
        if (count($parts) >= 3) {
            $accounts[] = [
                'initData' => trim($parts[0]),
                'name'     => trim($parts[1]),
                'deviceId' => trim($parts[2]),
                'proxy'    => isset($parts[3]) ? trim($parts[3]) : null
            ];
        }
    }
    return $accounts;
}

function saveAccounts($accounts) {
    $file = getAccountsFile();
    $content = "# Format: initData|name|deviceId|proxy(optional)\n";
    foreach ($accounts as $acc) {
        $line = $acc['initData'] . '|' . $acc['name'] . '|' . $acc['deviceId'];
        if (!empty($acc['proxy'])) $line .= '|' . $acc['proxy'];
        $content .= $line . "\n";
    }
    file_put_contents($file, $content);
}

function addAccount($initData, $name, $deviceId, $proxy = null) {
    $accounts = loadAccounts();
    foreach ($accounts as $acc) if ($acc['initData'] === $initData) return false;
    $accounts[] = ['initData' => $initData, 'name' => $name, 'deviceId' => $deviceId, 'proxy' => $proxy];
    saveAccounts($accounts);
    // Initialize stats for this account
    saveStats($initData, ['claims' => 0, 'totalclaims' => 0]);
    return true;
}

function deleteAccount($initData) {
    $accounts = loadAccounts();
    $new = [];
    $deleted = false;
    foreach ($accounts as $acc) {
        if ($acc['initData'] !== $initData) $new[] = $acc;
        else $deleted = true;
    }
    if ($deleted) {
        saveAccounts($new);
        // Optionally delete stats
        $file = getStatsFile();
        if (file_exists($file)) {
            $all = json_decode(file_get_contents($file), true) ?: [];
            if (isset($all[$initData])) unset($all[$initData]);
            file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT));
        }
    }
    return $deleted;
}

function displayAccounts() {
    $rows=[];foreach(loadAccounts() as $i=>$account){$label=$account['email']??$account['name']??('Account '.($i+1));$rows[]=($i+1).'. '.$label.' | '.(!empty($account['proxy'])?'proxy':'direct');}
    bx_card('Saved accounts', $rows?:['No accounts saved.']);
}

function selectAccount() {
    $accounts = loadAccounts();
    if (empty($accounts)) { echo YELLOW . "No accounts available!\n" . RESET; return null; }
    displayAccounts();
    echo WHITE . "Select account (1-" . count($accounts) . "): " . RESET;
    $choice = trim(fgets(STDIN));
    if (!is_numeric($choice) || $choice < 1 || $choice > count($accounts)) {
        echo RED . "Invalid selection!\n" . RESET;
        return null;
    }
    return $accounts[$choice - 1];
}

// ---------- API Headers ----------

function getApiHeaders() {
    return [
        "user-agent: " . saveData(APP_HOST, 'user-agent'),
        "content-type: application/json",
        "accept: */*",
        "referer: https://mini.keran.co/",
        "accept-language: en-US,en;q=0.9",
        "origin: https://mini.keran.co",
        "sec-ch-ua: \"Chromium\";v=\"139\", \"Not;A=Brand\";v=\"99\"",
        "sec-ch-ua-platform: \"Android\"",
        "sec-ch-ua-mobile: ?1",
        "sec-fetch-site: same-origin",
        "sec-fetch-mode: cors",
        "sec-fetch-dest: empty"
    ];
}

// ---------- API Functions ----------

function getDetails($initData, $deviceId, $proxy = null) {
    $headers = getApiHeaders();
    $payload = json_encode([
        "action"   => "get_user_data",
        "initData" => $initData,
        "deviceId" => $deviceId
    ]);
    $r = Run(API_URL, $headers, $payload, null, $proxy,2,$name);
    return json_decode($r['body'], true);
}

function getFaucetSpin($initData, $deviceId, $mode, $proxy = null) {
    $headers = getApiHeaders();
    $payload = json_encode([
        "action"   => "get_faucet_spin_reward",
        "initData" => $initData,
        "deviceId" => $deviceId,
        "mode"     => $mode
    ]);
    $r = Run(API_URL, $headers, $payload, null, $proxy,2,$name);
    return json_decode($r['body'], true);
}

function getDouble($initData,$deviceId,$mode,$gameToken,$proxy = null){
    $headers = getApiHeaders();
    
    $payload = json_encode(["action" => "start_double_reward","initData" => $initData,"deviceId" => $deviceId,"mode" => $mode,"gameToken" => $gameToken]);

    $r = Run(API_URL, $headers, $payload, null, $proxy,2,$name);
    $js = json_decode($r['body'], true);

    if(!$js['status'] == "success") return false;

    $challenge = $js['challenge'];


    $captchaData = captcha("https://mini.keran.co", "0x4AAAAAAACAEtFrYI5hvlhN", "turnstile", "double_reward");
    $token = $captchaData['token'] ?? null;

    if(!$token){
        $captchaData = captcha("https://mini.keran.co", "0x4AAAAAAACAEtFrYI5hvlhN", "turnstile", "double_reward");
        $token = $captchaData['token'] ?? null;
    }

    if (!$token) {
        return ['status' => 'error', 'message' => 'Failed to get CAPTCHA token'];
    }

    $payload = json_encode(["action" => "complete_double_reward","initData" => $initData,"deviceId" => $deviceId,"mode" => $mode,"gameToken" => $gameToken,"adProof" => "","challenge" => $challenge,"captchaToken" => $token]);

    $r = Run(API_URL, $headers, $payload, null, $proxy,2,$name);
    $js = json_decode($r['body'], true);

    if(!$js['status'] == "success") return false;

    return true;
}

function claimFaucet($initData, $deviceId, $mode, $proxy = null) {
    $headers = getApiHeaders();

    $spin = getFaucetSpin($initData, $deviceId, $mode, $proxy);

    $attempts = 0;
    
    while ($spin['status'] != "success" && $attempts < 3) {
        countdown(30,"");
        $spin = getFaucetSpin($initData, $deviceId, $mode, $proxy);
        $attempts++;
    }
    if ($spin['status'] != "success") {
        return ["status" => false,"message" => "Not Able To Spin"]; // or an error message
    }

    $double = getDouble($initData,$deviceId,$mode,$spin['gameToken'],$proxy);
    if(!$double){return ['status' => 'error', 'message' => 'Request failed'];}


    $captchaData = captcha("https://mini.keran.co", "0x4AAAAAAACAEtFrYI5hvlhN", "turnstile", "faucet_claim");

    $token = $captchaData['token'] ?? null;

    if(!$token){
        $captchaData = captcha("https://mini.keran.co", "0x4AAAAAAACAEtFrYI5hvlhN", "turnstile", "faucet_claim");
        $token = $captchaData['token'] ?? null;
    }

    if (!$token) {
        return ['status' => 'error', 'message' => 'Failed to get CAPTCHA token'];
    }


    $payload = json_encode([
        "action"       => "claim_faucet",
        "initData"     => $initData,
        "deviceId"     => $deviceId,
        "captchaToken" => $token,
        "mode"         => $mode,
        "gameToken" => $spin['gameToken']
    ]);

    $r = Run(API_URL, $headers, $payload, null, $proxy, 2, null); // remove undefined $name
    if (!isset($r['body'])) {
        return ['status' => 'error', 'message' => 'Request failed'];
    }
    return json_decode($r['body'], true);
}

// ---------- Main Faucet Loop (Infinite per Account) ----------

function runFaucet($account) {
    $initData = $account['initData'];
    $name     = $account['name'];
    $deviceId = $account['deviceId'];
    $proxy    = $account['proxy'] ?? null;
    $accountId = $initData; // use initData as unique ID for stats

    $modeEmoji = [
        'card'     => '🃏', 'roll'   => '🎲', 'wheel'   => '🎡',
        'box'      => '📦', 'scratch' => '🎯', 'target'  => '🎯',
        'chest'=> '🪙', 'meteor'    => '🦀'
    ];

    echo "\n" . CYAN . "═══════════════════════════════════════════════════════════════\n" . RESET;
    echo WHITE . "  👤 Running: " . GREEN . $name . RESET . "\n";
    if ($proxy) echo WHITE . "  🌐 Proxy: " . CYAN . $proxy . RESET . "\n";
    echo CYAN . "═══════════════════════════════════════════════════════════════\n" . RESET;

    // Load initial stats for display
    $stats = loadStats($accountId);
    $GLOBALS['faucet_claims'] = $stats['claims'];
    $GLOBALS['faucet_totalclaims'] = $stats['totalclaims'];

    while (true) {
        // Fetch latest user data
        $details = getDetails($initData, $deviceId, $proxy);
        if (empty($details['data']['balance'])) {
            echo RED . "✗ Failed to fetch user data. Check initData. Exiting loop.\n" . RESET;
            break;
        }

        $balance = $details['data']['balance'];
        $coin    = $details['data']['preferred_coin'] ?? 'COIN';
        $games   = $details['data']['faucet_modes'] ?? [];

        echo "\n" . WHITE . "💰 Balance: " . GREEN . "$balance $coin\n" . RESET;

        $cooldowns = [];

        foreach ($games as $mode) {
            $cooldown = $details['data']["faucet_cooldown_remaining_$mode"] ?? 0;
            $cooldowns[$mode] = $cooldown;

            if (empty($cooldown)){

            $emoji = $modeEmoji[$mode] ?? '🎮';
            animation("🎯 Playing: $mode $emoji");

            // Attempt to claim
            $result = claimFaucet($initData, $deviceId, $mode, $proxy);

            // Increment total attempts
            $GLOBALS['faucet_totalclaims']++;
            $total = $GLOBALS['faucet_totalclaims'];

            if (isset($result['status']) && $result['status'] == 'success') {
                $reward = $result['claimed_amount'] ?? 0;
                $balance += $reward;
                $GLOBALS['faucet_claims']++; // successful claim

                // Update stats file
                updateStats($accountId, 1, 1); // +1 claim, +1 total
                $stats = loadStats($accountId);
                $GLOBALS['faucet_claims'] = $stats['claims'];
                $GLOBALS['faucet_totalclaims'] = $stats['totalclaims'];

                $msg = "+{$reward} {$coin}";
                // Display in the requested format
                echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . GREEN . $msg . WHITE . " | " . YELLOW . $balance . " $coin" . RESET . " | " . get_balance() . RESET . "\n";
                countdown(60,"");
            } else {
                $msg = $result['message'] ?? 'unknown error';
                echo RED . "✗ Claim failed for $mode: $msg\n" . RESET;
                $stats = loadStats($accountId);
                $stats['totalclaims']++;
                saveStats($accountId, $stats);
                $GLOBALS['faucet_totalclaims'] = $stats['totalclaims'];
                // Display with 0 claim increment
                echo WHITE . '[ ' . GREEN . $GLOBALS['faucet_claims'] . WHITE . " / " . YELLOW . $GLOBALS['faucet_totalclaims'] . WHITE . ' ] ' . RED . "Failed" . WHITE . " | " . YELLOW . $balance . " $coin" . RESET . "\n";
            }
        }
    }

        // Determine minimum cooldown
        $minCooldown = null;
        foreach ($cooldowns as $mode => $cd) {
            if ($cd > 0 && ($minCooldown === null || $cd < $minCooldown)) {
                $minCooldown = $cd;
            }
        }

        if ($minCooldown !== null) {
            $wait = $minCooldown + 2;
            countdown($wait,"");
        } else {
            countdown(60,"");
        }
    }
}

// ---------- Menu ----------

function menu() {
    bx_card('FaucetFi Telegram / BuxAds', ['ACCOUNTS & REWARDS', '1  Run account', '2  Add account', '3  Delete account', '4  View accounts', '5  Exit']);
    echo "Choose: ";
}

// ---------- Main Execution (CLI only) ----------

if (php_sapi_name() !== 'cli') die("This script must be run from the command line!\n");

// Set default user-agent if not set
if (!saveData(APP_HOST, 'user-agent')) {
    saveData(APP_HOST, 'user-agent', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36');
}

while (true) {
    menu();
    $option = trim(fgets(STDIN));
    switch ($option) {
        case '1': // Run Account
            $acc = selectAccount();
            if ($acc) runFaucet($acc);
            break;

        case '2': // Add Account
            echo WHITE . "Enter initData (from Telegram WebApp): " . RESET;
            $initData = trim(fgets(STDIN));
            if (empty($initData)) {
                echo RED . "initData cannot be empty!\n" . RESET;
                break;
            }
            echo WHITE . "Enter your name (or press Enter for auto): " . RESET;
            $name = trim(fgets(STDIN));
            if (empty($name)) {
                $name = "User_" . substr(md5($initData), 0, 6);
                echo YELLOW . "Using auto-generated name: $name\n" . RESET;
            }
            $deviceId = generateDeviceId();
            echo WHITE . "Generated deviceId: " . CYAN . $deviceId . RESET . "\n";
            echo WHITE . "Enter proxy (optional): " . RESET;
            $proxy = trim(fgets(STDIN)) ?: null;

            if (addAccount($initData, $name, $deviceId, $proxy)) {
                echo GREEN . "✓ Account added successfully!\n" . RESET;
            } else {
                echo RED . "✗ Account already exists!\n" . RESET;
            }
            echo WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
            break;

        case '3': // Delete Account
            $accounts = loadAccounts();
            if (empty($accounts)) {
                echo YELLOW . "No accounts to delete.\n" . RESET;
            } else {
                displayAccounts();
                echo WHITE . "Enter the initData or name to delete: " . RESET;
                $search = trim(fgets(STDIN));
                $found = false;
                foreach ($accounts as $acc) {
                    if ($acc['initData'] === $search || $acc['name'] === $search) {
                        if (deleteAccount($acc['initData'])) {
                            echo GREEN . "✓ Deleted.\n" . RESET;
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) echo RED . "✗ Not found.\n" . RESET;
            }
            echo WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
            break;

        case '4': // View Accounts
            displayAccounts();
            echo WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
            break;

        case '5': // Exit
            echo GREEN . "Goodbye!\n" . RESET;
            exit(0);

        default:
            echo RED . "Invalid option!\n" . RESET;
            sleep(1);
    }
}