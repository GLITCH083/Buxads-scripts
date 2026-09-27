<?php
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
// PROFESSIONAL LOGGING + CTRL+C SUPPORT
// ============================================
function enableCtrlC() {
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function () {
            echo "\n" . YELLOW . "⏹  Stopped by user (Ctrl+C)\n" . RESET;
            exit(0);
        });
        pcntl_signal(SIGTERM, function () {
            echo "\n" . YELLOW . "⏹  Terminated\n" . RESET;
            exit(0);
        });
    }
}


// ========== SHARED CRYSTAL BOX THEME (all scripts — same as btcadspace) ==========
if (!function_exists('themeOpen')) {
function theme_box_w() { return 61; }
function theme_plain($s) { return preg_replace('/\033\[[0-9;]*m/', '', (string)$s); }
function themeOpen($title) {
    $GLOBALS['theme_box_open'] = true;
    $w = theme_box_w();
    echo "\n";
    echo CYAN . "┌" . str_repeat("─", $w) . "┐\n" . RESET;
    $plain = theme_plain($title);
    $pad = max(1, $w - 2 - strlen($plain));
    echo CYAN . "│" . RESET . WHITE . "  " . $title . str_repeat(" ", max(0, $pad - 2)) . CYAN . "│\n" . RESET;
    echo CYAN . "├" . str_repeat("─", $w) . "┤\n" . RESET;
}
function themeRow($label, $value) {
    $w = theme_box_w();
    $plainV = theme_plain($value);
    if (strlen($plainV) > 42) {
        $plainV = substr($plainV, 0, 39) . "...";
        $value = $plainV;
    }
    $padV = max(1, $w - 16 - strlen($plainV));
    echo CYAN . "│" . RESET . WHITE . "  " . str_pad((string)$label, 12) . ": " . $value . str_repeat(" ", $padV) . CYAN . "│\n" . RESET;
}

/** Print a status line inside open box, or normal line if no box open */
function themeLiveMsg($msg, $color = null) {
    if (!empty($GLOBALS['theme_box_open']) && function_exists('themeRow')) {
        themeRow("Status", ($color ? $color : YELLOW) . theme_plain($msg) . RESET);
        return;
    }
    if ($color && defined('RESET')) {
        echo $color . $msg . RESET . "\n";
    } else {
        echo $msg . "\n";
    }
}

function themeClose() {
    echo CYAN . "└" . str_repeat("─", theme_box_w()) . "┘\n" . RESET;
    $GLOBALS['theme_box_open'] = false;
}
function themeStatus($title, $rows) {
    themeOpen($title);
    foreach ($rows as $label => $value) themeRow($label, $value);
    themeClose();
}
/** One-shot status box (account header, login, wait, error, etc.) */
function themeBox($title, $rows = []) {
    themeStatus($title, $rows);
}
/** Account header — used by every script */
function themeAccount($email, $proxy = null, $extra = []) {
    $rows = ["Account" => GREEN . $email . RESET];
    if ($proxy) $rows["Proxy"] = CYAN . $proxy . RESET;
    foreach ($extra as $k => $v) $rows[$k] = $v;
    themeStatus("👤  ACCOUNT", $rows);
}
/**
 * Login — ONE box with everything inside
 * $extra can hold: Account, Mode, Captcha, Token Len, Token, etc.
 */
function themeLogin($ok, $detail = "", $extra = []) {
    $rows = [];
    foreach ($extra as $k => $v) {
        $rows[$k] = $v;
    }
    if ($detail !== "" && $detail !== null) {
        if (!isset($rows["Detail"]) && !isset($rows["Account"])) {
            $rows["Account"] = WHITE . $detail . RESET;
        } elseif (!isset($rows["Detail"])) {
            $rows["Detail"] = WHITE . $detail . RESET;
        }
    }
    if ($ok) {
        $rows["Status"] = GREEN . "Successful" . RESET;
        themeStatus("✅  LOGIN", $rows);
    } else {
        $rows["Status"] = RED . "Failed" . RESET;
        themeStatus("❌  LOGIN FAILED", $rows);
    }
}

/**
 * Captcha-only box (optional). Prefer putting captcha rows into logClaim instead.
 */
function themeCaptcha($type, $tokenLen = 0, $tokenPreview = "", $extra = []) {
    $rows = ["Type" => YELLOW . $type . RESET];
    if ($tokenLen > 0) {
        $rows["Token Len"] = GREEN . (string)$tokenLen . RESET;
        if ($tokenPreview !== "") $rows["Token"] = GREY . $tokenPreview . RESET;
    }
    foreach ($extra as $k => $v) $rows[$k] = $v;
    themeStatus("🔐  CAPTCHA", $rows);
}
}

/**
 * LIVE claim box — open at start, add rows as work happens, close at end.
 * Labels use emojis as requested.
 */
function claimBoxOpen($title = "✅  CLAIM") {
    themeOpen($title);
}

function claimBoxRow($emojiLabel, $value) {
    // $emojiLabel e.g. "🧩 Captcha", "🔑 Token Len", "🎁 Reward"
    $w = theme_box_w();
    $label = (string)$emojiLabel;
    $plainL = theme_plain($label);
    $plainV = theme_plain($value);
    if (strlen($plainV) > 40) {
        $plainV = substr($plainV, 0, 37) . "...";
        $value = $plainV;
    }
    // label width ~14 visible chars for alignment
    $labPad = max(1, 14 - strlen($plainL));
    $line = $label . str_repeat(" ", $labPad) . ": " . $value;
    $plainLine = theme_plain($line);
    $pad = max(1, $w - 2 - strlen($plainLine));
    echo CYAN . "│" . RESET . WHITE . "  " . $line . str_repeat(" ", max(0, $pad - 2)) . CYAN . "│\n" . RESET;
}

function claimBoxClose() {
    themeClose();
}

/**
 * Final one-shot claim box (emoji labels). Prefer live open/row/close when possible.
 *
 * Order:
 *   🧩 Captcha
 *   🔑 Token Len
 *   🔐 Token
 *   📊 Claim
 *   🎁 Reward
 *   💰 Balance
 */
function logClaim($claims, $total, $reward, $balance, $apiBalance = null, $extra = []) {
    // Fallback from last successful captcha solve
    if (empty($extra["Type"]) || $extra["Type"] === "Unknown") {
        if (!empty($GLOBALS["last_captcha_type"])) {
            $extra["Type"] = $GLOBALS["last_captcha_type"];
        }
    }
    if (empty($extra["Token Len"]) || (int)$extra["Token Len"] === 0) {
        if (!empty($GLOBALS["last_captcha_len"])) {
            $extra["Token Len"] = $GLOBALS["last_captcha_len"];
        }
    }
    if (empty($extra["Token"])) {
        if (!empty($GLOBALS["last_captcha_token"])) {
            $tok = $GLOBALS["last_captcha_token"];
            $extra["Token"] = substr($tok, 0, 22) . (strlen($tok) > 22 ? "..." : "");
            if (empty($extra["Token Len"])) $extra["Token Len"] = strlen($tok);
        }
    }

    claimBoxOpen("✅  CLAIM SUCCESSFUL");

    $type = isset($extra["Type"]) ? (string)$extra["Type"] : "";
    if ($type !== "" && strcasecmp($type, "Unknown") !== 0) {
        claimBoxRow("🧩 Captcha", YELLOW . $type . RESET);
    }
    $tlen = isset($extra["Token Len"]) ? (int)$extra["Token Len"] : 0;
    if ($tlen > 0) {
        claimBoxRow("🔑 Token Len", GREEN . $tlen . RESET);
    }
    $tok = isset($extra["Token"]) ? (string)$extra["Token"] : "";
    if ($tok !== "") {
        claimBoxRow("🔐 Token", GREY . $tok . RESET);
    }
    if (!empty($extra["URL"])) {
        claimBoxRow("🔗 URL", CYAN . $extra["URL"] . RESET);
    }
    if (!empty($extra["Antibot"])) {
        claimBoxRow("🧩 Antibot", WHITE . $extra["Antibot"] . RESET);
    }

    claimBoxRow("📊 Claim", GREEN . $claims . WHITE . " / " . YELLOW . $total . RESET);
    claimBoxRow("🎁 Reward", GREEN . "+" . $reward . RESET);
    claimBoxRow("💰 Balance", YELLOW . $balance . RESET);

    if ($apiBalance !== null && $apiBalance !== "") {
        claimBoxRow("💎 Api Token", PURPLE . $apiBalance . RESET);
    }

    claimBoxClose();
}

function logInfo($title, $data = []) {
    $rows = [];
    foreach ($data as $k => $v) {
        $rows[$k] = GREEN . $v . RESET;
    }
    if (empty($rows)) $rows["Info"] = $title;
    themeStatus($title, $rows);
}

function logFail($title, $detail = "") {
    themeStatus("❌  " . $title, [
        "Status" => RED . "Failed" . RESET,
        "Detail" => $detail ?: "-",
    ]);
}

function logWait($msg, $seconds = 0) {
    $rows = ["Action" => YELLOW . $msg . RESET];
    if ($seconds > 0) $rows["Wait"] = WHITE . $seconds . "s" . RESET;
    themeStatus("⏳  WAITING", $rows);
}


// ============================================
// SAVE DATA FUNCTION
// ============================================
function saveData($folder, $filename) {
    $base = __DIR__ . "/../configs/{$folder}-config";
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
    $dir = __DIR__ . "/../configs/{$host}-config";
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
    $dir = __DIR__ . "/../configs/{$host}-config";
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
    $dir = __DIR__ . "/../configs/{$host}-config";
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
    $folder = __DIR__ . "/../configs/{$host}-config";
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

        $response = curl_exec($ch);
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
    // Compact: one updating line OR one box row (no spam stacks)
    $plain = preg_replace('/\033\[[0-9;]*m/', '', (string)$message);
    if (!empty($GLOBALS['theme_box_open']) && function_exists('themeRow')) {
        static $lastAnim = '';
        if ($plain === $lastAnim) return;
        $lastAnim = $plain;
        themeRow("Captcha", YELLOW . $plain . RESET);
        return;
    }
    // single-line status (overwrite)
    $color = defined("YELLOW") ? YELLOW : "";
    $reset = defined("RESET") ? RESET : "";
    echo "\r" . $color . "🧩 " . $plain . $reset . str_repeat(" ", 8);
    if (function_exists("flush")) @flush();
    return;
    $width = 60; // unreachable legacy
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

if (!function_exists('parseRewardMsg')) {
/** HAR-accurate: claim POSTs return 303; reward is in Swal.fire on the next page. */
function parseRewardMsg($body, $fallback = "Claim successful") {
    $body = (string)$body;
    if ($body === "") return $fallback;
    // SweetAlert2 from HAR: Swal.fire({ icon: 'success', html: '...', text: '...' })
    if (preg_match_all("/Swal\\.fire\\(\\{([\\s\\S]*?)\\}\\)/", $body, $blocks)) {
        foreach ($blocks[1] as $block) {
            if (stripos($block, "Ad Blocker") !== false) continue;
            if (stripos($block, "Copied!") !== false) continue;
            if (stripos($block, "icon:\\s*'error'") !== false || stripos($block, 'icon: "error"') !== false) {
                if (preg_match("/html:\\s*['`]([^'`]+)['`]|text:\\s*['\"]([^'\"]+)['\"]/", $block, $mm)) {
                    $t = trim(html_entity_decode(strip_tags($mm[1] !== "" ? $mm[1] : $mm[2])));
                    if ($t !== "") return $t;
                }
                continue;
            }
            if (preg_match("/html:\\s*['`]([^'`]{3,200})['`]/", $block, $mm)) {
                $t = trim(html_entity_decode(strip_tags($mm[1])));
                if ($t !== "" && stripos($t, "Redirecting") === false) return $t;
            }
            if (preg_match("/text:\\s*['\"]([^'\"]{3,200})['\"]/", $block, $mm)) {
                $t = trim(html_entity_decode(strip_tags($mm[1])));
                if ($t !== "" && stripos($t, "clipboard") === false) return $t;
            }
        }
    }
    // JSON
    $j = @json_decode($body, true);
    if (is_array($j)) {
        foreach (array("message", "msg", "reward", "text", "status_message") as $k) {
            if (!empty($j[$k]) && is_string($j[$k])) {
                $t = trim(html_entity_decode(strip_tags($j[$k])));
                if ($t !== "") return $t;
            }
        }
    }
    if (preg_match('/class="[^"]*alert-success[^"]*"[^>]*>([\\s\\S]{3,200}?)<\\/div>/i', $body, $m)) {
        $t = trim(html_entity_decode(strip_tags($m[1])));
        if ($t !== "") return $t;
    }
    if (preg_match('/You have (?:earned|received|claimed)[^<!.]{0,100}/i', $body, $m)) {
        return trim(preg_replace('/\\s+/', ' ', strip_tags($m[0])));
    }
    return $fallback;
}
}


if (!function_exists('claimRewardFromRedirect')) {
/** After claim POST (303), follow Location or same path and parse Swal reward. */
function claimRewardFromRedirect($postResult, $fallbackUrl, $proxy, $email, $fallbackMsg = "Claim successful") {
    $body = is_array($postResult) ? ($postResult["body"] ?? "") : "";
    $msg = parseRewardMsg($body, "");
    if ($msg !== "") return $msg;
    $loc = $fallbackUrl;
    if (is_array($postResult)) {
        $hdr = $postResult["header"] ?? [];
        if (is_array($hdr)) {
            if (!empty($hdr["location"])) {
                $loc = trim(is_array($hdr["location"]) ? $hdr["location"][0] : $hdr["location"]);
            } elseif (!empty($hdr["Location"])) {
                $loc = trim(is_array($hdr["Location"]) ? $hdr["Location"][0] : $hdr["Location"]);
            }
        }
        $info = $postResult["info"] ?? [];
        if (!empty($info["redirect_url"])) $loc = $info["redirect_url"];
    }
    if (!$loc) $loc = $fallbackUrl;
    if ($loc && strpos($loc, "http") !== 0 && defined("SITE")) {
        $loc = rtrim(SITE, "/") . "/" . ltrim($loc, "/");
    }
    $h = function_exists("headers") ? headers() : array();
    $r = Run($loc, $h, null, null, $proxy, 0, $email);
    return parseRewardMsg($r["body"] ?? "", $fallbackMsg);
}
}


