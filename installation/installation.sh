#!/bin/bash

# One-click Termux Installation Script

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

START_DIR=$(pwd)

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}       Termux Development Environment Installation${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}📦 Packages to be installed:${NC}"
echo "  • x11-repo"
echo "  • termux-x11-nightly"
echo "  • termux-x11"
echo "  • php"
echo "  • nodejs"
echo "  • chromium"
echo ""

echo -e "${YELLOW}📚 NPM Packages to be installed:${NC}"
echo "  • express"
echo "  • puppeteer-real-browser"
echo "  • axios"
echo ""

echo -e "${YELLOW}📍 Installation Location:${NC}"
echo "  • Home directory: ~/"
echo "  • Starting directory: $START_DIR"
echo ""

echo -e "${BLUE}Starting installation in 3 seconds...${NC}"
sleep 1
echo -e "${BLUE}2...${NC}"
sleep 1
echo -e "${BLUE}1...${NC}"
sleep 1
echo ""

echo -e "${GREEN}[1/9] Updating package list...${NC}"
pkg update -y && pkg upgrade -y

echo -e "${GREEN}[2/9] Installing x11-repo...${NC}"
pkg install x11-repo -y

echo -e "${GREEN}[3/9] Installing termux-x11-nightly...${NC}"
pkg install termux-x11-nightly -y

echo -e "${GREEN}[4/9] Installing termux-x11...${NC}"
pkg install termux-x11 -y

echo -e "${GREEN}[5/9] Installing PHP...${NC}"
pkg install php -y

echo -e "${GREEN}[6/9] Installing Node.js...${NC}"
pkg install nodejs -y

echo -e "${GREEN}[7/9] Installing Chromium...${NC}"
pkg install chromium -y

echo -e "${GREEN}[8/9] Installing NPM packages in home directory...${NC}"
cd ~

echo -e "${BLUE}  → Installing express...${NC}"
npm install express

echo -e "${BLUE}  → Installing puppeteer-real-browser...${NC}"
npm install puppeteer-real-browser

echo -e "${BLUE}  → Installing axios...${NC}"
npm install axios

echo -e "${GREEN}[9/9] Returning to starting directory...${NC}"
cd "$START_DIR"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Installation Complete!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}📊 Installed Versions:${NC}"
echo -e "  PHP: $(php -v 2>/dev/null | head -n1 || echo 'Not found')"
echo -e "  Node.js: $(node -v 2>/dev/null || echo 'Not found')"
echo -e "  NPM: $(npm -v 2>/dev/null || echo 'Not found')"
echo -e "  Chromium: $(chromium --version 2>/dev/null || echo 'Not found')"
echo ""

echo -e "${YELLOW}📁 NPM Packages Location:${NC}"
echo -e "  ~/node_modules/"
echo ""

echo -e "${YELLOW}📍 Current Directory:${NC}"
echo -e "  $(pwd)"
echo ""

echo -e "${GREEN}🎉 You're all set! Enjoy your Termux development environment!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"