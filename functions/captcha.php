<?php
include_once(__DIR__ . "/function.php");
include(__DIR__ . "/rss.php");

function get_balance(){
    $data = json_encode([
        "apikey" => anticaptcha_key,
        "mode" => "getbalance"
    ]);

    $r = json_decode(Run(api_endpoint, ["Content-Type: application/json"],$data)['body'],true);
    if(isset($r['balance'])){
        return number_format((float)$r['balance'], 4, ".", "");
    }else{
        null;
    }
}

function poll($id){
    $maxTries = 12;
    for ($i = 1; $i <= $maxTries; $i++) {
    $json = json_encode(["apikey" => anticaptcha_key,"action" => "get","id" => $id]);
    $r = json_decode(Run(api_endpoint,["Content-Type: application/json"],$json)['body'],true);
    if (isset($r["status"]) && $r["status"] === true) {
        return $r;
     }elseif($r['status'] == "pending"){
         animation("[$id] Getting Captcha...");
         sleep(5);
     }else{
        return $r;
     }
    }
}

function captcha($url,$sitekey,$method,$action = null){
    $json = [
    "apikey" => anticaptcha_key,
    "mode" => $method,
    "domain" => $url,
    "siteKey" => $sitekey
    ];

    if($action){
        $json['action'] = $action;
    }

    
    $r = json_decode(Run(api_endpoint,["Content-Type: application/json"],json_encode($json))['body'],true);

    if(isset($r['jobId'])){
        return poll($r['jobId']);
    }
}

function upsidedown($bs64,$mode = 'upsidedown'){
    $json = json_encode([
    "apikey" => anticaptcha_key,
    "mode" => $mode,
    "image" => $bs64
    ]);

    $r = json_decode(Run(api_endpoint,["Content-Type: application/json"],$json)['body'],true);

    if(isset($r['jobId'])){
        return poll($r['jobId']);
    }
}

function solve_adslab($sitekey, $domain, $subid = "widget_user") {
    $data = json_encode([
        "apikey" => anticaptcha_key,
        "mode" => "adslab",
        "sitekey" => $sitekey,
        "domain" => $domain,
        "subid" => $subid
    ]);

    $create = json_decode(Run(api_endpoint, ["Content-Type: application/json"], $data)['body'], true);

    if (empty($create['jobId'])) {
        return ["success" => false, "message" => "Failed to create adslab job", "response" => $create];
    }else{
        return poll($create['jobId']);
    }
}

function rs_upsidedown(string $html, string $url): array {
    // 1. Fetch captcha image
    $imageBytes = (new RsCaptchaImage())->fetch($html, $url);
    if (!$imageBytes) return ['rs-response' => null];

        $coords = upsidedown(base64_encode($imageBytes),"rsv2");

    if (empty($coords['x']) || empty($coords['y'])) return ['rs-response' => null];

    $token = (new RsCaptchaBuilder())->build((int)$coords['x'], (int)$coords['y'], $html);
    
    return ['rs-response' => $token];
}

function rsv5($app_id,$public_key){
    $payload = [
        'apikey' => anticaptcha_key,
        'mode' => 'rsv5',
        'appId' => $app_id,
        'publicKey' => $public_key
    ];
    
    $response = json_decode(Run(api_endpoint, ['Content-Type: application/json'], json_encode($payload))['body'], true);
    
    if (empty($response['jobId'])) return null;

    return poll($response['jobId']);
}

function rs($html,$ua){
    $app_id = trim(explode('&', explode('app_id=', $html)[1])[0]);
    $public_key = trim(explode('&', explode('public_key=', $html)[1])[0]);
    
    $data = rsv5($app_id,$public_key);

    if($data['captcha_key']){
        return [
            "rs-id" => $data['captcha_key'],
            "rs-token" => $data['token'],
        ];
    }else{
            return [
                "rs-id" => null,
                "rs-token" => null,
            ];
        }
    }

    function bypassCloudFlare($url_, $proxy = null) {

        $host = parse_url($url_, PHP_URL_HOST);
        $baseDir = BASE_DIR . "/configs/{$host}-config/";
    
        // Ensure config directory exists
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
    
        // ------------------------------------------------------------
        // CASE 1: NO PROXY – use localhost service
        // ------------------------------------------------------------
        if (empty($proxy)) {
            $url = 'http://localhost:7860/bypass';
    
            $data = [
                'mode'   => 'iuam',
                'domain' => $url_,
            ];
    
            $jsonData = json_encode($data);
    
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]);
    
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);
    
            if ($error) {
                return ["success" => false, "error" => "cURL Error (local): $error"];
            }
            if ($httpCode !== 200) {
                return ["success" => false, "error" => "HTTP Error (local): $httpCode, Response: $response"];
            }
    
            $resp = json_decode($response, true);
            $cloudflare_cookie = $resp['cf_clearance'] ?? null;
            $user_agent        = $resp['user_agent']   ?? '';
    
            if ($cloudflare_cookie) {
                // Save cookie (no proxy, so use generic filename)
                file_put_contents($baseDir . "cf_cookie.txt", "cf_clearance " . $cloudflare_cookie);
                file_put_contents($baseDir . "userAgent", $user_agent);
    
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
            ];
        }
    
        // ------------------------------------------------------------
        // CASE 2: PROXY PROVIDED – use captcha API
        // ------------------------------------------------------------
        $proxyHost = null;
    
        // Prepare API payload
        $payload = [
            'apikey' => anticaptcha_key,   // must be defined globally
            'mode'   => 'iuam',
            'domain' => $url_
        ];
    
        // Normalise proxy string
        if (!preg_match('#^https?://#i', $proxy)) {
            $proxy = "http://{$proxy}";
        }
        $parts = parse_url($proxy);
    
        if (!empty($parts['host'])) {
            $proxyData = [
                'hostname' => $parts['host'],
                'port'     => $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80),
                'scheme'   => $parts['scheme'] ?? 'http'
            ];
            $proxyHost = $parts['host'];
            $proxyPort = $parts['port'];
    
            if (!empty($parts['user']) && !empty($parts['pass'])) {
                $proxyData['username'] = $parts['user'];
                $proxyData['password'] = $parts['pass'];
            }
    
            $payload['proxy'] = $proxyData;
        }
    
         print_r($payload);
    
    
        // Submit task to API
        $jsonPayload = json_encode($payload);
        $ch = curl_init(api_endpoint);   // must be defined globally
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
    
        if ($error) {
            return ["success" => false, "error" => "cURL Error (API submit): $error"];
        }
        if ($httpCode !== 200) {
            return ["success" => false, "error" => "HTTP Error (API submit): $httpCode, Response: $response"];
        }
    
        $submitResult = json_decode($response, true);
        
    
        // Check for immediate API errors
        if (isset($submitResult['error'])) {
            if (stripos($submitResult['error'], 'Insufficient balance') !== false) {
                exit("ERROR: No API balance available. Please top up your captcha service account.\n");
            }
            return ["success" => false, "error" => $submitResult['error']];
        }
    
        if (empty($submitResult['jobId'])) {
            return ["success" => false, "error" => "No jobId received from API"];
        }
    
        $jobId = $submitResult['jobId'];
        $pollResult = poll($jobId);
         print_r($pollResult);
    
            // Success – extract cf_clearance and user_agent
            $cloudflare_cookie = $pollResult['cookie'] ?? null;
            $user_agent        = $pollResult['user_agent']   ??  $pollResult['UserAgent']   ??  $pollResult['Useragent']   ??  $pollResult['userAgent'] ?? null;
    
            if ($cloudflare_cookie) {
                // Save cookie with proxy‑specific filename
                $cookieFilename = empty($proxyHost) ? "cf_cookie.txt" : "{$proxyHost}-{$proxyPort}-cf_cookies.txt";
                file_put_contents($baseDir . $cookieFilename, "cf_clearance " . $cloudflare_cookie);
                file_put_contents($baseDir . "userAgent", $user_agent);
    
                return [
                    "cloudflare_cookie" => $cloudflare_cookie,
                    "user_agent"        => $user_agent,
                    "success"           => true,
                ];
            }
    
            // If we got here, the API returned an unexpected format
            return [
                "cloudflare_cookie" => null,
                "user_agent"        => $user_agent,
                "success"           => false,
                "error"             => "API did not return cf_clearance"
            ];
        }
    

        function Bypass($url){


            $data = json_encode([
                "apikey" => anticaptcha_key,
                "mode" => "shortlink",
                "url" => $url
            ]);
        
            $response = json_decode(Run(api_endpoint,["Content-Type: application/json"],$data)['body'],true);
            if(isset($response['jobId'])){
                $id = $response['jobId'];
               
                $data = json_encode([
                    "apikey" => anticaptcha_key,
                    "action" => "get",
                    "id" => $id
                ]);
        
                $attempt = 0;
                $attempt_max = 20;
                while($attempt < $attempt_max){
                    $response = json_decode(Run(api_endpoint,["Content-Type: application/json"],$data)['body'],true);
                    if (isset($response["status"]) && $response["status"] === true) {
                       return $response;
                    }elseif($response['status'] == "pending"){
                        sleep(5);
                        $attempt += 1;
                    }else{
                       return $response;
                    }
                }
            }
        }

        function antibot($html){
            $p = explode('data:image/png;base64,',$html);
            $count = count($p) - 2;
            if($count < 3 || $count > 4) return null;
            $cut = fn($s) => explode('"',$s)[0];
            $data = [ 
                'apikey' => anticaptcha_key,
                'mode' => 'freeantibot',
                'main' => $cut($p[1]),
                'sub' => []
            ];
        
            for($i = 1; $i <= $count; $i++){
                $label = x('rel=\"','\"',$html,$i);
                $data['sub'][$label] = $cut($p[$i+1]);
            }
            $r = Run(api_endpoint,["Content-Type: application/json"],json_encode($data,JSON_UNESCAPED_SLASHES));
            
            $r = json_decode($r['body'],true);
        
            if(!empty($r['jobId'])){
                $json = json_encode(["apikey" => anticaptcha_key,"action" => "get","id" => $r['jobId']]);
            }
        
            while(true){
                $r = json_decode(Run(api_endpoint,["Content-Type: application/json"],$json)['body'],true);
                
                if($r['order']){
                    $bot = " " . str_replace(" "," ",$r['order']);
                    break;
                }else{
                    animation("Getting AntibotLinks");
                    sleep(5);
                }
            }
        
            if($bot){
        
                #animation("Got Free AB: $bot");
                return $bot;
        
            }
        }
        
        
            