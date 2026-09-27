# Buxads Official Bot (Cleaned)

A powerful Faucet Scraper Tool built for automated faucet claiming and website management.

## Version 1.2.0 (Cleaned)

### Changes in this clean version
- Removed large training datasets (`shake_dataset.zip`, captcha images)
- Removed personal accounts, configs, sessions, and information folders
- Removed hardcoded GitHub token (now uses `github_token.txt` if present)
- Cleaner package, ready for fresh setup

### Features
- BuxAds support
- Individual script execution
- Puppeteer Real Browser support
- Chromium support
- API Key integration
- Termux-X11 compatible

---

### Installation

1. Navigate to the installation directory:

```bash
cd installation
```

2. Run the installer:

```bash
bash installation.sh
```

---

### Termux-X11 Setup

Download the correct Termux-X11 application for your device:

https://github.com/termux/termux-x11/releases/tag/nightly

Install the application and make sure it launches correctly.

---

### Verify Installation

Before running the bot, verify that the following dependencies are installed:

```bash
php -v
node -v
```

Also verify:
- Puppeteer Real Browser
- Chromium

---

### Running the Bot

**Session 1 (Termux-X11)**

```bash
termux-x11 :1 &
export DISPLAY=:1
php cf.php
```

Keep this session running.

**Session 2 (Bot)**

```bash
php bot.php
```

---

### Scripts Folder

In addition to `bot.php`, individual scripts can be executed directly from the `scripts` folder.

This allows you to run specific website scripts without using the main bot.

---

### API Key Requirement

This project requires an API Key from:

**@buxads_bot**

Make sure your API Key is configured before running the bot.

---

### Creator

Telegram: @glitch_394

---

### Disclaimer

This project is provided for educational and research purposes only. Users are responsible for complying with the terms and conditions of any website they interact with.
