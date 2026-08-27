<?php

date_default_timezone_set("Asia/Karachi");

define("APP_HOST", "buxads-Bot");
define("BASE_DIR", __DIR__);

// ================== FIX 1: ADD API_URL ==================
define("API_URL", "https://coinfree.app/api.php");
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

// ================== FIX 2: Fallback countdown() ==================
if (!function_exists('countdown')) {
    function countdown($seconds, $message = '') {
        for ($i = $seconds; $i > 0; $i--) {
            echo "\r⏳ Waiting {$i}s ... " . str_repeat(' ', 10);
            sleep(1);
        }
        echo "\r" . str_repeat(' ', 30) . "\r";
    }
}
// ================================================================

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

function generateDeviceId() {
    return bin2hex(random_bytes(32)); // 64 hex chars
}


function getStatsFile() {
    $infoDir = BASE_DIR . "/information";
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
    $infoDir = BASE_DIR . "/information";
    if (!is_dir($infoDir)) mkdir($infoDir, 0755, true);
    return $infoDir . "/spaceshooter.net.txt";
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
    $accounts = loadAccounts();
    if (empty($accounts)) {
        echo YELLOW . "No accounts found.\n" . RESET;
        return;
    }
    echo "\n" . CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  📋  ACCOUNT LIST" . str_repeat(" ", 34) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    $i = 1;
    foreach ($accounts as $acc) {
        $proxy = !empty($acc['proxy']) ? GREEN . "✓" . RESET : RED . "✗" . RESET;
        $stats = loadStats($acc['initData']);
        $line = sprintf("║  %2d. %-15s  Claims: %3d/%-3d  Proxy: %s  ║",
            $i,
            substr($acc['name'], 0, 15),
            $stats['claims'],
            $stats['totalclaims'],
            $proxy
        );
        echo WHITE . $line . "\n" . RESET;
        $i++;
    }
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
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
        "referer: https://coinfree.app/",
        "accept-language: en-US,en;q=0.9",
        "origin: https://coinfree.app",
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

function claimFaucet($initData, $deviceId, $mode, $proxy = null) {
    $headers = getApiHeaders();

    // First, call get_faucet_spin_reward (as per original)
    $spin = getFaucetSpin($initData, $deviceId, $mode, $proxy);

    // Solve captcha
    $captchaData = captcha("https://coinfree.app", "0x4AAAAAAB6mAUIH75NUE5fq", "turnstile", "faucet_claim");
    $token = $captchaData['token'];
    if (!$token) {
        return ['status' => 'error', 'message' => 'Failed to get CAPTCHA token'];
    }

    $payload = json_encode([
        "action"       => "claim_faucet",
        "initData"     => $initData,
        "deviceId"     => $deviceId,
        "captchaToken" => $token,
        "mode"         => $mode
    ]);

    $r = Run(API_URL, $headers, $payload, null, $proxy,2,$name);
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
        'box'      => '📦', 'plinko' => '🎯', 'target'  => '🎯',
        'drop'     => '💧', 'coinflip'=> '🪙', 'claw'    => '🦀'
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

            if ($cooldown > 0) {
                animation("⏳ $mode cooldown: {$cooldown}s, skipping.");
                continue;
            }

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

            sleep(2); // small delay between games
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
            sleep(60);
        }
    }
}

// ---------- Menu ----------

function menu() {
    clear();
    echo "\n";
    echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . "  🚀  spaceshooter.net AUTO BOT" . str_repeat(" ", 30) . CYAN . "║\n" . RESET;
    echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  1. Run Account (infinite loop)" . str_repeat(" ", 33) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  2. Add Account" . str_repeat(" ", 45) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  3. Delete Account" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  4. View Accounts" . str_repeat(" ", 43) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  5. Exit" . str_repeat(" ", 52) . CYAN . "║\n" . RESET;
    echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
    echo WHITE . "Choose an option: " . RESET;
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