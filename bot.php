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
if (!defined("BASE_DIR")) {
    define("BASE_DIR", __DIR__);
}
if (!defined("SCRIPTS_DIR")) {
    define("SCRIPTS_DIR", BASE_DIR . "/scripts");
}

// Try to load functions with error suppression
@require_once BASE_DIR . "/functions/function.php";
@require_once BASE_DIR . "/functions/captcha.php";

// ============================================
// GITHUB CONFIGURATION - PRIVATE REPO
// ============================================
if (!defined("GITHUB_TOKEN")) {
    $tokenFile = BASE_DIR . "/github_token.txt";
    if (file_exists($tokenFile)) {
        define("GITHUB_TOKEN", trim(@file_get_contents($tokenFile)));
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
if (!defined("anticaptcha_key")) define("anticaptcha_key", @saveData(APP_HOST, "anticaptcha-apikey"));

// Color definitions with error suppression
if (!defined("GREEN")) {
    define("RED", "\033[1;31;40m");
    define("GREEN", "\033[1;32;40m");
    define("YELLOW", "\033[1;33;40m");
    define("BLUE", "\033[1;34;40m");
    define("PURPLE", "\033[1;35;40m");
    define("CYAN", "\033[1;36;40m");
    define("GREY", "\033[1;30;40m");
    define("WHITE", "\033[1;37m");
    define("RESET", "\033[0m");
    define("DIM", "\033[2m");
    define("BOLD", "\033[1m");
}

function githubRequest($url, $method = 'GET') {
    try {
        $ch = @curl_init();
        if (!$ch) return null;
        
        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($ch, CURLOPT_USERAGENT, 'Buxads-Bot/1.0');
        
        if (defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN)) {
            @curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: token ' . GITHUB_TOKEN,
                'Accept: application/vnd.github.v3+json'
            ]);
        }
        
        $response = @curl_exec($ch);
        $httpCode = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            return @json_decode($response, true);
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

function githubDownloadFile($path) {
    try {
        $url = GITHUB_RAW . $path;
        $ch = @curl_init();
        if (!$ch) return false;
        
        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        @curl_setopt($ch, CURLOPT_USERAGENT, 'Buxads-Bot/1.0');
        
        if (defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN)) {
            @curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: token ' . GITHUB_TOKEN
            ]);
        }
        
        $response = @curl_exec($ch);
        $httpCode = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            return $response;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function githubGetDirectoryContents($path) {
    try {
        $url = GITHUB_API . $path;
        $data = githubRequest($url);
        
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
    } catch (Exception $e) {
        return [];
    }
}

function getCurrentVersion() {
    try {
        $localFile = BASE_DIR . "/" . VERSION_FILE;
        if (file_exists($localFile)) {
            $content = @file_get_contents($localFile);
            if ($content) {
                $data = @json_decode($content, true);
                if ($data && isset($data['version'])) {
                    return $data;
                }
            }
        }
        return ['version' => '0.0.0', 'features' => []];
    } catch (Exception $e) {
        return ['version' => '0.0.0', 'features' => []];
    }
}

function fetchLatestVersion() {
    try {
        $content = githubDownloadFile(VERSION_FILE);
        if ($content) {
            return @json_decode($content, true);
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

function isNewerVersion($current, $latest) {
    try {
        if (!$latest) return false;
        return version_compare($latest['version'], $current['version'], '>');
    } catch (Exception $e) {
        return false;
    }
}

function displayUpdateInfo($current, $latest) {
    try {
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
    } catch (Exception $e) {
        // Silent fail
    }
}

function applyUpdate($latest) {
    try {
        echo "\n" . YELLOW . "📥 Downloading update from private repository..." . RESET . "\n";
        
        if (!is_dir(TEMP_DIR)) {
            @mkdir(TEMP_DIR, 0777, true);
        }
        
        $filesToDownload = [
            'bot.php',
            'functions/function.php',
            'functions/captcha.php',
            'version.json'
        ];
        
        $scripts = githubGetDirectoryContents('scripts');
        if ($scripts && is_array($scripts)) {
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
                @file_put_contents($tempFile, $content);
                $downloaded++;
                echo GREEN . "✓" . RESET . "\n";
            } else {
                echo RED . "✗" . RESET . "\n";
            }
        }
        
        echo "\n" . GREEN . "✓ Downloaded " . $downloaded . "/" . $total . " files" . RESET . "\n";
        echo "\n" . YELLOW . "🔄 Applying update..." . RESET . "\n";
        
        if (file_exists(BASE_DIR . "/bot.php")) {
            @copy(BASE_DIR . "/bot.php", BASE_DIR . "/bot.php.bak");
            echo "  ✓ Created backup: bot.php.bak\n";
        }
        
        $files = @glob(TEMP_DIR . "/*");
        if ($files && is_array($files)) {
            foreach ($files as $file) {
                if (is_dir($file)) continue;
                
                $basename = basename($file);
                
                if ($basename == 'function.php' || $basename == 'captcha.php') {
                    $dest = BASE_DIR . "/functions/" . $basename;
                } elseif ($basename == 'version.json' || $basename == 'bot.php') {
                    $dest = BASE_DIR . "/" . $basename;
                } else {
                    $dest = BASE_DIR . "/scripts/" . $basename;
                }
                
                $dir = dirname($dest);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                
                if (@rename($file, $dest)) {
                    echo "  ✓ Updated: " . $basename . "\n";
                }
            }
        }
        
        if (is_dir(TEMP_DIR)) {
            $files = @glob(TEMP_DIR . "/*");
            if ($files && is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir(TEMP_DIR);
        }
        
        echo "\n" . GREEN . "✓ Update applied successfully!" . RESET . "\n";
    } catch (Exception $e) {
        echo RED . "✗ Update failed: " . $e->getMessage() . "\n" . RESET;
    }
}

function checkForUpdates() {
    try {
        echo "\n" . CYAN . "🔍 Checking for updates from private repo..." . RESET . "\n";
        
        if (!defined('GITHUB_TOKEN') || empty(GITHUB_TOKEN)) {
            echo RED . "❌ GitHub token not configured!\n" . RESET;
            echo YELLOW . "Please create a Personal Access Token at:\n" . RESET;
            echo YELLOW . "  https://github.com/settings/tokens\n" . RESET;
            echo YELLOW . "Then save it to: " . BASE_DIR . "/github_token.txt\n" . RESET;
            echo WHITE . "Press Enter to continue..." . RESET;
            @fgets(STDIN);
            return false;
        }
        
        $current = getCurrentVersion();
        $latest = fetchLatestVersion();
        
        if (!$latest) {
            echo YELLOW . "⚠️  Could not check for updates.\n" . RESET;
            echo WHITE . "Press Enter to continue..." . RESET;
            @fgets(STDIN);
            return false;
        }
        
        if (isNewerVersion($current, $latest)) {
            displayUpdateInfo($current, $latest);
            
            echo WHITE . "Do you want to download and install the update? (y/n): " . RESET;
            $choice = trim(@fgets(STDIN));
            
            if (strtolower($choice) === 'y') {
                applyUpdate($latest);
                echo "\n" . GREEN . "✅ Update complete! Please restart the bot.\n" . RESET;
                echo WHITE . "Press Enter to continue..." . RESET;
                @fgets(STDIN);
                return true;
            } else {
                echo YELLOW . "Update skipped.\n" . RESET;
            }
        } else {
            echo GREEN . "✅ You are using the latest version (" . $current['version'] . ")\n" . RESET;
        }
        
        echo WHITE . "Press Enter to continue..." . RESET;
        @fgets(STDIN);
        return false;
    } catch (Exception $e) {
        echo RED . "❌ Error checking updates: " . $e->getMessage() . "\n" . RESET;
        echo WHITE . "Press Enter to continue..." . RESET;
        @fgets(STDIN);
        return false;
    }
}

function getScripts() {
    try {
        $scripts = [];
        
        if (!is_dir(SCRIPTS_DIR)) {
            @mkdir(SCRIPTS_DIR, 0777, true);
            return $scripts;
        }
        
        $files = @glob(SCRIPTS_DIR . "/*.php");
        if (!$files || !is_array($files)) {
            return $scripts;
        }
        
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
    } catch (Exception $e) {
        return [];
    }
}

function getScriptDescription($filename) {
    try {
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
    } catch (Exception $e) {
        return $filename;
    }
}

function getScriptVersion($filename) {
    try {
        $file = SCRIPTS_DIR . '/' . $filename;
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                if (preg_match('/@version\s+([^\n]+)/i', $content, $matches)) {
                    return trim($matches[1]);
                }
                if (preg_match('/v(?:ersion)?\s*([0-9.]+)/i', $content, $matches)) {
                    return 'v' . $matches[1];
                }
            }
        }
        return 'v1.0';
    } catch (Exception $e) {
        return 'v1.0';
    }
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

function displayScriptsMenu($scripts) {
    try {
        clearScreen();
        
        $version = getCurrentVersion();
        $balance = @get_balance();
        if (!$balance) $balance = 'N/A';
        
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
    } catch (Exception $e) {
        // Silent fail
    }
}

function displayScriptsList($scripts) {
    try {
        clearScreen();
        
        $version = getCurrentVersion();
        $balance = @get_balance();
        if (!$balance) $balance = 'N/A';
        
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
    } catch (Exception $e) {
        // Silent fail
    }
}

function runScript($script) {
    try {
        echo "\n";
        echo GREEN . "▶  Running: " . WHITE . $script['basename'] . "\n" . RESET;
        echo CYAN . str_repeat("═", 70) . "\n" . RESET;
        echo "\n";
        
        $cwd = getcwd();
        @chdir(SCRIPTS_DIR);
        
        if (file_exists($script['file'])) {
            // Include the script with suppressed errors
            @include_once $script['file'];
        } else {
            echo RED . "✗ Script file not found: " . $script['file'] . "\n" . RESET;
        }
        
        echo "\n";
        echo CYAN . str_repeat("═", 70) . "\n" . RESET;
        echo GREEN . "✓ Script completed!\n" . RESET;
        
        @chdir($cwd);
    } catch (Exception $e) {
        echo "\n";
        echo RED . "✗ Script error: " . $e->getMessage() . "\n" . RESET;
    }
}

function getUserInput($prompt, $default = null) {
    try {
        echo WHITE . $prompt . RESET;
        if ($default !== null) {
            echo " [" . CYAN . $default . RESET . "]";
        }
        echo ": ";
        $input = trim(@fgets(STDIN));
        return empty($input) ? $default : $input;
    } catch (Exception $e) {
        return $default;
    }
}

function clearScreen() {
    try {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @passthru('cls');
        } else {
            @passthru('clear');
        }
    } catch (Exception $e) {
        // Silent fail
    }
}

// ============================================
// MAIN PROGRAM
// ============================================

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line!\n");
}

// Handle Ctrl+C without pcntl
if (function_exists('pcntl_signal')) {
    @pcntl_signal(SIGINT, function() {
        echo "\n" . YELLOW . "Interrupted by user. Exiting...\n" . RESET;
        exit(0);
    });
}

// Check if scripts directory exists
if (!is_dir(SCRIPTS_DIR)) {
    @mkdir(SCRIPTS_DIR, 0777, true);
}

// Main loop with error suppression
while (true) {
    try {
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
            while (true) {
                displayScriptsList($scripts);
                
                if (empty($scripts)) {
                    echo YELLOW . "📁 No scripts found in: " . SCRIPTS_DIR . "\n" . RESET;
                    echo WHITE . "Press Enter to continue..." . RESET;
                    @fgets(STDIN);
                    break;
                }
                
                $scriptChoice = getUserInput("Select script (1-" . count($scripts) . " or 0 to go back)", "0");
                
                if ($scriptChoice === '0' || $scriptChoice === '') {
                    break;
                }
                
                if (!is_numeric($scriptChoice) || $scriptChoice < 1 || $scriptChoice > count($scripts)) {
                    echo RED . "❌ Invalid selection! Press Enter to continue...\n" . RESET;
                    @fgets(STDIN);
                    continue;
                }
                
                $selectedScript = $scripts[$scriptChoice - 1];
                
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
                @fgets(STDIN);
            }
        }
    } catch (Exception $e) {
        // Silent fail - continue loop
        echo RED . "❌ An error occurred. Press Enter to continue...\n" . RESET;
        @fgets(STDIN);
        continue;
    }
}

exit;
