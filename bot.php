#!/usr/bin/env php
<?php
/**
 * 🤖 Bot Launcher - Script Manager
 * 
 * @author GLITCH083
 * @version v2.0
 */

// Suppress all errors
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// CONFIGURATION
// ============================================
define("BASE_DIR", __DIR__);
define("SCRIPTS_DIR", BASE_DIR . "/scripts");

// Load required files
require_once BASE_DIR . "/functions/function.php";
require_once BASE_DIR . "/functions/captcha.php";

// ============================================
// GITHUB CONFIGURATION
// ============================================
$tokenFile = BASE_DIR . "/github_token.txt";
if (file_exists($tokenFile)) {
    $githubToken = trim(file_get_contents($tokenFile));
} else {
    $githubToken = "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";
}

define("GITHUB_TOKEN", $githubToken);
define("GITHUB_USERNAME", "GLITCH083");
define("GITHUB_REPO", "Buxads-scripts");
define("GITHUB_BRANCH", "main");
define("GITHUB_API_URL", "https://api.github.com/repos/" . GITHUB_USERNAME . "/" . GITHUB_REPO);
define("VERSION_FILE", "version.json");
define("TEMP_DIR", BASE_DIR . "/temp_update");
define("APP_HOST", "buxads-Bot");
define("api_endpoint", "http://37.60.224.60:7860/api");

date_default_timezone_set("Asia/Karachi");

if (!defined("anticaptcha_key")) {
    define("anticaptcha_key", saveData(APP_HOST, "anticaptcha-apikey"));
}

// ============================================
// GITHUB API FUNCTIONS
// ============================================
function githubApiRequest($endpoint) {
    $url = GITHUB_API_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Buxads-Bot/2.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: token ' . GITHUB_TOKEN,
            'Accept: application/vnd.github.v3+json',
            'User-Agent: Buxads-Bot/2.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        return json_decode($response, true);
    }
    
    return null;
}

function githubDownloadFile($path) {
    // Try GitHub API first (works for private repos)
    $data = githubApiRequest("/contents/" . $path);
    
    if ($data && isset($data['content'])) {
        return base64_decode($data['content']);
    }
    
    // Fallback to raw URL
    $rawUrl = "https://raw.githubusercontent.com/" . GITHUB_USERNAME . "/" . GITHUB_REPO . "/" . GITHUB_BRANCH . "/" . $path;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $rawUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Buxads-Bot/2.0',
        CURLOPT_HTTPHEADER => [
            'Authorization: token ' . GITHUB_TOKEN,
            'User-Agent: Buxads-Bot/2.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        return $response;
    }
    
    return false;
}

function githubListDirectory($path) {
    $data = githubApiRequest("/contents/" . $path);
    
    if ($data && is_array($data)) {
        $files = [];
        foreach ($data as $item) {
            if (isset($item['type']) && $item['type'] === 'file' && 
                isset($item['name']) && pathinfo($item['name'], PATHINFO_EXTENSION) === 'php') {
                $files[] = $item['name'];
            }
        }
        return $files;
    }
    
    return [];
}

// ============================================
// VERSION FUNCTIONS
// ============================================
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

function fetchLatestVersion() {
    $content = githubDownloadFile(VERSION_FILE);
    
    if ($content) {
        $data = json_decode($content, true);
        if ($data && isset($data['version'])) {
            return $data;
        }
    }
    
    return null;
}

function isNewerVersion($current, $latest) {
    if (!$latest || !isset($latest['version']) || !isset($current['version'])) {
        return false;
    }
    
    return version_compare($latest['version'], $current['version'], '>');
}

// ============================================
// UPDATE FUNCTIONS
// ============================================
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

function applyUpdate($latest) {
    echo "\n" . YELLOW . "📥 Downloading update..." . RESET . "\n";
    
    if (!is_dir(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0777, true);
    }
    
    $filesToDownload = [
        'bot.php',
        'functions/function.php',
        'functions/captcha.php',
        'version.json'
    ];
    
    // Get scripts list
    $scripts = githubListDirectory('scripts');
    foreach ($scripts as $script) {
        $filesToDownload[] = 'scripts/' . $script;
    }
    
    $downloaded = 0;
    $total = count($filesToDownload);
    
    foreach ($filesToDownload as $file) {
        echo "  Downloading: " . $file . " ... ";
        
        $content = githubDownloadFile($file);
        if ($content !== false) {
            $tempFile = TEMP_DIR . "/" . basename($file);
            file_put_contents($tempFile, $content);
            $downloaded++;
            echo GREEN . "✓" . RESET . "\n";
        } else {
            echo RED . "✗" . RESET . "\n";
        }
    }
    
    echo "\n" . GREEN . "✓ Downloaded " . $downloaded . "/" . $total . " files" . RESET . "\n";
    
    if ($downloaded == 0) {
        echo RED . "✗ No files downloaded. Update failed." . RESET . "\n";
        return false;
    }
    
    echo "\n" . YELLOW . "🔄 Applying update..." . RESET . "\n";
    
    // Backup current bot.php
    if (file_exists(BASE_DIR . "/bot.php")) {
        copy(BASE_DIR . "/bot.php", BASE_DIR . "/bot.php.bak");
        echo "  ✓ Created backup: bot.php.bak\n";
    }
    
    // Move files to correct locations
    $files = glob(TEMP_DIR . "/*");
    foreach ($files as $file) {
        if (is_dir($file)) continue;
        
        $basename = basename($file);
        
        // Determine destination
        if ($basename == 'function.php' || $basename == 'captcha.php') {
            $dest = BASE_DIR . "/functions/" . $basename;
        } elseif ($basename == 'version.json' || $basename == 'bot.php') {
            $dest = BASE_DIR . "/" . $basename;
        } else {
            $dest = BASE_DIR . "/scripts/" . $basename;
        }
        
        // Create directory if needed
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Move file
        if (rename($file, $dest)) {
            echo "  ✓ Updated: " . $basename . "\n";
        }
    }
    
    // Clean up temp directory
    $files = glob(TEMP_DIR . "/*");
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    @rmdir(TEMP_DIR);
    
    echo "\n" . GREEN . "✅ Update applied successfully!" . RESET . "\n";
    return true;
}

function checkForUpdates() {
    echo "\n" . CYAN . "🔍 Checking for updates..." . RESET . "\n";
    
    $current = getCurrentVersion();
    echo GREY . "  Current version: " . $current['version'] . RESET . "\n";
    
    $latest = fetchLatestVersion();
    
    if (!$latest) {
        echo RED . "❌ Could not fetch version information from GitHub.\n" . RESET;
        echo YELLOW . "  Please check your internet connection and token.\n" . RESET;
        echo WHITE . "Press Enter to continue..." . RESET;
        fgets(STDIN);
        return false;
    }
    
    echo GREY . "  Latest version: " . $latest['version'] . RESET . "\n";
    
    if (isNewerVersion($current, $latest)) {
        displayUpdateInfo($current, $latest);
        
        echo WHITE . "Do you want to download and install the update? (y/n): " . RESET;
        $choice = strtolower(trim(fgets(STDIN)));
        
        if ($choice === 'y' || $choice === 'yes') {
            if (applyUpdate($latest)) {
                echo "\n" . GREEN . "✅ Update complete! Please restart the bot.\n" . RESET;
            }
        } else {
            echo YELLOW . "Update skipped.\n" . RESET;
        }
    } else {
        echo GREEN . "✅ You are using the latest version (" . $current['version'] . ")\n" . RESET;
    }
    
    echo WHITE . "Press Enter to continue..." . RESET;
    fgets(STDIN);
    return true;
}

// ============================================
// SCRIPT MANAGEMENT FUNCTIONS
// ============================================
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
        
        $scripts[] = [
            'file' => $file,
            'basename' => $basename,
            'description' => getScriptDescription($basename),
            'version' => getScriptVersion($basename),
            'color' => getScriptColor($basename)
        ];
    }
    
    usort($scripts, function($a, $b) {
        return strcasecmp($a['basename'], $b['basename']);
    });
    
    return $scripts;
}

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
    return ucwords(trim($name));
}

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

// ============================================
// DISPLAY FUNCTIONS
// ============================================
function displayMainMenu() {
    clearScreen();
    
    $version = getCurrentVersion();
    $balance = function_exists('get_balance') ? get_balance() : 'N/A';
    
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

function displayScriptsList($scripts) {
    clearScreen();
    
    $version = getCurrentVersion();
    $balance = function_exists('get_balance') ? get_balance() : 'N/A';
    
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
            $name = substr($script['basename'], 0, 25);
            $name = str_pad($name, 25);
            $desc = substr($script['description'], 0, 30);
            $desc = str_pad($desc, 30);
            $version = str_pad($script['version'], 8);
            
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

function runScript($script) {
    echo "\n";
    echo GREEN . "▶  Running: " . WHITE . $script['basename'] . "\n" . RESET;
    echo CYAN . str_repeat("═", 70) . "\n" . RESET;
    echo "\n";
    
    $cwd = getcwd();
    chdir(SCRIPTS_DIR);
    
    if (file_exists($script['file'])) {
        include $script['file'];
    } else {
        echo RED . "✗ Script file not found: " . $script['file'] . "\n" . RESET;
    }
    
    echo "\n";
    echo CYAN . str_repeat("═", 70) . "\n" . RESET;
    echo GREEN . "✓ Script completed!\n" . RESET;
    
    chdir($cwd);
}

function getUserInput($prompt, $default = null) {
    echo WHITE . $prompt . RESET;
    if ($default !== null) {
        echo " [" . CYAN . $default . RESET . "]";
    }
    echo ": ";
    
    $input = trim(fgets(STDIN));
    return empty($input) ? $default : $input;
}

function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

// ============================================
// MAIN PROGRAM
// ============================================
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line!\n");
}

// Handle Ctrl+C
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
    displayMainMenu();
    
    $choice = getUserInput("Select option (1-3)", "1");
    
    switch ($choice) {
        case '3':
            echo GREEN . "Goodbye!\n" . RESET;
            exit(0);
            
        case '2':
            checkForUpdates();
            break;
            
        case '1':
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
                    echo RED . "❌ Invalid selection!\n" . RESET;
                    echo WHITE . "Press Enter to continue..." . RESET;
                    fgets(STDIN);
                    continue;
                }
                
                $selectedScript = $scripts[$scriptChoice - 1];
                
                echo "\n";
                echo CYAN . "╔═══════════════════════════════════════════════════════════════╗\n" . RESET;
                echo CYAN . "║" . WHITE . BOLD . "  📄 SCRIPT DETAILS" . str_repeat(" ", 50) . CYAN . "║\n" . RESET;
                echo CYAN . "╠═══════════════════════════════════════════════════════════════╣\n" . RESET;
                echo CYAN . "║" . WHITE . "  Name:        " . $selectedScript['color'] . $selectedScript['basename'] . RESET . str_repeat(" ", 27 - strlen($selectedScript['basename'])) . CYAN . "║\n" . RESET;
                echo CYAN . "║" . WHITE . "  Description: " . substr($selectedScript['description'], 0, 27) . str_repeat(" ", 27 - strlen(substr($selectedScript['description'], 0, 27))) . CYAN . "║\n" . RESET;
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
            break;
            
        default:
            echo RED . "❌ Invalid option!\n" . RESET;
            echo WHITE . "Press Enter to continue..." . RESET;
            fgets(STDIN);
    }
}
