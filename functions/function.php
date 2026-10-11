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

bx_initialize(dirname(__DIR__));

// ============================================
// COLOR DEFINITIONS
// ============================================
define("RED", "\033[1;31;40m");
define("GREEN", "\033[1;32;40m");
define("YELLOW", "\033[1;33;40m");
define("BLUE", "\033[1;34;40m");
define("PURPLE", "\033[1;35;40m");
define("CYAN", "\033[1;36;40m");
define("GREY", "\033[1;30;40m");
define("WHITE", "\033[1;37m");
define("BOLD_YELLOW", "\033[1;33m");
define('MAGENTA', "\033[35m");
define("MONO", "\033[2;37;40m");
define("EMONO", "\033[0;37;40m");
define("ITALIC", "\033[3;37;40m");
define("UNDERLINE_CYAN", "\033[4;36m");
define("BLINKING_GREEN", "\033[5;32m");
define("GLOWING_PURPLE", "\033[1;35;5m");
define("GLOWING_WHITE", "\033[1;37;5m");
define("BG_RED", "\033[41m");
define("BG_GREEN", "\033[42m");
define("BG_BLUE", "\033[44m");
define("NEWLINE", "\n");
define("RESET", WHITE);
define("BG_END", "\033[0m");
if (!defined("BOLD")) define("BOLD", "\033[1m");
if (!defined("DIM")) define("DIM", "\033[2m");
// ============================================
// SAVE DATA FUNCTION
// ============================================
function saveData($folder, $filename) {
    $base = (defined("BUXADS_DATA_DIR") ? BUXADS_DATA_DIR : dirname(__DIR__)) . "/configs/{$folder}-config";
    if (!is_dir($base)) mkdir($base, 0777, true);

    $path = $base . "/$filename";
    if (file_exists($path)) {
        return file_get_contents($path);
    }
    
    $data = readline("Input $filename: ");
    file_put_contents($path, $data);
    return $data;
}

// ============================================
// PARSE PROXY HELPER
// ============================================
function parseProxyString($proxy) {
    if (empty($proxy)) return null;
    
    if (!preg_match('#^https?://#i', $proxy)) {
        $proxy = "http://" . $proxy;
    }
    
    $parts = parse_url($proxy);
    
    if (empty($parts['host'])) return null;
    
    return [
        'host' => $parts['host'],
        'port' => $parts['port'] ?? 80,
        'user' => $parts['user'] ?? null,
        'pass' => $parts['pass'] ?? null,
        'scheme' => $parts['scheme'] ?? 'http'
    ];
}

// ============================================
// GET CF COOKIE & USER AGENT - FIXED
// ============================================
function getCfCookieAndUserAgent($host, $proxy = null) {
    $dir = (defined("BUXADS_DATA_DIR") ? BUXADS_DATA_DIR : dirname(__DIR__)) . "/configs/{$host}-config";
    if (!is_dir($dir)) return null;

    $cookieFile = $dir . "/cf_cookie.txt";
    
    if (!empty($proxy)) {
        // Extract host and port from proxy
        $proxyParts = parseProxyString($proxy);
        
        if ($proxyParts && !empty($proxyParts['host'])) {
            $proxyHost = $proxyParts['host'];
            $proxyPort = $proxyParts['port'];
            $proxyCookieFile = $dir . "/{$proxyHost}-{$proxyPort}-cf_cookies.txt";
            
            if (file_exists($proxyCookieFile)) {
                $cookieFile = $proxyCookieFile;
            }
        }
    }

    $cfCookie = file_exists($cookieFile) ? trim(file_get_contents($cookieFile)) : null;
    
    // Clean cookie if it has prefix
    if ($cfCookie && strpos($cfCookie, 'cf_clearance ') === 0) {
        $cfCookie = str_replace('cf_clearance ', '', $cfCookie);
    }
    
    $uaFile = $dir . "/userAgent";
    $userAgent = file_exists($uaFile) ? trim(file_get_contents($uaFile)) : null;

    return [
        'cf_cookie' => $cfCookie,
        'user_agent' => $userAgent
    ];
}

// ============================================
// SAVE CF COOKIE & USER AGENT
// ============================================
function saveCfCookieAndUserAgent($host, $cookie, $userAgent, $proxy = null) {
    $dir = (defined("BUXADS_DATA_DIR") ? BUXADS_DATA_DIR : dirname(__DIR__)) . "/configs/{$host}-config";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Determine filename based on proxy
    $cookieFile = $dir . "/cf_cookie.txt";
    
    if (!empty($proxy)) {
        $proxyParts = parseProxyString($proxy);
        
        if ($proxyParts && !empty($proxyParts['host'])) {
            $proxyHost = $proxyParts['host'];
            $proxyPort = $proxyParts['port'];
            $cookieFile = $dir . "/{$proxyHost}-{$proxyPort}-cf_cookies.txt";
        }
    }

    // Save cookie (just the value, no prefix)
    file_put_contents($cookieFile, trim($cookie));
    
    // Save User-Agent
    $uaFile = $dir . "/userAgent";
    if (!empty($userAgent)) {
        file_put_contents($uaFile, trim($userAgent));
    }

    return true;
}

// ============================================
// GET COOKIES FUNCTION
// ============================================
function get_cookies($host, $proxy = null, $email = null) {
    $dir = (defined("BUXADS_DATA_DIR") ? BUXADS_DATA_DIR : dirname(__DIR__)) . "/configs/{$host}-config";
    if (!is_dir($dir)) return '';
    
    $cookieFile = $dir . "/cookie.txt";
    
    if (!empty($proxy)) {
        $proxyParts = parseProxyString($proxy);
        
        if ($proxyParts && !empty($proxyParts['host'])) {
            $proxyHost = $proxyParts['host'];
            $proxyPort = $proxyParts['port'];
            $proxyCookieFile = $dir . "/{$proxyHost}-{$proxyPort}-cookie.txt";
            
            if (file_exists($proxyCookieFile)) {
                $cookieFile = $proxyCookieFile;
            }
        }
    }
    
    if (file_exists($cookieFile)) {
        $content = file_get_contents($cookieFile);
        // Parse Netscape format cookies
        $cookies = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] == '#') continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 7) {
                $cookies[] = $parts[5] . '=' . $parts[6];
            }
        }
        return implode('; ', $cookies);
    }
    
    return '';
}

// ============================================
// GET REQUEST HEADERS
// ============================================
function getRequestHeaders($host, $proxy = null, $email = null) {
    $cookies = getCfCookieAndUserAgent($host, $proxy);
    $userAgent = !empty($cookies['user_agent']) ? $cookies['user_agent'] : saveData($host, 'UserAgent');
    $cookieString = '';
    
    if (!empty($cookies['cf_cookie'])) {
        // Clean up cookie format
        $cookieString = trim($cookies['cf_cookie']);
        // Remove trailing semicolon if exists
        $cookieString = rtrim($cookieString, ';');
    }
    
    if (empty($cookieString)) {
        $cookieString = get_cookies($host, $proxy, $email);
    }
    
    $headers = [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "Accept-Language: en-US,en;q=0.9",
        "User-Agent: " . $userAgent,
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        "Upgrade-Insecure-Requests: 1",
        "Sec-Fetch-Dest: document",
        "Sec-Fetch-Mode: navigate",
        "Sec-Fetch-Site: none",
        "Sec-Fetch-User: ?1"
    ];
    
    if (!empty($cookieString)) {
        $headers[] = "Cookie: " . $cookieString;
    }
    
    return $headers;
}

function Run($url, $head = 0, $post = 0, $data = "data", $proxy = 0, $returnType = 0, $name = "") {
    $host = parse_url($url, PHP_URL_HOST);
    $folder = (defined("BUXADS_DATA_DIR") ? BUXADS_DATA_DIR : dirname(__DIR__)) . "/configs/{$host}-config";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    // Cookie file
    $proxyPart = '';
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

        $cookieFile = !empty($name)
            ? $folder . "/{$name}-{$proxyPart}-cookie.txt"
            : $folder . "/{$proxyPart}-cookie.txt";
    } else {
        $cookieFile = $folder . "/" . (!empty($name) ? "{$name}-cookie.txt" : "cookie.txt");
    }

    // ===== CHECK FOR EXISTING CF COOKIE FILE =====
    $cfCookieFile = $folder . "/cf_cookie.txt";
    if (!empty($proxy)) {
        $proxyParts = parseProxyString($proxy);
        if ($proxyParts && !empty($proxyParts['host'])) {
            $proxyHost = $proxyParts['host'];
            $proxyPort = $proxyParts['port'];
            $proxyCfCookieFile = $folder . "/{$proxyHost}-{$proxyPort}-cf_cookies.txt";
            if (file_exists($proxyCfCookieFile)) {
                $cfCookieFile = $proxyCfCookieFile;
            }
        }
    }

    $maxRetries = 5;
    $retryDelay = 1;
    $attempt = 0;
    $response = false;
    $info = [];
    $curlErr = '';
    $rawHeaders = '';

    // Store original headers for reference
    $originalHeaders = ($head && is_array($head)) ? $head : [];
    $currentHeaders = $originalHeaders;

    // ===== LOAD EXISTING COOKIE AND USER AGENT IF AVAILABLE =====
    $existingCookie = null;
    $existingUserAgent = null;
    
    if (file_exists($cfCookieFile)) {
        $existingCookie = trim(file_get_contents($cfCookieFile));
        // Clean cookie if it has prefix
        if ($existingCookie && strpos($existingCookie, 'cf_clearance ') === 0) {
            $existingCookie = str_replace('cf_clearance ', '', $existingCookie);
        }
      
        
        // Load user agent
        $uaFile = $folder . "/userAgent";
        if (file_exists($uaFile)) {
            $existingUserAgent = trim(file_get_contents($uaFile));
        }
        
        // Add existing cookie to headers
        if (!empty($existingCookie)) {
            $cookieHeader = "cf_clearance=" . $existingCookie;
            
            // Check if cookie is already in headers
            $cookieFound = false;
            $userAgentFound = false;
            
            foreach ($currentHeaders as &$header) {
                if (stripos($header, 'Cookie:') === 0) {
                    $header = 'Cookie: ' . $cookieHeader;
                    $cookieFound = true;
                }
                if ($existingUserAgent && stripos($header, 'user-agent:') === 0) {
                    $header = 'user-agent: ' . $existingUserAgent;
                    $userAgentFound = true;
                }
            }
            
            if (!$cookieFound) {
                $currentHeaders[] = 'Cookie: ' . $cookieHeader;
            }
            if ($existingUserAgent && !$userAgentFound) {
                $currentHeaders[] = 'user-agent: ' . $existingUserAgent;
            }
        }
    } else {
    }

    do {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        // ===== PROXY =====
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
                curl_setopt($ch, CURLOPT_SUPPRESS_CONNECT_HEADERS, true);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            }
        } else {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
        }

        // POST
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        // Headers - use current headers
        $headers = $currentHeaders;
        $headers[] = 'Expect:';
        $headers[] = 'Connection: close';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // ===== CRITICAL: Capture headers with callback =====
        $rawHeaders = '';
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (&$rawHeaders) {
            $rawHeaders .= $headerLine;
            return strlen($headerLine);
        });

        $response = (function_exists('bx_interrupt_exec')?bx_interrupt_exec($ch):curl_exec($ch));
        $info = curl_getinfo($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $info['http_code'] != 0) {

            // ===== CHECK FOR CLOUDFLARE (403 with "Just a moment...") =====
            $isCloudflare = false;
            if ($info['http_code'] == 403 && 
                (preg_match('/Just a moment.../', $response) ||
                 preg_match('/cf-ray/i', $response) ||
                 preg_match('/cloudflare/i', $response) ||
                 stripos($response, 'challenge') !== false)) {
                $isCloudflare = true;
            }

            if ($isCloudflare) {
                 
                // ===== CHECK IF WE ALREADY HAVE A COOKIE AND IT'S FAILING =====
                if (!empty($existingCookie)) {
                }
                
                // ===== CALL BYPASS FUNCTION =====
                if (!function_exists('bypassCloudFlare')) {
                    break;
                }
                
                $cfResult = bypassCloudFlare($url, $proxy);
                
                if ($cfResult && isset($cfResult['success']) && $cfResult['success'] === true) {
                    
                    $cloudflareCookie = $cfResult['cloudflare_cookie'] ?? null;
                    $userAgent = $cfResult['user_agent'] ?? '';
                    
                    if (!empty($cloudflareCookie)) {
                        
                        // Save the cookie and UA
                        saveCfCookieAndUserAgent($host, $cloudflareCookie, $userAgent, $proxy);
                        
                        // Update existing cookie and user agent variables
                        $existingCookie = $cloudflareCookie;
                        $existingUserAgent = $userAgent;
                        
                        // Update headers with new cookie
                        $cookieHeader = "cf_clearance=" . $cloudflareCookie;
                        $currentHeaders = $originalHeaders;
                        
                        // Add UA (must match the cookie)
                        if (!empty($userAgent)) {
                            $userAgentFound = false;
                            foreach ($currentHeaders as &$header) {
                                if (stripos($header, 'user-agent:') === 0) {
                                    $header = 'user-agent: ' . $userAgent;
                                    $userAgentFound = true;
                                    break;
                                }
                            }
                            if (!$userAgentFound) {
                                $currentHeaders[] = 'user-agent: ' . $userAgent;
                            }
                        }
                        
                        // Add Cookie
                        $cookieFound = false;
                        foreach ($currentHeaders as &$header) {
                            if (stripos($header, 'Cookie:') === 0) {
                                $header = 'Cookie: ' . $cookieHeader;
                                $cookieFound = true;
                                break;
                            }
                        }
                        if (!$cookieFound) {
                            $currentHeaders[] = 'Cookie: ' . $cookieHeader;
                        }
                        
                        // Add all standard headers
                        $additionalHeaders = [
                            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                            'Accept-Language: en-US,en;q=0.9',
                            'Cache-Control: no-cache',
                            'Pragma: no-cache',
                            'Upgrade-Insecure-Requests: 1',
                            'Sec-Fetch-Dest: document',
                            'Sec-Fetch-Mode: navigate',
                            'Sec-Fetch-Site: none',
                            'Sec-Fetch-User: ?1'
                        ];
                        
                        foreach ($additionalHeaders as $addHeader) {
                            $headerFound = false;
                            $headerKey = explode(':', $addHeader)[0] . ':';
                            foreach ($currentHeaders as &$header) {
                                if (stripos($header, $headerKey) === 0) {
                                    $header = $addHeader;
                                    $headerFound = true;
                                    break;
                                }
                            }
                            if (!$headerFound) {
                                $currentHeaders[] = $addHeader;
                            }
                        }
                        
                        $attempt++;
                   
                        continue; // Retry with new cookie
                    }
                } else {
                    $error = $cfResult['error'] ?? 'Unknown error';
            
                    break;
                }
            }

            // Success - no Cloudflare detected
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

    // Parse headers from the callback
    $parsedHeader = parseHeaders($rawHeaders);

    return [
        "header" => $parsedHeader,
        "body"   => $response,
        "info"   => $info
    ];
}
// ============================================
// PARSE HEADERS HELPER
// ============================================
function parseHeaders($rawHeaders) {
    $headers = [];
    $lines = explode("\r\n", $rawHeaders);
    
    if (!empty($lines[0])) {
        $headers['status_line'] = $lines[0];
        if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $lines[0], $matches)) {
            $headers['http_code'] = (int)$matches[1];
        }
    }
    
    for ($i = 1; $i < count($lines); $i++) {
        $line = $lines[$i];
        if (empty($line)) break;
        
        $parts = explode(': ', $line, 2);
        if (count($parts) == 2) {
            $key = strtolower($parts[0]);
            $value = $parts[1];
            
            if (isset($headers[$key])) {
                if (is_array($headers[$key])) {
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = [$headers[$key], $value];
                }
            } else {
                $headers[$key] = $value;
            }
        }
    }
    
    return $headers;
}

// ============================================
// CHECK CLOUDFLARE - FIXED
// ============================================
function check_cloudflare($url, $host, $email, $proxy = null) {
    $proxyHeaders = getProxyForHeaders($proxy);
    $response = Run("$url/", getRequestHeaders($host, $proxyHeaders), null, "data", $proxy, 0, $email);
    $html = $response['body'];
    
    if (in_array($response['info']['http_code'], [301, 404])) {
        $response = Run($url, getRequestHeaders($host, $proxyHeaders), null, "data", $proxy, 0, $email);
        $html = $response['body'];
    }
    
    if (preg_match('/Just a moment.../', $html)) {
        $cfResult = bypassCloudFlare($url, $proxy);
        if ($cfResult && $cfResult['success']) {
            // Save the bypass result
            if (!empty($cfResult['cloudflare_cookie'])) {
                saveCfCookieAndUserAgent($host, $cfResult['cloudflare_cookie'], $cfResult['user_agent'] ?? '', $proxy);
            }
            
            // Retry with new cookies
            $response = Run($url, getRequestHeaders($host, $proxyHeaders), null, "data", $proxy, 0, $email);
        }
    }
    
    return $response;
}

// ============================================
// UI/ANIMATION FUNCTIONS
// ============================================
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
    $colors = [GREEN, WHITE, YELLOW, BLUE, PURPLE, CYAN];
    $colorIndex = 0;
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

function x($a, $b, $r, $n = 1) {
    return trim(explode($b, explode($a, $r)[$n])[0]);
}

function clear() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        passthru('cls');
    } else {
        passthru('clear');
    }
}