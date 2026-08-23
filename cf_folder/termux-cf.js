// ===== TERMUX STORAGE FIX =====
process.env.NODE_PATH = require('path').join(process.env.HOME, 'node_modules');
require('module').Module._initPaths();

Object.defineProperty(process, 'platform', { value: 'linux' });

const express = require('express');
const { connect } = require('puppeteer-real-browser');
const fs = require('fs');
const path = require('path');

// ===== CONFIGURATION =====
const app = express();
const port = process.env.PORT || 7860;
const authToken = process.env.authToken || null;

global.browserLimit = Number(process.env.browserLimit) || 6;
global.timeOut = Number(process.env.timeOut) || 180000;

// ===== BROWSER SLOT SEMAPHORE =====
let activeBrowsers = 0;
const browserQueue = [];

async function acquireBrowserSlot() {
  if (global.browserLimit === 0) return;
  if (activeBrowsers < global.browserLimit) {
    activeBrowsers++;
    return;
  }
  await new Promise(resolve => browserQueue.push(resolve));
  activeBrowsers++;
}

function releaseBrowserSlot() {
  if (global.browserLimit === 0) return;
  if (browserQueue.length > 0) {
    browserQueue.shift()();
  } else {
    activeBrowsers--;
  }
}

// ===== BROWSER FACTORY =====
async function createBrowser() {
  const { browser } = await connect({
    headless: false,
    turnstile: true,
    customConfig: {
      chromePath: '/data/data/com.termux/files/usr/bin/chromium-browser'
    },
    connectOption: { defaultViewport: null },
    disableXvfb: true,
    args: [
      '--no-sandbox',
      '--disable-dev-shm-usage',
    ]
  });

  const [page] = await browser.pages();
  await page.goto('about:blank');
  return { browser, page };
}

// ===== SOLVER =====
const cloudflare = require('./endpoints/cloudflare');

// ===== MIDDLEWARE =====
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

app.use((req, res, next) => {
  if (authToken && req.headers.authorization !== `Bearer ${authToken}`) {
    return res.status(401).json({ message: 'Unauthorized' });
  }
  next();
});

// ===== ENDPOINTS =====
app.get('/', (req, res) => {
  res.json({ message: 'IUAM Solver running', activeBrowsers });
});

app.post('/bypass', async (req, res) => {
  const data = req.body;

  if (!data || data.mode !== 'iuam') {
    return res.status(400).json({ success: false, message: "Only mode 'iuam' is supported" });
  }

  let browser;
  try {
    await acquireBrowserSlot();
    const ctx = await createBrowser();
    browser = ctx.browser;
    const page = ctx.page;

    const result = await cloudflare(data, page);
    res.json({ success: true, ...result });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  } finally {
    if (browser) {
      try { await browser.close(); } catch {}
    }
    releaseBrowserSlot();
  }
});

app.use((req, res) => {
  res.status(404).json({ message: 'Not Found' });
});

// ===== START =====
const server = app.listen(port, () => {
  console.log(`IUAM Solver running on port ${port}`);
});

try { server.timeout = global.timeOut; } catch {}