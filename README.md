# Buxads Scripts

**Version 2.0.2**

Public faucet / PTC automation bot package.

## What's new
- Crystal claim boxes (captcha, reward, balance)
- Single account keeps claiming (Ctrl+C to stop)
- Run All Accounts (Smart)
- Captcha auto-retry when solver is busy
- Earnsolana faucet + PTC
- BTCAdSpace Surf + Videos
- 99faucet stays on one coin until error
- PHP 8.5 ready

## Setup
1. Clone or extract this repo
2. Put your captcha API key in `configs/buxads-Bot-config/anticaptcha-apikey`
3. Run:
```bash
php bot.php
```
4. Add accounts inside each script menu

## Update
In the bot menu choose **Check for Updates** (needs a GitHub token with access to this repo if private).

## Notes
- Do not share personal accounts or API keys
- Use proxies responsibly
