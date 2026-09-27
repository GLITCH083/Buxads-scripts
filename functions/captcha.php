<?php
include_once(__DIR__ . "/function.php");
include(__DIR__ . "/rss.php");

// Vernuable endpoints (docs style)
define("api_in",  "https://vernuable.my.id/in.php");
define("api_res", "https://vernuable.my.id/res.php");
// optional if you still need a single constant somewhere:
define("api_endpoint", api_in);

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


/**
 * Captcha API error codes → meaning + action
 */
function captchaErrorInfo($code) {
    $code = strtoupper(trim((string)$code));
    $map = [
        "CAPCHA_NOT_READY"         => ["Still solving", "Poll again"],
        "CAPTCHA_NOT_READY"        => ["Still solving", "Poll again"],
        "ERROR_WRONG_USER_KEY"     => ["Invalid key", "Check anticaptcha / vernuable key"],
        "ERROR_KEY_DOES_NOT_EXIST" => ["Unknown key", "Regenerate / use linked account key"],
        "ERROR_ZERO_BALANCE"       => ["No balance", "Deposit funds on captcha provider"],
        "ERROR_INVALID_METHOD"     => ["Unknown method", "Use: turnstile, iuam, adslab, antibot, hcaptcha"],
        "ERROR_WRONG_CAPTCHA_ID"   => ["Bad or expired job id", "Submit a new task"],
        "ERROR_CAPTCHA_UNSOLVABLE" => ["Failed to solve", "Retry"],
        "ERROR_NO_SLOT_AVAILABLE"  => ["No solver slot", "Wait and retry"],
        "TIMEOUT"                  => ["Poll timeout", "Retry task"],
    ];
    if (isset($map[$code])) {
        return ["code" => $code, "meaning" => $map[$code][0], "action" => $map[$code][1]];
    }
    if (strpos($code, "ERROR_") === 0) {
        return ["code" => $code, "meaning" => "API error", "action" => "Check key / balance / method"];
    }
    return ["code" => $code, "meaning" => "Unknown", "action" => "Retry or check logs"];
}

function captchaLogError($code) {
    $info = captchaErrorInfo($code);
    $msg = $info["code"] . " · " . $info["meaning"] . " → " . $info["action"];
    if (function_exists("themeStatus")) {
        themeStatus("❌  CAPTCHA ERROR", [
            "Code"    => YELLOW . $info["code"] . RESET,
            "Meaning" => RED . $info["meaning"] . RESET,
            "Action"  => WHITE . $info["action"] . RESET,
        ]);
    } elseif (defined("RED") && defined("YELLOW") && defined("RESET")) {
        echo RED . "✖ Captcha: " . YELLOW . $msg . RESET . "\n";
    } else {
        echo "✖ Captcha: $msg\n";
    }
    return $info;
}


/** Echo captcha status into live box when open, else normal line */
function captchaEcho($msg, $kind = "wait") {
    // Compact: one updating line (no spam). Final OK prints once with newline.
    $plain = preg_replace('/\033\[[0-9;]*m/', '', (string)$msg);
    $color = defined("YELLOW") ? YELLOW : "";
    if ($kind === "ok" && defined("GREEN")) $color = GREEN;
    if ($kind === "err" && defined("RED")) $color = RED;
    $reset = defined("RESET") ? RESET : "";

    // Inside open claim box — one status row only (overwrite via same label)
    if (!empty($GLOBALS["theme_box_open"]) && function_exists("themeRow") && $kind !== "ok") {
        // throttle repeated wait rows: only print every 3rd or on change
        static $lastBoxMsg = "";
        if ($plain === $lastBoxMsg) return;
        $lastBoxMsg = $plain;
        $label = "Status";
        themeRow($label, $color . $plain . $reset);
        return;
    }
    if (!empty($GLOBALS["theme_box_open"]) && function_exists("themeRow") && $kind === "ok") {
        themeRow("Captcha", $color . $plain . $reset);
        return;
    }

    // Outside box: single-line animation with \r (does not stack)
    if ($kind === "wait") {
        $line = $color . $plain . $reset;
        // pad/clear previous longer line
        echo "\r" . $line . str_repeat(" ", max(0, 12)) . "\r";
        if (function_exists("flush")) @flush();
        return;
    }
    // OK / ERR: clear wait line then print final
    echo "\r" . str_repeat(" ", 80) . "\r";
    if ($color) echo $color . $plain . $reset . "\n";
    else echo $plain . "\n";
}

/** Live countdown on one line while waiting for solver slot */
function captchaWaitAnim($seconds, $label = "⏳ Slot busy · retry") {
    $seconds = max(1, (int)$seconds);
    $color = defined("YELLOW") ? YELLOW : "";
    $reset = defined("RESET") ? RESET : "";
    if (!empty($GLOBALS["theme_box_open"]) && function_exists("themeRow")) {
        themeRow("Status", $color . $label . " {$seconds}s" . $reset);
        sleep($seconds);
        return;
    }
    for ($s = $seconds; $s >= 1; $s--) {
        $line = $color . $label . " · {$s}s   " . $reset;
        echo "\r" . $line;
        if (function_exists("flush")) @flush();
        sleep(1);
    }
    echo "\r" . str_repeat(" ", 70) . "\r";
}


function poll($id) {
    $maxTries = 40;
    for ($i = 1; $i <= $maxTries; $i++) {
        $json = json_encode([
            "key"    => anticaptcha_key,
            "action" => "get",
            "id"     => $id,
            "json"   => "1",
        ]);
        $r = json_decode(Run(api_res, ["Content-Type: application/json"], $json)['body'], true);
        if (!is_array($r)) {
            $r = ["status" => 0, "request" => "ERROR_INVALID_RESPONSE"];
        }

        $req = (string)($r["request"] ?? "");
        $status = (int)($r["status"] ?? 0);

        // ready
        if ($status === 1) {
            if (!empty($r["request"]) && is_string($r["request"]) && isset($r["request"][0]) && $r["request"][0] === "{") {
                $j = json_decode($r["request"], true);
                if (is_array($j)) {
                    $r = array_merge($r, $j);
                }
            }
            if (empty($r["order"]) && !empty($r["request"]) && is_string($r["request"])
                && preg_match('/^\s*\d+(\s+\d+)+\s*$/', $r["request"])) {
                $r["order"] = trim($r["request"]);
            }
            if (empty($r["token"]) && !empty($r["request"]) && is_string($r["request"])
                && isset($r["request"][0]) && $r["request"][0] !== "{") {
                $reqStr = (string)$r["request"];
                $reqUp = strtoupper($reqStr);
                // Do NOT promote error codes to token
                if (strpos($reqUp, "ERROR_") !== 0 && $reqUp !== "CAPCHA_NOT_READY" && $reqUp !== "TIMEOUT") {
                    $r["token"] = $reqStr;
                }
                if ((function_exists("str_starts_with") && str_starts_with($reqStr, "http"))
                    || strpos($reqStr, "http") === 0) {
                    $r["original_url"] = $reqStr;
                }
            }
            if (empty($r["cookie"]) && !empty($r["cf_clearance"])) {
                $r["cookie"] = $r["cf_clearance"];
            }
            return $r;
        }

        // still solving → poll again
        if ($req === "CAPCHA_NOT_READY" || $req === "CAPTCHA_NOT_READY") {
            if (function_exists("animation")) {
                animation("🧩 Solving captcha...");
            }
            sleep(3);
            continue;
        }

        // soft: no slot → wait and keep trying (do NOT fail user login)
        $reqUp = strtoupper($req);
        if ($reqUp === "ERROR_NO_SLOT_AVAILABLE" || strpos($reqUp, "NO_SLOT") !== false) {
            $wait = min(15, 3 + $i * 2);
            if (defined("YELLOW") && defined("RESET")) {
                captchaEcho("⏳ No solver slot · waiting {$wait}s then retry... (" . $i . "/" . $maxTries . ")", "wait");
            }
            sleep($wait);
            continue;
        }

        // soft: unsolvable on poll → stop this job (caller will resubmit) — no scary error box
        if ($reqUp === "ERROR_CAPTCHA_UNSOLVABLE") {
            if (defined("YELLOW") && defined("RESET")) {
                captchaEcho("⏳ Captcha unsolvable · will resubmit new task...", "wait");
            }
            $r["error_info"] = captchaErrorInfo($req);
            $r["success"] = false;
            $r["retryable"] = true;
            return $r;
        }

        // fatal key/balance/method — stop for real
        $fatal = [
            "ERROR_WRONG_USER_KEY",
            "ERROR_KEY_DOES_NOT_EXIST",
            "ERROR_ZERO_BALANCE",
            "ERROR_INVALID_METHOD",
            "ERROR_WRONG_CAPTCHA_ID",
        ];
        if (in_array($reqUp, $fatal, true)) {
            captchaLogError($req);
            $r["error_info"] = captchaErrorInfo($req);
            $r["success"] = false;
            $r["retryable"] = false;
            return $r;
        }

        // other ERROR_* — brief wait then continue a few times
        if (strpos($reqUp, "ERROR_") === 0) {
            if ($i < 8) {
                $wait = 3 + $i;
                if (defined("YELLOW") && defined("RESET")) {
                    captchaEcho("⏳ " . $reqUp . " · wait {$wait}s retry...", "wait");
                }
                sleep($wait);
                continue;
            }
            captchaLogError($req);
            $r["error_info"] = captchaErrorInfo($req);
            $r["success"] = false;
            return $r;
        }

        if ($i < 5) {
            sleep(2);
            continue;
        }
        captchaLogError($req !== "" ? $req : "UNKNOWN");
        $r["error_info"] = captchaErrorInfo($req !== "" ? $req : "UNKNOWN");
        return $r;
    }
    captchaLogError("TIMEOUT");
    return ["status" => 0, "request" => "TIMEOUT", "error_info" => captchaErrorInfo("TIMEOUT"), "success" => false];
}


function captcha($url, $sitekey, $method, $action = null) {
    $maxSubmit = 8;
    $last = null;

    for ($attempt = 1; $attempt <= $maxSubmit; $attempt++) {
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
            $json["action"] = $action;
        }

        $r = json_decode(Run(api_in, ["Content-Type: application/json"], json_encode($json))["body"], true);
        if (!is_array($r)) {
            $last = ["status" => 0, "request" => "ERROR_INVALID_RESPONSE", "success" => false];
            if ($attempt < $maxSubmit) {
                sleep(3);
                continue;
            }
            captchaLogError("ERROR_INVALID_RESPONSE");
            return $last;
        }

        // job created → poll
        if (isset($r["request"]) && (int)($r["status"] ?? 0) === 1) {
            $result = poll($r["request"]);
            // poll success
            if (is_array($result) && (int)($result["status"] ?? 0) === 1) {
                $tok = (string)($result["token"] ?? $result["request"] ?? "");
                // Never treat API error codes as tokens (e.g. ERROR_NO_SLOT_AVAILABLE = len 23)
                $tokUp = strtoupper(trim($tok));
                $isErr = ($tok === "" || strpos($tokUp, "ERROR_") === 0 || $tokUp === "CAPCHA_NOT_READY"
                    || $tokUp === "TIMEOUT" || $tokUp === "CAPTCHA_NOT_READY");
                $minLen = (stripos($method, "turnstile") !== false) ? 80 : 20;
                if ($isErr || strlen($tok) < $minLen) {
                    if (defined("YELLOW")) {
                        captchaEcho("⏳ Bad token · " . substr($tokUp, 0, 40) . " · retry", "wait");
                    }
                    $last = ["status" => 0, "request" => $tokUp ?: "BAD_TOKEN", "success" => false, "retryable" => true];
                    if ($attempt < $maxSubmit) {
                        sleep(3 + $attempt);
                        continue;
                    }
                    captchaLogError($tokUp ?: "BAD_TOKEN");
                    return $last;
                }
                $result["token"] = $tok;
                $result["request"] = $tok;
                $GLOBALS["last_captcha_type"] = $method;
                $GLOBALS["last_captcha_token"] = $tok;
                $GLOBALS["last_captcha_len"] = strlen($tok);
                if (defined("GREEN")) {
                    captchaEcho("✔ Captcha OK · " . $method . " · Len " . strlen($tok), "ok");
                }
                return $result;
            }
            // retryable poll failure (unsolvable / timeout)
            $req = strtoupper((string)($result["request"] ?? ""));
            $retryable = !empty($result["retryable"])
                || $req === "ERROR_CAPTCHA_UNSOLVABLE"
                || $req === "TIMEOUT"
                || $req === "ERROR_WRONG_CAPTCHA_ID";
            if ($retryable && $attempt < $maxSubmit) {
                $wait = 4 + $attempt * 2;
                if (defined("YELLOW") && defined("RESET")) {
                    captchaEcho("⏳ Captcha retry " . $attempt . "/" . $maxSubmit . " · " . $req . " · wait {$wait}s", "wait");
                }
                sleep($wait);
                $last = $result;
                continue;
            }
            return is_array($result) ? $result : $r;
        }

        // create failed
        $err = strtoupper((string)($r["request"] ?? $r["error"] ?? "SUBMIT_FAILED"));
        $last = $r;

        // NO SLOT / busy → wait and resubmit (never fail login on first no-slot)
        if ($err === "ERROR_NO_SLOT_AVAILABLE" || strpos($err, "NO_SLOT") !== false) {
            $wait = min(30, 5 + $attempt * 3);
            if (defined("YELLOW") && defined("RESET")) {
                captchaEcho("⏳ No solver slot · waiting {$wait}s then auto-retry (" . $attempt . "/" . $maxSubmit . ")", "wait");
            }
            sleep($wait);
            continue;
        }

        // unsolvable on create → retry
        if ($err === "ERROR_CAPTCHA_UNSOLVABLE" && $attempt < $maxSubmit) {
            $wait = 3 + $attempt;
            if (defined("YELLOW") && defined("RESET")) {
                captchaEcho("⏳ Unsolvable · resubmit in {$wait}s (" . $attempt . "/" . $maxSubmit . ")", "wait");
            }
            sleep($wait);
            continue;
        }

        // fatal key/balance — stop immediately
        if (in_array($err, ["ERROR_WRONG_USER_KEY", "ERROR_KEY_DOES_NOT_EXIST", "ERROR_ZERO_BALANCE", "ERROR_INVALID_METHOD"], true)) {
            captchaLogError($err);
            $r["error_info"] = captchaErrorInfo($err);
            $r["success"] = false;
            return $r;
        }

        // other errors: a few retries
        if ($attempt < $maxSubmit) {
            $wait = 3 + $attempt;
            if (defined("YELLOW") && defined("RESET")) {
                captchaEcho("⏳ " . $err . " · wait {$wait}s retry (" . $attempt . "/" . $maxSubmit . ")", "wait");
            }
            sleep($wait);
            continue;
        }

        captchaLogError($err);
        $r["error_info"] = captchaErrorInfo($err);
        $r["success"] = false;
        return $r;
    }

    if (is_array($last)) {
        captchaLogError((string)($last["request"] ?? "SUBMIT_FAILED"));
        $last["success"] = false;
        return $last;
    }
    return ["status" => 0, "request" => "SUBMIT_FAILED", "success" => false];
}

function upsidedown($bs64, $mode = 'upsidedown') {
    $maxSubmit = 8;
    for ($attempt = 1; $attempt <= $maxSubmit; $attempt++) {
        $json = json_encode([
            "key"    => anticaptcha_key,
            "method" => $mode,
            "image"  => $bs64,
            "json"   => "1",
        ]);
        $r = json_decode(Run(api_in, ["Content-Type: application/json"], $json)["body"], true);
        if (isset($r["request"]) && (int)($r["status"] ?? 0) === 1) {
            return poll($r["request"]);
        }
        $err = strtoupper((string)($r["request"] ?? ""));
        if ($err === "ERROR_NO_SLOT_AVAILABLE" || strpos($err, "NO_SLOT") !== false) {
            $wait = min(30, 5 + $attempt * 3);
            if (defined("YELLOW") && defined("RESET")) {
                captchaEcho("⏳ No solver slot · waiting {$wait}s then auto-retry (" . $attempt . "/" . $maxSubmit . ")", "wait");
            }
            sleep($wait);
            continue;
        }
        if (in_array($err, ["ERROR_WRONG_USER_KEY", "ERROR_KEY_DOES_NOT_EXIST", "ERROR_ZERO_BALANCE"], true)) {
            captchaLogError($err);
            return $r;
        }
        if ($attempt < $maxSubmit) {
            sleep(3 + $attempt);
            continue;
        }
        if ($err) captchaLogError($err);
        return $r;
    }
    return ["status" => 0, "request" => "SUBMIT_FAILED", "success" => false];
}

function solve_adslab($sitekey, $domain, $type = "static", $subid = "widget_user") {
    $data = json_encode([
        "key"     => anticaptcha_key,
        "method"  => "adslab",
        "sitekey" => $sitekey,
        "domain"  => $domain,
        "subid"   => $subid,
        "type"    => $type,
        "json"    => "1",
    ]);
    $create = json_decode(Run(api_in, ["Content-Type: application/json"], $data)['body'], true);

    if (empty($create['request']) || (int)($create['status'] ?? 0) !== 1) {
        return ["success" => false, "message" => "Failed to create adslab job", "response" => $create];
    }
    return poll($create['request']);
}

function rotation($base, $piece, $crop) {
    $data = json_encode([
        "key"    => anticaptcha_key,
        "method" => "rotation",
        "base"   => $base,
        "piece"  => $piece,
        "crop"   => $crop,
        "json"   => "1",
    ]);
    
    $create = json_decode(Run(api_in, ["Content-Type: application/json"], $data)['body'], true);
    if (empty($create['request']) || (int)($create['status'] ?? 0) !== 1) {
        return ["success" => false, "message" => "Failed to create rotation job", "response" => $create];
    }
    return poll($create['request']);
}

function motion($base) {
    $data = json_encode([
        "key"    => anticaptcha_key,
        "method" => "limefaucet",
        "image"  => $base,
        "json"   => "1",
    ]);
    $create = json_decode(Run(api_in, ["Content-Type: application/json"], $data)['body'], true);
    if (empty($create['request']) || (int)($create['status'] ?? 0) !== 1) {
        return ["success" => false, "message" => "Failed to create limefaucet job", "response" => $create];
    }
    return poll($create['request']);
}

function rs_upsidedown(string $html, string $url): array {
    $imageBytes = (new RsCaptchaImage())->fetch($html, $url);
    if (!$imageBytes) return ['rs-response' => null];

    $coords = upsidedown(base64_encode($imageBytes), "rsv2");
    if (empty($coords['x']) || empty($coords['y'])) return ['rs-response' => null];

    $token = (new RsCaptchaBuilder())->build((int)$coords['x'], (int)$coords['y'], $html);
    return ['rs-response' => $token];
}

function rsv5($app_id, $public_key) {
    $payload = [
        'key'       => anticaptcha_key,
        'method'    => 'rsv5',
        'appId'     => $app_id,
        'publicKey' => $public_key,
        'json'      => '1',
    ];
    $response = json_decode(Run(api_in, ['Content-Type: application/json'], json_encode($payload))['body'], true);
    if (empty($response['request']) || (int)($response['status'] ?? 0) !== 1) return null;
    return poll($response['request']);
}

function rs($html, $ua) {
    $app_id = trim(explode('&', explode('app_id=', $html)[1])[0]);
    $public_key = trim(explode('&', explode('public_key=', $html)[1])[0]);
    $data = rsv5($app_id, $public_key);
    if (!empty($data['captcha_key'])) {
        return ["rs-id" => $data['captcha_key'], "rs-token" => $data['token']];
    }
    return ["rs-id" => null, "rs-token" => null];
}

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
        $localEndpoint = 'http://localhost:7860/bypass';  // correct path

        $payload = [
            'apikey' => anticaptcha_key,
            'mode'   => 'iuam',
            'domain' => $url_,
        ];

        $response = Run($localEndpoint, ['Content-Type: application/json'], json_encode($payload));
        $result = json_decode($response['body'], true);

        // Local solver returns { success: true, cookie: "...", userAgent: "..." }
        // or { success: false, message: "..." } on error
        if (!empty($result['success']) && !empty($result['cf_clearance'])) {
            $cloudflare_cookie = $result['cf_clearance'];
            $user_agent = $result['userAgent'] ?? $result['user_agent'];

            // Save cookie with "cf_clearance " prefix (as in remote branch)
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

        // If local solver failed or returned an error
        $errorMsg = $result['message'] ?? 'Local solver failed (no cookie)';
        return [
            "success" => false,
            "error"   => $errorMsg,
            "raw"     => $result
        ];
    }

    // ------------------------------------------------------------
    // CASE 2: PROXY PROVIDED – use remote captcha API
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

    // Submit task to remote API (api_in is the endpoint)
    $create = json_decode(Run(api_in, ['Content-Type: application/json'], json_encode($payload))['body'], true);

    if (empty($create['request']) || (int)($create['status'] ?? 0) !== 1) {
        if (($create['request'] ?? '') === 'ERROR_ZERO_BALANCE') {
            exit("ERROR: No API balance available. Please top up your captcha service account.\n");
        }
        return ["success" => false, "error" => $create['request'] ?? 'create failed', "response" => $create];
    }

    // Poll for result (your poll() function handles retries)
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

function Bypass($url) {
    $data = json_encode([
        "key"    => anticaptcha_key,
        "method" => "shortlink",
        "url"    => $url,
        "json"   => "1",
    ]);
    $response = json_decode(Run(api_in, ["Content-Type: application/json"], $data)['body'], true);
    if (isset($response['request']) && (int)($response['status'] ?? 0) === 1) {
        return poll($response['request']);
    }
    return $response;
}

function antibot($html) {
    $p = explode('data:image/png;base64,', $html);
    $count = count($p) - 2;
    if ($count < 3 || $count > 4) return null;

    $cut = fn($s) => explode('"', $s)[0];
    $data = [
        'key'    => anticaptcha_key,
        'method' => 'antibot',
        'main'   => $cut($p[1]),
        'sub'    => [],
        'json'   => '1',
    ];
    for ($i = 1; $i <= $count; $i++) {
        $label = x('rel=\"', '\"', $html, $i);
        $data['sub'][$label] = $cut($p[$i + 1]);
    }

    $r = json_decode(
        Run(api_in, ["Content-Type: application/json"], json_encode($data, JSON_UNESCAPED_SLASHES))['body'],
        true
    );
    if (empty($r['request']) || (int)($r['status'] ?? 0) !== 1) return null;

    $res = poll($r['request']);
    if (!empty($res['order'])) {
        return " " . trim($res['order']);
    }
    if (!empty($res['request']) && preg_match('/^\s*\d+(\s+\d+)+\s*$/', $res['request'])) {
        return " " . trim($res['request']);
    }
    return null;
}

function pcaptcha_solve($image, $question) {
    $data = [
        "key"    => anticaptcha_key,
        "method" => "pcaptcha",
        "task"   => $question,
        "image"  => base64_encode($image),
        "json"   => "1",
    ];
    $js = json_decode(Run(api_in, ["Content-Type: application/json"], json_encode($data))['body'], true);
    if (isset($js['request']) && (int)($js['status'] ?? 0) === 1) {
        return poll($js['request']);
    }
    return null;
}

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

function solveIconCaptcha($icon_token, $host, $url, $captcha_url, $email, $proxy = null) {
    // unchanged from your version — still calls iconcaptcha()
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
        "initTimestamp" => $init_time,
    ];
    $encoded_payload = base64_encode(json_encode($load_payload));

    $headers = [
        "host: $host",
        "x-iconcaptcha-token: $icon_token",
        "x-requested-with: XMLHttpRequest",
        "user-agent: " . saveData(APP_HOST, 'user-agent'),
        "content-type: multipart/form-data; boundary=----$webkit_boundary",
        "accept: */*",
        "origin: https://$host",
        "referer: $url",
    ];

    $request_body = "------$webkit_boundary\r\n" .
        "Content-Disposition: form-data; name=\"payload\"\r\n\r\n" .
        "$encoded_payload\r\n" .
        "------$webkit_boundary--";

    $response = Run($captcha_url, $headers, $request_body, "data", $proxy, 2, $email)['body'];
    $response_data = json_decode(base64_decode($response), true);
    if (!isset($response_data['identifier'])) return null;

    $challenge_id = $response_data['identifier'];
    $challenge = $response_data['challenge'];
    $solve = iconcaptcha($challenge);
    if (!$solve || !isset($solve['x']) || !isset($solve['y'])) return null;

    foreach ([$solve['x']] as $x_position) {
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
            "initTimestamp" => $init_time,
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
                "ic-hp" => "",
            ];
        }
    }
    return null;
}