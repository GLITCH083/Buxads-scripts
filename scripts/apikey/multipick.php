<?php
namespace BuxAdsMultiPick;
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










error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
date_default_timezone_set("Asia/Karachi");
define(__NAMESPACE__."\\"."APP_HOST", "Multi-claimer");

if(!function_exists('bx_root')) {
function bx_root($directory) {
    for($i=0;$i<6;$i++){if(is_file($directory.'/functions/function.php'))return $directory;$directory=dirname($directory);}
    throw new RuntimeException('Keep this script inside the Buxads-scripts folder.');
}
function bx_storage_root($root,$home,$platform) {
    return $platform!=='Windows'&&is_string($home)&&$home!==''?rtrim($home,'/').'/.buxads':$root;
}
function bx_initialize($root,$storage=null) {
    if(defined(__NAMESPACE__.'\\BUXADS_DATA_DIR'))return BUXADS_DATA_DIR;
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
    define(__NAMESPACE__."\\".'BUXADS_DATA_DIR',$data);return $data;
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

$pickRoot=bx_root(__DIR__);
define(__NAMESPACE__."\\"."BASE_DIR", bx_initialize($pickRoot));
define(__NAMESPACE__."\\".'KEYLOCK_KEY', 'ASHANFAISALRAFI');

// ======================== VERNUABLE API ENDPOINTS ========================
define(__NAMESPACE__."\\"."api_in",  "https://vernuable.my.id/in.php");
define(__NAMESPACE__."\\"."api_res", "https://vernuable.my.id/res.php");
define(__NAMESPACE__."\\"."api_endpoint", api_in);

// ======================== SINGLE API KEY ========================
if (!defined(__NAMESPACE__."\\anticaptcha_key")) define(__NAMESPACE__."\\"."anticaptcha_key", saveData("buxads-Bot", "anticaptcha-apikey"));

// ======================== HOST CONFIGURATIONS ========================
$hosts = [
    'tonpick.game' => ['rv2_sitekey'=>null,'hcap_sitekey'=>null,'turnstile_sitekey'=>null],
    'bchpick.io' => [
        'rv2_sitekey' => '6Lc0tAwtAAAAAFbyDcWfY0682xbFMiOGUzvJZu5z',
        'hcap_sitekey' => '46cc953e-0746-4ac0-a906-94f66b3216a0',
        'turnstile_sitekey' => '0x4AAAAAADexuS24rGq6WGDh'
    ],
    'bnbpick.io' => [
        'rv2_sitekey' => '6Le5mIwlAAAAADmaHuGxaf5KTEiWwG9FhQex0JDc',
        'hcap_sitekey' => '1cf1ac2d-e962-4efc-a097-265d3b11ed32',
        'turnstile_sitekey' => '0x4AAAAAAA0_O3uScCqtpqXl'
    ],
    'dogepick.io' => [
        'rv2_sitekey' => '6LfVA0obAAAAAI8bLZBdotcvg-ms4heUAP1ebfjO',
        'hcap_sitekey' => 'e7342ebf-604d-4884-a967-0181277f2c5f',
        'turnstile_sitekey' => '0x4AAAAAABbyeJO9QkW9czUo'
    ],
    'litepick.io' => [
        'rv2_sitekey' => '6LdF080aAAAAAAGrGLP6tj70rOwRMPMM0ZU021u5',
        'hcap_sitekey' => '1cf1ac2d-e962-4efc-a097-265d3b11ed32',
        'turnstile_sitekey' => '0x4AAAAAAA0-UWDHOKP0OrgS'
    ],
    'solpick.io' => [
        'rv2_sitekey' => '6LdfNx8rAAAAAIkedgGnuX6TIRANDEDA2fsIjx3s',
        'hcap_sitekey' => 'f67eb825-e7db-46b8-a48c-819b59bad81c',
        'turnstile_sitekey' => '0x4AAAAAAA9HXS0xZCemCoe9'
    ],
    'suipick.io' => [
        'rv2_sitekey' => '6Lc_sVwrAAAAAOorkzR_84W5Iy5E8kEX1wiGRkfU',
        'hcap_sitekey' => '7ccaf614-6f10-4f9c-9ca4-2d4ce04c70ca',
        'turnstile_sitekey' => '0x4AAAAAABgtwLBJbn9NePjw'
    ],
    'polpick.io' => [
        'rv2_sitekey' => '6LcHOR8rAAAAAFBzOKHRFY6yLoilRi-JyGnQdUtq',
        'hcap_sitekey' => '962e0432-59ea-44d2-a672-33d42086290a',
        'turnstile_sitekey' => '0x4AAAAAAA8_cZ-8EiewkNNb'
    ],
    'tronpick.io' => [
        'rv2_sitekey' => '6LeBFBclAAAAANoZIrwXU1cPgYDDM7f1ehHpzXWj',
        'hcap_sitekey' => '8628f615-3f07-4c0b-a889-c8a20544e46e',
        'turnstile_sitekey' => '0x4AAAAAAAW74HiAaujGhyeV'
    ],
];

saveData(APP_HOST, 'UserAgent');

// ======================== COLOR DEFINITIONS ========================
define(__NAMESPACE__."\\"."RED", "\033[1;31;40m");
define(__NAMESPACE__."\\"."GREEN", "\033[1;32;40m");
define(__NAMESPACE__."\\"."YELLOW", "\033[1;33;40m");
define(__NAMESPACE__."\\"."BLUE", "\033[1;34;40m");
define(__NAMESPACE__."\\"."PURPLE", "\033[1;35;40m");
define(__NAMESPACE__."\\"."CYAN", "\033[1;36;40m");
define(__NAMESPACE__."\\"."GREY", "\033[1;30;40m");
define(__NAMESPACE__."\\"."WHITE", "\033[1;37m");
define(__NAMESPACE__."\\"."BOLD_YELLOW", "\033[1;33m");
define(__NAMESPACE__."\\".'MAGENTA', "\033[35m");
define(__NAMESPACE__."\\"."MONO", "\033[2;37;40m");
define(__NAMESPACE__."\\"."EMONO", "\033[0;37;40m");
define(__NAMESPACE__."\\"."ITALIC", "\033[3;37;40m");
define(__NAMESPACE__."\\"."UNDERLINE_CYAN", "\033[4;36m");
define(__NAMESPACE__."\\"."BLINKING_GREEN", "\033[5;32m");
define(__NAMESPACE__."\\"."GLOWING_PURPLE", "\033[1;35;5m");
define(__NAMESPACE__."\\"."GLOWING_WHITE", "\033[1;37;5m");
define(__NAMESPACE__."\\"."BG_RED", "\033[41m");
define(__NAMESPACE__."\\"."BG_GREEN", "\033[42m");
define(__NAMESPACE__."\\"."BG_BLUE", "\033[44m");
define(__NAMESPACE__."\\"."NEWLINE", "\n");
define(__NAMESPACE__."\\"."RESET", WHITE);
define(__NAMESPACE__."\\"."BG_END", "\033[0m");

// ======================== UTILITY FUNCTIONS ========================
function linee() {
    return str_repeat("═", 105) . NEWLINE;
}

function fast($arr) {
    $char = str_split($arr);
    foreach($char as $animated) {
        echo $animated;
        usleep(5000);
    }
}

function animation($message) {
    $width = 60;
    $msg_effect = substr($message, 0, $width);
    $wait = strlen($message) <= $width;

    first_part($msg_effect, $width, $wait);

    if (strlen($message) > $width) {
        for ($i = $width; $i < strlen($message); $i++) {
            $msg_effect = substr($msg_effect, 1) . $message[$i];
            echo "\r" . $msg_effect;
            flush();
            usleep(100000);
        }
    }
    sleep(1);
    echo "\r" . str_repeat(' ', $width) . "\r";
    flush();
}

function first_part($message, $width, $wait) {
    $animated_message = str_pad($message, $width, ' ', STR_PAD_BOTH);
    $msg_effect = "";
    
    for ($i = 0; $i < strlen($animated_message) - 1; $i++) {
        $msg_effect .= $animated_message[$i];
        echo "\r" . $msg_effect;
        flush();
        usleep(30000);
    }

    if ($wait) {
        sleep(1);
    }
}

function countdown($duration, $message) {
    $totalDuration = $duration;  
    $startTime = microtime(true);  
    $arrowLength = 1;  

    while (true) {  
        $elapsedTime = microtime(true) - $startTime;  
        $remainingTime = $duration - $elapsedTime;  

        if ($remainingTime <= 0) {  
            $remainingTime = 0;  
            break;  
        }  

        $remainingInt = (int) $remainingTime;  
        $hours   = floor($remainingInt / 3600);  
        $minutes = floor(($remainingInt % 3600) / 60);  
        $seconds = $remainingInt % 60;  

        if (floor($elapsedTime) >= $arrowLength) {  
            $arrowLength = (floor($elapsedTime) % 5) + 1;  
        }  
        $arrow = str_repeat('=', $arrowLength - 1) . '>';  

        printf("\r%s %02d:%02d:%02d %s", $message, $hours, $minutes, $seconds, $arrow);  
        usleep(200000);
    }  
    echo "\r                                                   \r";  
}

function clear() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        passthru('cls');
    } else {
        passthru('clear');
    }
}

// ======================== CORE FUNCTIONS ========================
function Run($url, $head = 0, $post = 0, $data = "data", $proxy = 0, $returnType = 2, $name = "") {
    $host = parse_url($url, PHP_URL_HOST);
    $folder = BASE_DIR . "/configs/{$host}-config";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    if (!empty($proxy)) {  
        $proxyForHost = trim($proxy);  
        if (!preg_match('#^https?://#i', $proxyForHost)) {  
            $proxyForHost = "http://" . $proxyForHost;  
        }  
        $parts = parse_url($proxyForHost);  
        $hostPart = $parts['host'] ?? '';  

        if (!empty($hostPart)) {  
            $proxyPart = $hostPart;  
        } else {  
            $proxyForHost = preg_replace('/^.*@/', '', trim($proxy));  
            $proxyPart = preg_replace('/[^a-zA-Z0-9._-]/', '_', $proxyForHost);  
            $proxyPart = preg_replace('/_+/', '_', $proxyPart);  
            $proxyPart = trim($proxyPart, '_');  
        }  

        if (!empty($name)) {  
            $cookieFile = $folder . "/{$name}-{$proxyPart}-cookie.txt";  
        } else {  
            $cookieFile = $folder . "/{$proxyPart}-cookie.txt";  
        }  
    } else {  
        $cookieFile = $folder . "/" . (!empty($name) ? "{$name}-cookie.txt" : "cookie.txt");  
    }  

    $maxRetries = $post ? 1 : 3;  
    $retryDelay = 1;  
    $attempt = 0;  
    $response = false;  
    $info = [];  
    $curlErr = '';  

    do {  
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);  
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);  
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);  
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);  

        if (!empty($proxy)) {  
            $proxy = trim($proxy);  
            if (!preg_match('#^https?://#i', $proxy)) {  
                $proxyWithScheme = "http://{$proxy}";  
            } else {  
                $proxyWithScheme = $proxy;  
            }  

            $parts = parse_url($proxyWithScheme);  
            if (!empty($parts['host']) && !empty($parts['port'])) {  
                curl_setopt($ch, CURLOPT_PROXY, $parts['host'] . ":" . $parts['port']);  
                if (!empty($parts['user']) && !empty($parts['pass'])) {  
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $parts['user'] . ":" . $parts['pass']);  
                }  
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);  
            }  
        }  

        if ($post) {  
            curl_setopt($ch, CURLOPT_POST, true);  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);  
        }  

        if ($head && is_array($head)) {  
            curl_setopt($ch, CURLOPT_HTTPHEADER, $head);  
        }  

        curl_setopt($ch, CURLOPT_HEADER, true);  

        $response = bx_interrupt_exec($ch);  
        $info = curl_getinfo($ch);  
        $curlErr = curl_error($ch);  

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);  
        $responseHeader = substr($response, 0, $headerSize);  
        $responseBody = substr($response, $headerSize);  
          
        if ($response !== false && $info['http_code'] != 0) {  
            break;  
        }  

        $attempt++;  
        if ($attempt < $maxRetries) {  
            sleep($retryDelay);  
        }  

    } while ($attempt < $maxRetries);  

    if ($response === false || $info['http_code'] == 0) {  
        return [  
            "body" => "Request failed after {$maxRetries} attempts. Error: " . ($curlErr ?: 'No response'),  
            "info" => $info  
        ];  
    }  

    if ($returnType == 1) {  
        return $responseBody;  
    } elseif ($returnType == 2) {  
        return [  
            "body" => $responseBody,  
            "info" => $info  
        ];  
    } else {  
        return [  
            "header" => $responseHeader,  
            "body" => $responseBody,  
            "info" => $info  
        ];  
    }  
}

function saveData($folder, $filename) {
    $base = BASE_DIR . "/configs/{$folder}-config";
    if (!is_dir($base)) mkdir($base, 0777, true);

    $path = $base . "/$filename";
    if (file_exists($path)) {
        return file_get_contents($path);
    } else {
        $data = $filename === 'UserAgent' ? 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36' : bx_live_readline("Input $filename: ");
        file_put_contents($path, $data);
        return $data;
    }
}

function getCookieString($host, $email) {
    $folder = BASE_DIR . "/configs/" . $host . "-config";
    $cookieFile = $folder . "/$email-cookie.txt";
    $cfCookieFile = $folder . "/cf_cookie.txt";

    $cookies = [];

    if (is_file($cookieFile)) {
        $lines = file($cookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if ($line[0] === '#') {
                if (strpos($line, '#HttpOnly_') === 0) {
                    $line = substr($line, 10);
                } else {
                    continue;
                }
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 7) continue;

            list($domain, $flag, $path, $secure, $expiration, $name, $value) = $parts;

            if (
                $domain !== $host &&
                strpos($domain, '.' . $host) === false &&
                strpos($host, ltrim($domain, '.')) === false
            ) {
                continue;
            }

            if ($expiration != 0 && $expiration < time()) {
                continue;
            }

            if (isset($cookies[$name])) {
                if (strlen($path) > strlen($cookies[$name]['path'])) {
                    $cookies[$name] = ['value' => $value, 'path' => $path];
                }
            } else {
                $cookies[$name] = ['value' => $value, 'path' => $path];
            }
        }
    }

    if (is_file($cfCookieFile)) {
        $lines = file($cfCookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;

            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) == 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                $cookies[$name] = [
                    'value' => $value,
                    'path'  => '/'
                ];
            }
        }
    }

    if (empty($cookies)) {
        return '';
    }

    $cookieStr = '';
    foreach ($cookies as $name => $data) {
        $cookieStr .= $name . '=' . $data['value'] . '; ';
    }

    return rtrim($cookieStr, '; ');
}

function getCfCookieAndUserAgent($host, $proxy = null) {
    $folder = BASE_DIR . "/configs/{$host}-config";
    if (!is_dir($folder)) return null;

    $cfCookie = null;
    $cookieFile = $folder . "/cf_cookie.txt";

    if (!empty($proxy)) {
        $proxyCookieFile = $folder . "/{$proxy}-cf_cookies.txt";
        if (file_exists($proxyCookieFile)) {
            $cookieFile = $proxyCookieFile;
        }
    }

    if (file_exists($cookieFile)) {
        $cfCookie = trim(file_get_contents($cookieFile));
    }

    $uaFile = $folder . "/userAgent";
    $userAgent = file_exists($uaFile) ? trim(file_get_contents($uaFile)) : null;

    return [
        'cf_cookie' => $cfCookie,
        'user_agent' => $userAgent
    ];
}

function get_cookies($host, $proxy = null, $email = null) {
    global $siteCookies;
    $cookieHeader = '';
    if (isset($siteCookies[$host])) {
        $cookieHeader = $siteCookies[$host];
    } elseif (!empty($cookies['cf_cookie'])) {
        $cookieHeader = str_replace(" ", "=", $cookies['cf_cookie']);
    }
    if (empty($cookieHeader)) {
        $cookieHeader = getCookieString($host, $email);
    }
    return $cookieHeader;
}

function parseProxy($proxy) {
    if (empty($proxy)) return [null, null, null];

    $fullProxy = $proxy;
    $host = null;
    $port = null;

    if (strpos($proxy, '@') !== false) {
        $parts = explode('@', $proxy, 2);
        $hostPort = $parts[1];
    } else {
        $hostPort = $proxy;
    }

    $hostPortParts = explode(':', $hostPort);
    $host = $hostPortParts[0];
    $port = isset($hostPortParts[1]) ? $hostPortParts[1] : null;

    return [$host, $port, $fullProxy];
}

function getRequestHeaders($host, $proxy = null, $email = null) {
    [$proxyHost, $proxyPort, $fullProxy] = parseProxy($proxy);
    $proxyKey = empty($proxyHost) ? '' : $proxyHost . '-' . $proxyPort;
    
    $cookies = getCfCookieAndUserAgent($host, $proxyKey);

    $userAgent = saveData(APP_HOST, 'UserAgent');
    if (!empty($cookies['user_agent'])) {
        $userAgent = $cookies['user_agent'];
    }

    $cookieString = '';
    if (!empty($cookies['cf_cookie'])) {
        $cookieString = str_replace(" ", "=", $cookies['cf_cookie']) . ";";
    }

    $cookieString .= get_cookies($host, $proxy, $email);

    $header = [
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "User-Agent: " . $userAgent,
    ];
    
    if ($cookieString) {
        $header[] = "cookie: " . $cookieString;
    }
    
    return $header;
}

function getFormHeaders($host, $html, $proxy = null, $email = null) {
    [$proxyHost, $proxyPort, $fullProxy] = parseProxy($proxy);
    $proxyKey = empty($proxyHost) ? '' : $proxyHost . '-' . $proxyPort;
    
    $cookies = getCfCookieAndUserAgent($host, $proxyKey);     

    $userAgent = saveData(APP_HOST, 'UserAgent');
    if (!empty($cookies['user_agent'])) {
        $userAgent = $cookies['user_agent'];
    }

    $cookieString = '';
    if (!empty($cookies['cf_cookie'])) {
        $cookieString = str_replace(" ", "=", $cookies['cf_cookie']) . ";";
    }

    $cookieString .= get_cookies($host, $fullProxy, $email);

    $headers = [
        "User-Agent: " . $userAgent,
        "Referer: https://$host/",
        "Origin: https://$host",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "cookie: " . $cookieString
    ];

    if (str_contains($html, 'XMLHttpRequest')) {
        $headers[] = "x-requested-with: XMLHttpRequest";
    }

    return $headers;
}

// ======================== CAPTCHA FUNCTIONS (VERNUABLE API) ========================
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return sprintf(
        '%08s-%04s-%04s-%04s-%12s',
        bin2hex(substr($data, 0, 4)),
        bin2hex(substr($data, 4, 2)),
        bin2hex(substr($data, 6, 2)),
        bin2hex(substr($data, 8, 2)),
        bin2hex(substr($data, 10, 6))
    );
}

function generateRandomUID($bytes = 4) {
    return strtoupper(bin2hex(random_bytes($bytes)));
}

function generateWebKitBoundary() {
    return 'WebKitFormBoundary' . generateRandomUID(8);
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

// ======================== VERNUABLE BALANCE & POLL ========================
function get_balance() {
    $data = json_encode([
        "key"    => anticaptcha_key,
        "action" => "getbalance",
        "json"   => "1",
    ]);
    $r = json_decode(Run(api_res, ["Content-Type: application/json"], $data)['body'], true);
    if (isset($r['balance'])) {
        return number_format((float)$r['balance'], 5, ".", "");
    }
    return null;
}

function poll($id) {
    $maxTries = 30;
    for ($i = 1; $i <= $maxTries; $i++) {
        $json = json_encode([
            "key"    => anticaptcha_key,
            "action" => "get",
            "id"     => $id,
            "json"   => "1",
        ]);
        $r = json_decode(Run(api_res, ["Content-Type: application/json"], $json)['body'], true);

        // ready
        if (isset($r["status"]) && (int)$r["status"] === 1) {
            // map request JSON (x/y, iuam, etc.)
            if (!empty($r['request']) && is_string($r['request']) && $r['request'][0] === '{') {
                $j = json_decode($r['request'], true);
                if (is_array($j)) {
                    $r = array_merge($r, $j);
                }
            }
            // antibot order string "3 1 2"
            if (empty($r['order']) && !empty($r['request']) && is_string($r['request'])
                && preg_match('/^\s*\d+(\s+\d+)+\s*$/', $r['request'])) {
                $r['order'] = trim($r['request']);
            }
            // shortlink / token
            if (empty($r['token']) && !empty($r['request']) && is_string($r['request'])
                && $r['request'][0] !== '{') {
                $r['token'] = $r['request'];
                if (str_starts_with($r['request'], 'http')) {
                    $r['original_url'] = $r['request'];
                }
            }
            // IUAM
            if (empty($r['cookie']) && !empty($r['cf_clearance'])) {
                $r['cookie'] = $r['cf_clearance'];
            }
            return $r;
        }

        // pending
        if (($r['request'] ?? '') === 'CAPCHA_NOT_READY' || (($r['status'] ?? '') === 0 && ($r['request'] ?? '') === 'CAPCHA_NOT_READY')) {
            animation("[$id] Getting Captcha...");
            sleep(3);
            continue;
        }

        // error
        return $r;
    }
    return ["status" => 0, "request" => "TIMEOUT"];
}

function captcha($url, $sitekey, $method, $action = null) {
    if (empty($sitekey)) return ['token' => null];
    
    $json = [
        "key"     => anticaptcha_key,
        "method"  => $method,
        "pageurl" => $url,
        "domain"  => $url,
        "sitekey" => $sitekey,
        "siteKey" => $sitekey,
        "json"    => "1",
    ];
    if ($action) {
        $json['action'] = $action;
    }
    $r = json_decode(Run(api_in, ["Content-Type: application/json"], json_encode($json))['body'], true);
    if (isset($r['request']) && (int)($r['status'] ?? 0) === 1) {
        return poll($r['request']); // job id
    }
    return $r;
}

function poll_captcha($id) {
    return poll($id);
}

// ======================== PCAPTCHA (VERNUABLE) ========================
function p_captcha_solve($image, $question, $proxy = null) {
    $data = [
        "key"    => anticaptcha_key,
        "method" => "pcaptcha",
        "task"   => $question,
        "image"  => base64_encode($image),
        "json"   => "1",
    ];
    $js = json_decode(Run(api_in, ["Content-Type: application/json"], json_encode($data))['body'], true);
    if (isset($js['request']) && (int)($js['status'] ?? 0) === 1) {
        $res = poll($js['request']);
        // Return indices format for compatibility
        if (isset($res['indices'])) return $res['indices'];
        if (isset($res['request']) && is_string($res['request'])) {
            return $res['request'];
        }
        return $res;
    }
    return null;
}

function poll_pcaptcha($id, $proxy = null) {
    return poll($id);
}

// ======================== ICON CAPTCHA (VERNUABLE) ========================
function iconcaptcha($image) {
    $data = json_encode([
        "key"    => anticaptcha_key,
        "method" => "iconcaptcha",
        "image"  => $image,
        "json"   => "1",
    ]);
    $js = json_decode(Run(api_in, ["Content-Type: application/json"], $data)['body'], true);
    if (isset($js['request']) && (int)($js['status'] ?? 0) === 1) {
        return poll($js['request']);
    }
    return null;
}

function poll_icon($id, $proxy = null) {
    return poll($id);
}

function solveIconCaptcha($icon_token, $host, $url, $user_agent, $captcha_url, $email, $proxy = null) {
    $webkit_boundary = generateWebKitBoundary();
    $init_time = round(microtime(true) * 1000) - rand(54, 97);
    $timestamp = round(microtime(true) * 1000);
    $widget_id = generateUUID();
    
    $load_payload = [
        "widgetId" => $widget_id,
        "action" => "LOAD",
        "theme" => "light",
        "token" => $icon_token,
        "timestamp" => $timestamp,
        "initTimestamp" => $init_time
    ];
    
    $encoded_payload = base64_encode(json_encode($load_payload));
    
    $headers = [
        "host: $host",
        "x-iconcaptcha-token: $icon_token",
        "x-requested-with: XMLHttpRequest",
        "user-agent: $user_agent",
        "content-type: multipart/form-data; boundary=----$webkit_boundary",
        "accept: */*",
        "origin: https://$host",
        "referer: $url",
    ];

    [$proxyHost, $proxyPort, $fullProxy] = parseProxy($proxy);
    $proxyKey = empty($proxyHost) ? '' : $proxyHost . '-' . $proxyPort;

    $cookies = getCfCookieAndUserAgent($host, $proxyKey);     

    $userAgent = saveData(APP_HOST, 'UserAgent');
    if (!empty($cookies['user_agent'])) {
        $userAgent = $cookies['user_agent'];
    }

    $cookieString = '';
    if (!empty($cookies['cf_cookie'])) {
        $cookieString = str_replace(" ", "=", $cookies['cf_cookie']) . ";";
    }

    $cookieString .= get_cookies($host, $fullProxy, $email);

    foreach ($headers as &$header) {
        if (strpos($header, 'user-agent:') === 0) {
            $header = "user-agent: " . $userAgent;
            break;
        }
    }

    $headers[] = "cookie: " . $cookieString;
    
    $request_body = "------$webkit_boundary\r\n" .
                   "Content-Disposition: form-data; name=\"payload\"\r\n\r\n" .
                   "$encoded_payload\r\n" .
                   "------$webkit_boundary--";
    
    $response = Run($captcha_url, $headers, $request_body, "data", $proxy, 2, $email)['body'];
    
    $response_data = json_decode(base64_decode($response), true);
    
    if (!isset($response_data['identifier'])) {
        return null;
    }
    
    $challenge_id = $response_data['identifier'];
    $challenge = $response_data['challenge'];
    
    // Use Vernuable iconcaptcha method
    $solve = iconcaptcha($challenge);
    
    // Check if solve is valid
    if (!$solve || !isset($solve['x']) || !isset($solve['y'])) {
        return null;
    }
    
    $x_positions = [$solve['x']];
    
    foreach ($x_positions as $x_position) {
        $init_time = round(microtime(true) * 1000) - rand(54, 97);
        $timestamp = round(microtime(true) * 1000);
        
        $selection_payload = [
            "widgetId" => $widget_id,
            "challengeId" => $challenge_id,
            "action" => "SELECTION",
            "x" => $x_position,
            "y" => $solve['y'],
            "width" => 320,
            "token" => $icon_token,
            "timestamp" => $timestamp,
            "initTimestamp" => $init_time
        ];
        
        $encoded_selection = base64_encode(json_encode($selection_payload));
        
        $selection_body = "------$webkit_boundary\r\n" .
                         "Content-Disposition: form-data; name=\"payload\"\r\n\r\n" .
                         "$encoded_selection\r\n" .
                         "------$webkit_boundary--";
        
        $selection_response = Run($captcha_url, $headers, $selection_body, "data", $proxy, 2, $email)['body'];
        $selection_data = json_decode(base64_decode($selection_response), true);
        
        if (isset($selection_data['completed']) && $selection_data['completed']) {
            return [
                "captcha" => "icaptcha",
                "_iconcaptcha-token" => $icon_token,
                "ic-rq" => 1,
                "ic-wid" => $widget_id,
                "ic-cid" => $selection_data['identifier'],
                "ic-hp" => ""
            ];
        }
    }
    return null;
}

// ======================== BYPASS CLOUDFLARE (VERNUABLE) ========================
function bypassCloudFlare($url_, $proxy = null) {
    $host = parse_url($url_, PHP_URL_HOST);
    $baseDir = BASE_DIR . "/configs/{$host}-config/";

    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }

    // ------------------------------------------------------------
    // CASE 1: NO PROXY – use local solver (Node.js /bypass endpoint)
    // ------------------------------------------------------------
    if (empty($proxy)) {
        $localEndpoint = 'http://localhost:7860/bypass';

        $payload = [
            'mode'   => 'iuam',
            'domain' => $url_,
        ];

        $response = Run($localEndpoint, ['Content-Type: application/json'], json_encode($payload));
        $result = json_decode($response['body'], true);

        if (!empty($result['success']) && !empty($result['cf_clearance'])) {
            $cloudflare_cookie = $result['cf_clearance'];
            $user_agent = $result['userAgent'] ?? $result['user_agent'];

            file_put_contents($baseDir . "cf_cookie.txt", "cf_clearance " . trim($cloudflare_cookie));
            if ($user_agent) {
                file_put_contents($baseDir . "userAgent", trim($user_agent));
            }

            return [
                "cloudflare_cookie" => $cloudflare_cookie,
                "user_agent"        => $user_agent,
                "success"           => true,
            ];
        }

        $errorMsg = $result['message'] ?? 'Local solver failed (no cookie)';
        return [
            "success" => false,
            "error"   => $errorMsg,
            "raw"     => $result
        ];
    }

    // ------------------------------------------------------------
    // CASE 2: PROXY PROVIDED – use Vernuable API
    // ------------------------------------------------------------
    $payload = [
        'key'     => anticaptcha_key,
        'method'  => 'iuam',
        'pageurl' => $url_,
        'domain'  => $url_,
        'json'    => '1',
    ];

    $proxyHost = null;
    $proxyPort = null;

    if (!preg_match('#^https?://#i', $proxy)) {
        $proxy = "http://{$proxy}";
    }
    $parts = parse_url($proxy);
    if (!empty($parts['host'])) {
        $proxyHost = $parts['host'];
        $proxyPort = $parts['port'] ?? 80;
        $proxyData = [
            'hostname' => $parts['host'],
            'port'     => $proxyPort,
            'scheme'   => $parts['scheme'] ?? 'http',
        ];
        if (!empty($parts['user']) && !empty($parts['pass'])) {
            $proxyData['username'] = $parts['user'];
            $proxyData['password'] = $parts['pass'];
        }
        $payload['proxy'] = $proxyData;
    }

    $create = json_decode(Run(api_in, ['Content-Type: application/json'], json_encode($payload))['body'], true);

    if (empty($create['request']) || (int)($create['status'] ?? 0) !== 1) {
        if (($create['request'] ?? '') === 'ERROR_ZERO_BALANCE') {
            exit("ERROR: No API balance available. Please top up your captcha service account.\n");
        }
        return ["success" => false, "error" => $create['request'] ?? 'create failed', "response" => $create];
    }

    $pollResult = poll($create['request']);
    $cloudflare_cookie = $pollResult['cookie'] ?? $pollResult['cf_clearance'] ?? null;
    $user_agent = $pollResult['userAgent'] ?? $pollResult['user_agent'] ?? null;

    if ($cloudflare_cookie) {
        $cookieFilename = empty($proxyHost) ? "cf_cookie.txt" : "{$proxyHost}-{$proxyPort}-cf_cookies.txt";
        file_put_contents($baseDir . $cookieFilename, "cf_clearance " . trim($cloudflare_cookie));
        if ($user_agent) {
            file_put_contents($baseDir . "userAgent", trim($user_agent));
        }
        return [
            "cloudflare_cookie" => $cloudflare_cookie,
            "user_agent"        => $user_agent,
            "success"           => true,
        ];
    }

    return [
        "cloudflare_cookie" => null,
        "user_agent"        => $user_agent,
        "success"           => false,
        "error"             => "API did not return cf_clearance",
    ];
}

function poll_cf($id) {
    return poll($id);
}

// ======================== SAFE REQUEST FUNCTIONS ========================
function safeRequest($url, $host, $email = null, $proxy = null) {
    $response = Run($url, getRequestHeaders($host, $proxy, $email), null, "data", $proxy, 2, $email);
    
    $html = $response['body'] ?? '';

    if (preg_match('/Just a moment.../', $html)) {
        $response = check_cloudflare($url, $host, $email, $proxy);
    }

    return $response;
}

function safeRequestpost($url,$host,$post,$html,$email,$proxy=null) {
    $headers=array_merge(getFormHeaders($host,$html,$proxy,$email),['Content-Type: application/x-www-form-urlencoded','x-requested-with: XMLHttpRequest']);
    return Run($url,$headers,$post,'data',$proxy,2,$email);
}

function check_cloudflare($url, $host, $email, $proxy = null) {
    $response = Run($url . "/", getRequestHeaders($host, $proxy, $email), null, "data", $proxy, 2, $email);
    $html = $response['body'];

    if ($response['info']['http_code'] == 301 || $response['info']['http_code'] == 404) {
        $response = Run($url, getRequestHeaders($host, $proxy, $email), null, "data", $proxy, 2, $email);
        $html = $response['body'];
    }

    if (preg_match('/Just a moment.../', $html)) {
        $cfResult = bypassCloudFlare($url, $proxy);
        $response = Run($url, getRequestHeaders($host, $proxy, $email), null, "data", $proxy, 2, $email);
    }

    return $response;
}

function check_cloudflare_post($url, $host, $post, $html, $email, $proxy = null) {
    $headers = array_merge(getFormHeaders($host, $html, $proxy, $email), ["x-requested-with: XMLHttpRequest"]);
    $response = Run($url . "/", $headers, $post, "data", $proxy, 2, $email);
    $html = $response['body'];

    if ($response['info']['http_code'] == 301 || $response['info']['http_code'] == 404 || $response['info']['http_code'] == 403) {
        $response = Run($url, $headers, $post, "data", $proxy, 2, $email);
        $html = $response['body'];
    }

    if (preg_match('/Just a moment.../', $html)) {
        $cfResult = bypassCloudFlare($url, $proxy);
        if (!$cfResult || !$cfResult['success']) {
            return $response;
        }
        $headers = array_merge(getFormHeaders($host, $html, $proxy, $email), ["x-requested-with: XMLHttpRequest"]);
        $response = Run($url, $headers, $post, "data", $proxy, 2, $email);
    }

    return $response;
}

// ======================== PAYLOAD FUNCTION ========================
function payyload($html, $email, $host, $url, $ua, $password = null, $proxy = null, $faucet = false, $login = false, $csrf = null, $key = null, $captcha_url = null, $config) {
    
    foreach(['rv2_sitekey'=>'GG_KEY','hcap_sitekey'=>'HCAPTCHA_KEY','turnstile_sitekey'=>'CF_KEY'] as $field=>$constant) {
        if(preg_match("/\\b".preg_quote($constant,'/')."\\s*=\\s*['\"]([^'\"]+)['\"]/",$html,$keyMatch)) $config[$field]=$keyMatch[1];
    }
    // Check if option exists in HTML
    $option = 0;
    if (strpos($html, '<option value="') !== false) {
        if(preg_match('/<select[^>]*id=[\"\']select_captcha[\"\'][^>]*>(.*?)<\/select>/s',$html,$sm) && preg_match('/<option[^>]*value=[\"\'](\d+)[\"\']/',$sm[1],$om)) $option=(int)$om[1];
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
        if (!empty($key)) {
            $data['hash'] = generate_hash($key);
        }
    }
    
    switch ($option) {
        case 0:
            if (!empty($iconToken)) {
                $iconResponse = solveIconCaptcha($iconToken, $host, $url, $ua, $captcha_url, $email, $proxy);
                if (!$iconResponse) return null;
                $data['captcha_type'] = 0;
                $data['_iconcaptcha-token'] = $iconResponse['_iconcaptcha-token'];
                $data['ic-rq'] = $iconResponse['ic-rq'];
                $data['ic-wid'] = $iconResponse['ic-wid'];
                $data['ic-cid'] = $iconResponse['ic-cid'];
                $type = "IconCaptcha";
            } else {
                // No captcha found, try turnstile
                $captchaResult = captcha($url, $config['turnstile_sitekey'] ?? null, "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                if ($host == 'tronlux.io') {
                    $data['captcha_provider'] = 3;
                    $data['cf-turnstile-response'] = $captchaResult['token'];
                } else {
                    $data['captcha_type'] = 3;
                    $data['c_captcha_response'] = $captchaResult['token'];
                }
                $type = "TurnStile";
            }
            break;
        
        case 1:
            $captchaResult = captcha($url, $config['rv2_sitekey'], "recaptchav2");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            $data['captcha_type'] = 1;
            $data['g-recaptcha-response'] = $captchaResult['token'];
            $type = "ReCaptchaV2";
            break;
        
        case 2:
            $captchaResult = captcha($url, $config['hcap_sitekey'], "hcaptcha");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            $data['captcha_type'] = 2;
            $data['h-captcha-response'] = $captchaResult['token'];
            $type = "Hcaptcha";
            break;
        
        case 3:
            if (str_contains($host,'lux')) {
                $captchaResult = captcha($url, $config['turnstile_sitekey'], "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                $data['captcha_provider'] = 3;
                $data['cf-turnstile-response'] = $captchaResult['token'];
                $type = "TurnStile";
            } else {
                $captchaResult = captcha($url, $config['turnstile_sitekey'], "turnstile");
                if (empty($captchaResult) || empty($captchaResult['token'])) return null;
                $data['captcha_type'] = 3;
                $data['c_captcha_response'] = $captchaResult['token'];
                $type = "TurnStile";
            }
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
            $captchaResult = captcha($url, $config['turnstile_sitekey'] ?? null, "turnstile");
            if (empty($captchaResult) || empty($captchaResult['token'])) return null;
            if ($host == 'tronlux.io') {
                $data['captcha_provider'] = 3;
                $data['cf-turnstile-response'] = $captchaResult['token'];
            } else {
                $data['captcha_type'] = 3;
                $data['c_captcha_response'] = $captchaResult['token'];
            }
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

// ======================== PCAPTCHA HANDLER ========================
function p_captcha_handler($email, $proxy, $host) {
    $r = Run("https://{$host}/generate_pcaptcha.php", getRequestHeaders($host, $proxy, $email), false, "data", $proxy, 0, $email);
    
    preg_match('/csrf_cookie_name=([^;\r\n]+)/', $r['header'] ?? '', $cm);
    $csrf = $cm[1] ?? '';
    if (!$csrf) {preg_match('/csrf_cookie_name=([^;]+)/', get_cookies($host,$proxy,$email),$cm);$csrf=$cm[1]??'';}
    $r = json_decode($r['body'], true);

    $imageResponse = Run($r['image'], getRequestHeaders($host, $proxy, $email), false, "data", $proxy, 2, $email);
    $imageData = $imageResponse['body'];
    
    $indices = p_captcha_solve($imageData, $r['question'], $proxy);
    
    if (empty($indices)) {
        return null;
    }
    
    $selected = json_encode($indices);
    
    $req = "action=verify_pcaptcha&selected_boxes=" . urlencode($selected) . "&csrf_test_name=" . $csrf;
    $r = Run("https://{$host}/process.php", getFormHeaders($host, null, $proxy, $email), $req, "data", $proxy, 0, $email);
    preg_match('/csrf_cookie_name=([^;\r\n]+)/', $r['header'] ?? '', $cm);
    $csrf = $cm[1] ?? '';
    if (!$csrf) {preg_match('/csrf_cookie_name=([^;]+)/', get_cookies($host,$proxy,$email),$cm);$csrf=$cm[1]??'';}
    $r = json_decode($r['body'], true);
    
    if ($r['message'] == "Success!") {
        return ["token" => $r['token'], "csrf" => $csrf];
    }
    return null;
}

// ======================== SITE FUNCTIONS ========================
function d_l($email, $password, $proxy = null, $accountId = null, $host, $config) {
    $url = "https://{$host}/login.php";
    $ua = saveData(APP_HOST, 'UserAgent');
    $response = safeRequest($url, $host, $email, $proxy);
    
    if ($response['info']['http_code'] == 302) {
        return ['success' => true, 'timer' => 0];
    }
    
    if ($response['info']['http_code'] == 200) {
        $r = Run($url, getRequestHeaders($host, $proxy, $email), false, "data", $proxy, 0, $email);
        preg_match('/csrf_cookie_name=([^;\r\n]+)/', $r['header'] ?? '', $cm);
    $csrf = $cm[1] ?? '';
    if (!$csrf) {preg_match('/csrf_cookie_name=([^;]+)/', get_cookies($host,$proxy,$email),$cm);$csrf=$cm[1]??'';}
        
        $timer = 0;
        if (preg_match('/show_countdown_clock\((\d+)\)/', $r['body'], $timerMatch)) {
            $timer = intval($timerMatch[1]);
        }
        
        $data = payyload($r['body'], $email, $host, $url, $ua, $password, $proxy, false, true, $csrf, null, "https://{$host}/iconcaptcha.php", $config);
        if (!$data) {echo '['.$host.'] Captcha was not completed.\n';return ['success' => false, 'timer' => 0];}
        
        $payload = http_build_query($data['payload']);
        $response = safeRequestpost("https://{$host}/process.php", $host, $payload, $r['body'], $email, $proxy);
        $postResponse = $response['body'];
        if ($response['info']['http_code'] == 200) {
            $js = json_decode($postResponse, true);
            echo CYAN . "[$host #$accountId]" . GREY . " [" . WHITE . "Login" . GREY . "]" . WHITE . " Message " . GREEN . ($js['mes'] ?? 'Unconfirmed response') . WHITE . NEWLINE;
        }
        
        return ['success' => $response['info']['http_code'] == 200 && (int)($js['ret'] ?? 0) === 1, 'timer' => 0];
    }
    
    echo '['.$host.'] Page request failed: HTTP '.($response['info']['http_code']??0).PHP_EOL;
    return ['success' => false, 'timer' => 0];
}

function f_l($email, $proxy = null, $accountId = null, $host, $config) {
    $url = "https://{$host}/faucet.php";
    $ua = saveData(APP_HOST, 'UserAgent');
    $response = safeRequest($url, $host, $email, $proxy);
    
    if ($response['info']['http_code'] == 302) {
        return ['success' => false, 'timer' => 0];
    }
    
    if ($response['info']['http_code'] == 200) {
        $r = Run($url, getRequestHeaders($host, $proxy, $email), false, "data", $proxy, 0, $email);
        preg_match('/csrf_cookie_name=([^;\r\n]+)/', $r['header'] ?? '', $cm);
    $csrf = $cm[1] ?? '';
    if (!$csrf) {preg_match('/csrf_cookie_name=([^;]+)/', get_cookies($host,$proxy,$email),$cm);$csrf=$cm[1]??'';}
        
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
            return ['success' => false, 'timer' => $timer];
        }
        
        $data = payyload($r['body'], $email, $host, $url, $ua, null, $proxy, true, false, $csrf, $key, "https://{$host}/iconcaptcha.php", $config);
        if (!$data) {echo '['.$host.'] Captcha was not completed.\n';return ['success' => false, 'timer' => 0];}
        
        $payload = http_build_query($data['payload']);
        $response = safeRequestpost("https://{$host}/process.php", $host, $payload, $r['body'], $email, $proxy);
        $postResponse = $response['body'];
        if ($response['info']['http_code'] == 200) {
            preg_match_all('/\{.*?\}/', $postResponse, $matches);
            $js = null;
            foreach ($matches[0] as $json) {
                $decoded = json_decode($json, true);
                if (isset($decoded['ret']) && $decoded['ret'] == 1) {
                    $js = $decoded;
                    break;
                }
            }
            if ($js && isset($js['reward'])) {
                echo CYAN . "[$host #$accountId]" . GREY . " [" . WHITE . "Faucet" . GREY . "]" . WHITE . " Message " . GREEN . ($js['mes'] ?? 'Unconfirmed response') . WHITE . " (" . GREEN . $data['Type'] . WHITE . ")" . WHITE . NEWLINE;
                echo CYAN . "[buxads.com]" . GREY . " [" . WHITE . "Apikey" . GREY . "]" . WHITE . " Balance " . GREEN . get_balance() . WHITE . NEWLINE;
            }
        }
        
        return ['success' => isset($js) && (int)($js['ret'] ?? 0) === 1 && isset($js['reward']), 'timer' => 0];
    }
    
    echo '['.$host.'] Page request failed: HTTP '.($response['info']['http_code']??0).PHP_EOL;
    return ['success' => false, 'timer' => 0];
}

// ======================== ACCOUNT PARSING ========================
function loadAccounts($host) {
    $accountFile = BASE_DIR . "/information/{$host}.txt";
    $infoDir = dirname($accountFile);

    if (!is_dir($infoDir)) {
        mkdir($infoDir, 0777, true);
    }

    if (!file_exists($accountFile)) {
        $exampleContent = "# Account file for $host\n# email@gmail.com|password|proxy\n";
        file_put_contents($accountFile, $exampleContent);
        return [];
    }

    $lines = file($accountFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $accounts = [];
    $proxyPatterns = [
        '/^[a-zA-Z0-9._-]+:[^@]+@[^:]+:\d+$/',
        '/^[a-zA-Z0-9._-]+@[^:]+:\d+$/',
        '/^\d+\.\d+\.\d+\.\d+:\d+$/',
        '/^[a-zA-Z0-9.-]+:\d+$/',
        '/^[a-z]+:\/\/.+/i'
    ];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        
        $parts = array_map('trim', explode('|', $line));
        
        $email = $parts[0] ?? '';
        $secondField = $parts[1] ?? '';
        $thirdField = $parts[2] ?? null;
        
        $isSecondFieldProxy = false;
        if (!empty($secondField)) {
            foreach ($proxyPatterns as $pattern) {
                if (preg_match($pattern, $secondField)) {
                    $isSecondFieldProxy = true;
                    break;
                }
            }
        }
        
        if ($isSecondFieldProxy) {
            $password = '';
            $proxy = $secondField;
        } elseif (!empty($thirdField)) {
            $password = $secondField;
            $proxy = $thirdField;
        } else {
            $password = $secondField;
            $proxy = null;
        }
        
        if (empty($proxy)) $proxy = null;
        if (empty($password)) $password = '';
        
        $accounts[] = [
            'email' => $email,
            'password' => $password,
            'proxy' => $proxy
        ];
    }
    
    return $accounts;
}

// ======================== SHOW REMAINING COUNTDOWN ========================
function showRemainingTimers($accountTimers, $hostNames, $host) {
    $currentTime = time();
    $timers = [];
    
    foreach ($accountTimers as $accId => $timerEnd) {
        if ($timerEnd > $currentTime) {
            $remaining = $timerEnd - $currentTime;
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            $seconds = $remaining % 60;
            $timers[] = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        }
    }
    
    if (!empty($timers)) {
        $msg = "⏳ " . count($timers) . " accounts waiting: " . implode(", ", $timers);
        animation($msg);
    }
}

// ======================== MAIN MENU ========================
function showMenu() {
    clear();
    echo GREEN . "╔══════════════════════════════════════════════════════════════╗\n";
    echo GREEN . "║              MULTI-FAUCET CLAIMER v1.0                      ║\n";
    echo GREEN . "╚══════════════════════════════════════════════════════════════╝\n\n";
    echo WHITE . "Select mode:\n";
    echo WHITE . "  [1] " . CYAN . "Run all hosts (1 by 1)\n";
    echo WHITE . "  [2] " . CYAN . "Select specific host\n";
    echo WHITE . "  [3] " . CYAN . "Exit\n\n";
    echo WHITE . "Enter choice: ";
    $choice = trim(bx_live_readline());
    return $choice;
}

function showHostMenu($hosts) {
    echo WHITE . "\nSelect host:\n";
    $i = 1;
    foreach ($hosts as $host => $config) {
        $accountFile = BASE_DIR . "/information/{$host}.txt";
        $hasAccounts = file_exists($accountFile) && filesize($accountFile) > 50;
        $status = $hasAccounts ? GREEN . "✓" . WHITE : RED . "✗" . WHITE;
        echo WHITE . "  [$i] " . CYAN . $host . " " . $status . "\n";
        $i++;
    }
    echo WHITE . "  [0] " . CYAN . "Back to main menu\n\n";
    echo WHITE . "Enter choice: ";
    $choice = trim(bx_live_readline());
    return $choice;
}

// ======================== PROCESS HOST FUNCTION ========================
function pk_log($host,$id,$message) {
    echo "\033[96m[".date('H:i:s')."] [$host #".($id+1)."]\033[0m ".strip_tags((string)$message)."\n";
}
function pk_accounts($host) {
    return array_values(array_filter(loadAccounts($host),fn($a)=>filter_var($a['email'],FILTER_VALIDATE_EMAIL)&&$a['password']!==''));
}
function pk_state_file($host,$account) {
    return BASE_DIR.'/configs/pick-scheduler/'.hash('sha256',$host.'|'.$account['email'].'|'.($account['proxy']??'')).'.json';
}
function pk_save_state($file,$next,$status) {
    $data=json_encode(['next_claim_at'=>$next,'status'=>$status],JSON_PRETTY_PRINT);
    $temporary=$file.'.tmp.'.getmypid();
    if(file_put_contents($temporary,$data,LOCK_EX)===false || !rename($temporary,$file)) throw new RuntimeException('Cannot save cooldown');
    @chmod($file,0600);
}
function pk_run($selected,$once=false,$attempt=null) {
    $tasks=[];$locks=[];$failures=0;
    $folder=BASE_DIR.'/configs/pick-scheduler';
    if(!is_dir($folder)&&!mkdir($folder,0700,true)) throw new RuntimeException('Cannot create cooldown folder');
    try {
        foreach($selected as $host=>$config) foreach(pk_accounts($host) as $id=>$account) {
            $file=pk_state_file($host,$account);
            $lock=fopen($file.'.lock','c');
            if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) {
                if($lock) fclose($lock); pk_log($host,$id,'Already running elsewhere; skipped');continue;
            }
            $locks[]=$lock; $tasks[]=[$host,$config,$id,$account,$file];
        }
        if(!$tasks) {echo "No configured accounts. Add an account first.\n";return 0;}
        do {
            $earliest=PHP_INT_MAX;
            foreach($tasks as [$host,$config,$id,$a,$file]) {
                $state=is_file($file)?json_decode(file_get_contents($file),true):[];
                $next=(int)($state['next_claim_at']??0);
                if($next>time()) {if($once) pk_log($host,$id,'Cooldown until '.date('H:i:s',$next));$earliest=min($earliest,$next);continue;}
                bx_live_begin();
                bx_live_begin();
                try {
                    if($attempt) $result=$attempt($host,$a,$id,$config);
                    else {
                        $login=d_l($a['email'],$a['password'],$a['proxy'],$id+1,$host,$config);
                        $result=$login['success']?f_l($a['email'],$a['proxy'],$id+1,$host,$config):$login;
                    }
                } catch(Throwable $e) {$result=['success'=>false,'timer'=>0];pk_log($host,$id,$e->getMessage());}
                $timer=(int)($result['timer']??0);
                if(!empty($result['success'])||$timer>0) {
                    $failures=0;$next=time()+(!empty($result['success'])?max(3600,$timer):$timer);
                    pk_save_state($file,$next,!empty($result['success'])?'claimed':'cooldown');
                    pk_log($host,$id,(!empty($result['success'])?'Claim confirmed. ':'').'Next turn '.date('H:i:s',$next));
                } else {
                    $failures++;$next=time()+60;
                    pk_save_state($file,$next,'failed');pk_log($host,$id,"Failed ($failures/3)");
                    if($failures>=3) {echo "Stopped after 3 consecutive failures.\n";return 1;}
                }
                bx_live_finish();
                bx_live_finish();
                $earliest=min($earliest,$next);
            }
            if(!$once) {echo 'Next account ready at '.date('H:i:s',$earliest)."\n";while(time()<$earliest) sleep(min(30,$earliest-time()));}
        } while(!$once);
        return $failures?1:0;
    } finally {bx_live_finish();foreach($locks as $lock) {flock($lock,LOCK_UN);fclose($lock);}}
}
function pk_choose($hosts,$default=null) {
    if($default) return $default;
    $names=array_keys($hosts);$rows=[];
    foreach($names as $i=>$host) $rows[]=($i+1).'. '.$host.' ('.count(pk_accounts($host)).' accounts)';
    bx_card('Choose site',$rows);
    $choice=(int)bx_live_readline('Site number (0 cancels): ');
    return $names[$choice-1]??null;
}
function pk_selection($hosts,$input) {
    if(!preg_match('/^\\d+(?:\\s*,\\s*\\d+)*$/',$input)) return [];
    $names=array_keys($hosts);$selected=[];
    foreach(explode(',',$input) as $number) {
        $index=(int)trim($number)-1;
        if(!isset($names[$index])) return [];
        $host=$names[$index];$selected[$host]=$hosts[$host];
    }
    return $selected;
}
function pk_menu($hosts,$default=null) {
    global $argv;
    if(in_array('--once',$argv??[],true)||in_array('--all',$argv??[],true)||in_array('--host',$argv??[],true)||in_array('--hosts',$argv??[],true)) {
        $groupIndex=array_search('--hosts',$argv??[],true);
        if($groupIndex!==false) {
            $selected=[];foreach(explode(',',$argv[$groupIndex+1]??'') as $name) {if(!isset($hosts[$name])) {echo 'Unknown site'.PHP_EOL;return 1;}$selected[$name]=$hosts[$name];}
            return pk_run($selected,in_array('--once',$argv??[],true));
        }
        $host=in_array('--all',$argv??[],true)?null:$default; $index=array_search('--host',$argv??[],true);
        if($index!==false) $host=$argv[$index+1]??'';
        if($host&&!isset($hosts[$host])) {echo "Unknown site\n";return 1;}
        return pk_run($host?[$host=>$hosts[$host]]:$hosts,in_array('--once',$argv??[],true));
    }
    while(true) {
        bx_card($default?:'MultiPick • hourly faucets',['1. Run one site 24/7','2. Run all configured sites 24/7','7. Run selected sites 24/7','3. Add account','4. View accounts','5. Delete account','6. Test one round','0. Exit']);
        $choice=trim(bx_live_readline('Choose: '));if($choice==='0'||$choice==='') return 0;
        if($choice==='2') return pk_run($hosts);
        if($choice==='7') {
            $names=array_keys($hosts);$rows=[];
            foreach($names as $i=>$name) $rows[]=($i+1).'. '.$name.' ('.count(pk_accounts($name)).' accounts)';
            bx_card('Choose your sites',$rows);
            $input=trim(bx_live_readline('Site numbers separated by commas (0 cancels): '));
            $selected=pk_selection($hosts,$input);
            if(!$selected) {echo "No valid sites selected.\n";continue;}
            return pk_run($selected);
        }
        $host=pk_choose($hosts,$default);if(!$host) continue;
        if($choice==='1'||$choice==='6') return pk_run([$host=>$hosts[$host]],$choice==='6');
        $accounts=pk_accounts($host);
        if($choice==='3') {
            $email=trim(bx_live_readline('Email: '));$password=trim(bx_live_readline('Password: '));$proxy=trim(bx_live_readline('Proxy (optional): '));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''||strpbrk($email.$password.$proxy,"|\r\n")!==false) {echo "Invalid account\n";continue;}
            $accounts[]=['email'=>$email,'password'=>$password,'proxy'=>$proxy?:null];
        } elseif($choice==='4'||$choice==='5') {
            bx_card($host,array_map(fn($a,$i)=>($i+1).'. '.$a['email'],$accounts,array_keys($accounts)));
            if($choice==='4') continue;
            $id=(int)bx_live_readline('Delete number (0 cancels): ')-1;
            if(!isset($accounts[$id])) continue;
            unset($accounts[$id]);
        } else continue;
        $file=BASE_DIR.'/information/'.$host.'.txt';
        $text="# email|password|optional proxy\n";
        foreach($accounts as $a) $text.=$a['email'].'|'.$a['password'].'|'.($a['proxy']??'')."\n";
        if(file_put_contents($file,$text,LOCK_EX)===false) throw new RuntimeException('Cannot save accounts');
        @chmod($file,0600);echo "Accounts saved.\n";
    }
}

if (!defined("PICK_LIBRARY_ONLY")) $pickStatus=pk_menu($hosts,null);
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) exit($pickStatus);
return $pickStatus;
