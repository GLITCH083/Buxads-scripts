#!/usr/bin/env php
<?php
/**
 * 🤖 Bot Launcher - Script Manager
 * 
 * @author GLITCH083
 * @version v2.0
 */


error_reporting(0);
// ============================================
// CONFIGURATION
// ============================================
if (!defined("BASE_DIR")) {
    define("BASE_DIR", __DIR__);
}
if (!defined("SCRIPTS_DIR")) {
    define("SCRIPTS_DIR", BASE_DIR . "/scripts");
}

if (!function_exists('saveData')) {
    require_once BASE_DIR . "/functions/function.php";
}
    require_once BASE_DIR . "/functions/captcha.php";




// ============================================
// GITHUB CONFIGURATION - PRIVATE REPO
// ============================================
if (!defined("GITHUB_TOKEN")) {
    $tokenFile = BASE_DIR . "/github_token.txt";
    if (file_exists($tokenFile)) {
        define("GITHUB_TOKEN", trim(file_get_contents($tokenFile)));
    } else {
        define("GITHUB_TOKEN", "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");
    }
}

if (!defined("GITHUB_USERNAME")) {
    define("GITHUB_USERNAME", "GLITCH083");
}
if (!defined("GITHUB_REPO")) {
    define("GITHUB_REPO", "Buxads-scripts");
}
if (!defined("GITHUB_BRANCH")) {
    define("GITHUB_BRANCH", "main");
}
if (!defined("GITHUB_API")) {
    define("GITHUB_API", "https://api.github.com/repos/" . GITHUB_USERNAME . "/" . GITHUB_REPO . "/contents/");
}
if (!defined("GITHUB_RAW")) {
    define("GITHUB_RAW", "https://raw.githubusercontent.com/" . GITHUB_USERNAME . "/" . GITHUB_REPO . "/" . GITHUB_BRANCH . "/");
}
if (!defined("VERSION_FILE")) {
    define("VERSION_FILE", "version.json");
}
if (!defined("TEMP_DIR")) {
    define("TEMP_DIR", BASE_DIR . "/temp_update");
}
if (!defined("APP_HOST")) {
    define("APP_HOST", "buxads-Bot");
}
if (!defined("api_endpoint")) {
    define("api_endpoint", "http://37.60.224.60:7860/api");
}

date_default_timezone_set("Asia/Karachi");
if (!defined("anticaptcha_key")) define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));


function githubRequest($url, $method = 'GET') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Buxads-Bot/1.0');
    
    if (defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . GITHUB_TOKEN,
            'Accept: application/vnd.github.v3+json'
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return json_decode($response, true);
    }
    return null;
}

/**
 * Fetch file from GitHub raw with authentication
 */
function githubDownloadFile($path) {
    $url = GITHUB_RAW . $path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Buxads-Bot/1.0');
    
    if (defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . GITHUB_TOKEN
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        return $response;
    }
    return false;
}

/**
 * Get list of files in a directory from GitHub
 */
function githubGetDirectoryContents($path) {
    $url = GITHUB_API . $path;
    $data = githubRequest($url);
    
    if ($data && is_array($data)) {
        $files = [];
        foreach ($data as $item) {
            if ($item['type'] === 'file' && pathinfo($item['name'], PATHINFO_EXTENSION) === 'php') {
                $files[] = $item['name'];
            }
        }
        return $files;
    }
    return [];
}

// ============================================
// VERSION INFO FUNCTIONS
// ============================================

/**
 * Get current version from local version.json
 */
function getCurrentVersion() {
    $localFile = BASE_DIR . "/" . VERSION_FILE;
    if (file_exists($localFile)) {
        $content = file_get_contents($localFile);
        $data = json_decode($content, true);
        if ($data && isset($data['version'])) {
            return $data;
        }
    }
    return ['version' => '0.0.0', 'features' => []];
}

/**
 * Fetch version info from GitHub
 */
function fetchLatestVersion() {
    $content = githubDownloadFile(VERSION_FILE);
    if ($content) {
        return json_decode($content, true);
    }
    return null;
}

/**
 * Compare versions
 */
function isNewerVersion($current, $latest) {
    if (!$latest) return false;
    return version_compare($latest['version'], $current['version'], '>');
}

/**
 * Display update information
 */
function displayUpdateInfo($current, $latest) {
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  📦 UPDATE AVAILABLE!" . str_repeat(" ", 48) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  Current Version: " . YELLOW . $current['version'] . str_repeat(" ", 50 - strlen($current['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Latest Version:  " . GREEN . $latest['version'] . str_repeat(" ", 50 - strlen($latest['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  📝 What's New:" . str_repeat(" ", 56) . CYAN . "║\n" . RESET;
    
    if (isset($latest['features']) && is_array($latest['features'])) {
        foreach ($latest['features'] as $feature) {
            $feature = substr($feature, 0, 60);
            echo CYAN . "║  " . WHITE . "• " . $feature . str_repeat(" ", 61 - strlen($feature)) . CYAN . "║\n" . RESET;
        }
    }
    
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

/**
 * Download and apply update
 */
function applyUpdate($latest) {
    echo "\n" . YELLOW . "📥 Downloading update from private repository..." . RESET . "\n";
    
    if (!is_dir(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0777, true);
    }
    
    $filesToDownload = [
        'bot.php',
        'functions/function.php',
        'functions/captcha.php',
        'version.json'
    ];
    
    $scripts = githubGetDirectoryContents('scripts');
    if ($scripts) {
        foreach ($scripts as $script) {
            $filesToDownload[] = 'scripts/' . $script;
        }
    }
    
    $downloaded = 0;
    $total = count($filesToDownload);
    
    foreach ($filesToDownload as $file) {
        echo "  Downloading: " . $file . " ... ";
        
        $content = githubDownloadFile($file);
        if ($content) {
            $tempFile = TEMP_DIR . "/" . basename($file);
            file_put_contents($tempFile, $content);
            $downloaded++;
            echo GREEN . "✓" . RESET . "\n";
        } else {
            echo RED . "✗" . RESET . "\n";
        }
    }
    
    echo "\n" . GREEN . "✓ Downloaded " . $downloaded . "/" . $total . " files" . RESET . "\n";
    echo "\n" . YELLOW . "🔄 Applying update..." . RESET . "\n";
    
    if (file_exists(BASE_DIR . "/bot.php")) {
        copy(BASE_DIR . "/bot.php", BASE_DIR . "/bot.php.bak");
        echo "  ✓ Created backup: bot.php.bak\n";
    }
    
    $files = glob(TEMP_DIR . "/*");
    foreach ($files as $file) {
        $basename = basename($file);
        if (is_dir($file)) continue;
        
        if ($basename == 'function.php' || $basename == 'captcha.php') {
            $dest = BASE_DIR . "/functions/" . $basename;
        } elseif ($basename == 'version.json' || $basename == 'bot.php') {
            $dest = BASE_DIR . "/" . $basename;
        } else {
            $dest = BASE_DIR . "/scripts/" . $basename;
        }
        
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        if (rename($file, $dest)) {
            echo "  ✓ Updated: " . $basename . "\n";
        } else {
            echo "  ✗ Failed to update: " . $basename . "\n";
        }
    }
    
    if (is_dir(TEMP_DIR)) {
        $files = glob(TEMP_DIR . "/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir(TEMP_DIR);
    }
    
    echo "\n" . GREEN . "✓ Update applied successfully!" . RESET . "\n";
}

/**
 * Check for updates and ask user
 */
function checkForUpdates() {
    echo "\n" . CYAN . "🔍 Checking for updates from private repo..." . RESET . "\n";
    
    if (!defined('GITHUB_TOKEN') || empty(GITHUB_TOKEN)) {
        echo RED . "❌ GitHub token not configured!\n" . RESET;
        echo YELLOW . "Please create a Personal Access Token at:\n" . RESET;
        echo YELLOW . "  https://github.com/settings/tokens\n" . RESET;
        echo YELLOW . "Then save it to: " . BASE_DIR . "/github_token.txt\n" . RESET;
        echo WHITE . "Press Enter to continue..." . RESET;
        fgets(STDIN);
        return false;
    }
    
    $current = getCurrentVersion();
    $latest = fetchLatestVersion();
    
    if (!$latest) {
        echo YELLOW . "⚠️  Could not check for updates.\n" . RESET;
        echo WHITE . "Press Enter to continue..." . RESET;
        fgets(STDIN);
        return false;
    }
    
    if (isNewerVersion($current, $latest)) {
        displayUpdateInfo($current, $latest);
        
        echo WHITE . "Do you want to download and install the update? (y/n): " . RESET;
        $choice = trim(fgets(STDIN));
        
        if (strtolower($choice) === 'y') {
            applyUpdate($latest);
            echo "\n" . GREEN . "✅ Update complete! Please restart the bot.\n" . RESET;
            echo WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
            return true;
        } else {
            echo YELLOW . "Update skipped.\n" . RESET;
        }
    } else {
        echo GREEN . "✅ You are using the latest version (" . $current['version'] . ")\n" . RESET;
    }
    
    echo WHITE . "Press Enter to continue..." . RESET;
    fgets(STDIN);
    return false;
}

// ============================================
// SCRIPTS FUNCTIONS
// ============================================

/**
 * Get all PHP scripts in the scripts directory
 */
function getScripts() {
    $scripts = [];
    
    if (!is_dir(SCRIPTS_DIR)) {
        mkdir(SCRIPTS_DIR, 0777, true);
        return $scripts;
    }
    
    $files = glob(SCRIPTS_DIR . "/*.php");
    
    foreach ($files as $file) {
        $basename = basename($file);
        $excluded = ['function.php', 'captcha.php', 'bot.php', 'config.php'];
        if (in_array($basename, $excluded)) continue;
        
        $description = getScriptDescription($basename);
        $version = getScriptVersion($basename);
        
        $scripts[] = [
            'file' => $file,
            'basename' => $basename,
            'description' => $description,
            'version' => $version,
            'color' => getScriptColor($basename)
        ];
    }
    
    usort($scripts, function($a, $b) {
        return strcasecmp($a['basename'], $b['basename']);
    });
    
    return $scripts;
}

/**
 * Get script description based on filename
 */
function getScriptDescription($filename) {
    $descriptions = [
        'adcoins.cc.php' => 'AdCoins.cc Faucet Bot',
        'claimcrypto.in.php' => 'ClaimCrypto.in Faucet Bot',
        'cryptoearns.com.php' => 'CryptoEarns.com Faucet Bot',
        'gamefaucet.fun.php' => 'GameFaucet.fun Faucet Bot',
        'spaceshooter.net.php' => 'SpaceShooter.net Faucet Bot',
        'faucet.php' => 'Faucet Claim Bot',
        'shortlink.php' => 'Shortlink Claim Bot',
        'auto.php' => 'Auto Claim Bot',
        'claim.php' => 'Claim Bot',
        'mail.php' => 'Mail Bot'
    ];
    
    foreach ($descriptions as $pattern => $desc) {
        if (stripos($filename, str_replace('.php', '', $pattern)) !== false) {
            return $desc;
        }
    }
    
    $name = str_replace(['.php', '_', '-'], ' ', $filename);
    $name = ucwords(trim($name));
    return $name;
}

/**
 * Get script version
 */
function getScriptVersion($filename) {
    $file = SCRIPTS_DIR . '/' . $filename;
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (preg_match('/@version\s+([^\n]+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/v(?:ersion)?\s*([0-9.]+)/i', $content, $matches)) {
            return 'v' . $matches[1];
        }
    }
    return 'v1.0';
}

/**
 * Get script color based on name
 */
function getScriptColor($name) {
    if (stripos($name, 'faucet') !== false) return GREEN;
    if (stripos($name, 'shortlink') !== false) return YELLOW;
    if (stripos($name, 'auto') !== false) return CYAN;
    if (stripos($name, 'claim') !== false) return PURPLE;
    if (stripos($name, 'bot') !== false) return BLUE;
    if (stripos($name, 'mail') !== false) return CYAN;
    if (stripos($name, 'adcoins') !== false) return GREEN;
    if (stripos($name, 'cryptoearns') !== false) return GREEN;
    if (stripos($name, 'spaceshooter') !== false) return GREEN;
    if (stripos($name, 'gamefaucet') !== false) return GREEN;
    if (stripos($name, 'claimcrypto') !== false) return GREEN;
    return WHITE;
}

/**
 * Display scripts menu
 */
function displayScriptsMenu($scripts) {
    clearScreen();
    
    $version = getCurrentVersion();
    $balance = get_balance();
    
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  🤖  BOT LAUNCHER v" . $version['version'] . str_repeat(" ", 47 - strlen($version['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Repo: " . GITHUB_USERNAME . str_repeat(" ", 57 - strlen(GITHUB_USERNAME)) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Balance: " . GREEN . $balance . WHITE . str_repeat(" ", 52 - strlen($balance)) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  [1] Run Scripts" . str_repeat(" ", 63) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  [2] Check for Updates" . str_repeat(" ", 58) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  [3] Exit" . str_repeat(" ", 70) . CYAN . "║\n" . RESET;
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

/**
 * Display scripts list
 */
function displayScriptsList($scripts) {
    clearScreen();
    
    $version = getCurrentVersion();
    $balance = get_balance();
    
    echo "\n";
    echo CYAN . "╔════════════════════════════════════════════════════════════════════════════╗\n" . RESET;
    echo CYAN . "║" . WHITE . BOLD . "  🤖  BOT LAUNCHER v" . $version['version'] . str_repeat(" ", 47 - strlen($version['version'])) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Repo: " . GITHUB_USERNAME . str_repeat(" ", 57 - strlen(GITHUB_USERNAME)) . CYAN . "║\n" . RESET;
    echo CYAN . "║" . WHITE . "  Balance: " . GREEN . $balance . WHITE . str_repeat(" ", 52 - strlen($balance)) . CYAN . "║\n" . RESET;
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    
    if (empty($scripts)) {
        echo CYAN . "║" . YELLOW . "  ❌ No scripts found in " . SCRIPTS_DIR . str_repeat(" ", 23) . CYAN . "║\n" . RESET;
    } else {
        $i = 1;
        foreach ($scripts as $script) {
            $num = str_pad($i, 2, " ", STR_PAD_LEFT);
            $name = substr($script['basename'], 0, 30);
            $name = str_pad($name, 30);
            $desc = substr($script['description'], 0, 35);
            $desc = str_pad($desc, 35);
            $version = str_pad($script['version'], 10);
            
            $color = $script['color'];
            
            echo CYAN . "║  " . $color . $num . ". " . $color . $name . RESET . " " . DIM . $desc . RESET . " " . GREY . $version . RESET . "  ║\n" . RESET;
            $i++;
        }
    }
    
    echo CYAN . "╠════════════════════════════════════════════════════════════════════════════╣\n" . RESET;
    echo CYAN . "║" . WHITE . "  [0] Back to Main Menu" . str_repeat(" ", 57) . CYAN . "║\n" . RESET;
    echo CYAN . "╚════════════════════════════════════════════════════════════════════════════╝\n" . RESET;
    echo "\n";
}

/**
 * Run a script
 */
function runScript($script) {
    echo "\n";
    echo GREEN . "▶  Running: " . WHITE . $script['basename'] . "\n" . RESET;
    echo CYAN . str_repeat("═", 70) . "\n" . RESET;
    echo "\n";
    
    $cwd = getcwd();
    chdir(SCRIPTS_DIR);
    
    try {
        require_once $script['file'];
        echo "\n";
        echo CYAN . str_repeat("═", 70) . "\n" . RESET;
        echo GREEN . "✓ Script completed!\n" . RESET;
    } catch (Exception $e) {
        echo "\n";
        echo RED . "✗ Script error: " . $e->getMessage() . "\n" . RESET;
        echo RED . "  File: " . $e->getFile() . ":" . $e->getLine() . "\n" . RESET;
    } catch (Error $e) {
        echo "\n";
        echo RED . "✗ Fatal error: " . $e->getMessage() . "\n" . RESET;
        echo RED . "  File: " . $e->getFile() . ":" . $e->getLine() . "\n" . RESET;
    }
    
    chdir($cwd);
}

/**
 * Get user input with prompt
 */
function getUserInput($prompt, $default = null) {
    echo WHITE . $prompt . RESET;
    if ($default !== null) {
        echo " [" . CYAN . $default . RESET . "]";
    }
    echo ": ";
    $input = trim(fgets(STDIN));
    return empty($input) ? $default : $input;
}

/**
 * Clear screen
 */
function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        passthru('cls');
    } else {
        passthru('clear');
    }
}

// ============================================
// SUPPRESS WARNINGS WHEN RUNNING SCRIPTS
// ============================================
$originalErrorReporting = error_reporting();
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// ============================================
// MAIN PROGRAM
// ============================================

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line!\n");
}

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() {
        echo "\n" . YELLOW . "Interrupted by user. Exiting...\n" . RESET;
        exit(0);
    });
}

// Check if scripts directory exists
if (!is_dir(SCRIPTS_DIR)) {
    mkdir(SCRIPTS_DIR, 0777, true);
}

// Main loop
while (true) {
    $scripts = getScripts();
    displayScriptsMenu($scripts);
    
    $choice = getUserInput("Select option (1-3)", "1");
    
    if ($choice === '3') {
        echo GREEN . "Goodbye!\n" . RESET;
        exit(0);
    }
    
    if ($choice === '2') {
        checkForUpdates();
        continue;
    }
    
    if ($choice === '1') {
        // Show scripts list
        while (true) {
            displayScriptsList($scripts);
            
            if (empty($scripts)) {
                echo YELLOW . "📁 No scripts found in: " . SCRIPTS_DIR . "\n" . RESET;
                echo WHITE . "Press Enter to continue..." . RESET;
                fgets(STDIN);
                break;
            }
            
            $scriptChoice = getUserInput("Select script (1-" . count($scripts) . " or 0 to go back)", "0");
            
            if ($scriptChoice === '0' || $scriptChoice === '') {
                break;
            }
            
            if (!is_numeric($scriptChoice) || $scriptChoice < 1 || $scriptChoice > count($scripts)) {
                echo RED . "❌ Invalid selection! Press Enter to continue...\n" . RESET;
                fgets(STDIN);
                continue;
            }
            
            $selectedScript = $scripts[$scriptChoice - 1];
            
            // Show script details and confirm
            echo "\n";
            echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
            echo CYAN . "║" . WHITE . BOLD . "  📄 SCRIPT DETAILS" . str_repeat(" ", 50) . CYAN . "║\n" . RESET;
            echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
            echo CYAN . "║" . WHITE . "  Name:        " . $selectedScript['color'] . $selectedScript['basename'] . RESET . str_repeat(" ", 27 - strlen($selectedScript['basename'])) . CYAN . "║\n" . RESET;
            echo CYAN . "║" . WHITE . "  Description: " . $selectedScript['description'] . str_repeat(" ", 27 - strlen(substr($selectedScript['description'], 0, 27))) . CYAN . "║\n" . RESET;
            echo CYAN . "║" . WHITE . "  Version:     " . $selectedScript['version'] . str_repeat(" ", 27 - strlen($selectedScript['version'])) . CYAN . "║\n" . RESET;
            echo CYAN . "╚═══════════════════════════════════════════════════════════════╝\n" . RESET;
            echo "\n";
            
            $confirm = getUserInput("Run this script? (y/n)", "y");
            if (strtolower($confirm) !== 'y') {
                continue;
            }
            
            runScript($selectedScript);
            
            echo "\n" . WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
        }
    }
}

// Restore error reporting
error_reporting($originalErrorReporting);
?>
