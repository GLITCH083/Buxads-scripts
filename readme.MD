Buxads Official Bot

A powerful Faucet Scraper Tool built for automated faucet claiming and website management.

Features

- BuxAds - support

- Individual script execution
- Puppeteer Real Browser support
- Chromium support
- API Key integration
- Termux-X11 compatible

---

Installation

1. Navigate to the installation directory:

cd installation

2. Run the installer:

bash installation.sh

---

Termux-X11 Setup

Download the correct Termux-X11 application for your device:

https://github.com/termux/termux-x11/releases/tag/nightly

Install the application and make sure it launches correctly.

---

Verify Installation

Before running the bot, verify that the following dependencies are installed:

php -v
node -v

Also verify:

- Puppeteer Real Browser
- Chromium

---


Running the Bot

Session 1 (Termux-X11)

Open the first Termux session and run:

termux-x11 :1 &
export DISPLAY=:1
php cf.php

Keep this session running.

Session 2 (Bot)

Open a second Termux session and run:

php bot.php


Scripts Folder

In addition to "bot.php", individual scripts can be executed directly from the "scripts" folder.

This allows you to run specific website scripts without using the main bot.

---

API Key Requirement

This project requires an API Key from:

@buxads_bot

Make sure your API Key is configured before running the bot.

---

Creator

Telegram: @glitch_394

---

Disclaimer

This project is provided for educational and research purposes only. Users are responsible for complying with the terms and conditions of any website they interact with.