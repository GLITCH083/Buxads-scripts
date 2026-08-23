const path = require('path');
const fs = require('fs');
const express = require('express');
const { connect } = require('puppeteer-real-browser');
const os = require('os');

// ===== CUSTOM TEMP DIRECTORY =====
const CUSTOM_TEMP = path.join(__dirname, 'chrome_temp');
if (!fs.existsSync(CUSTOM_TEMP)) {
  fs.mkdirSync(CUSTOM_TEMP, { recursive: true });
}

process.env.LIGHTHOUSE_TMP_DIR = CUSTOM_TEMP;
process.env.TMP = CUSTOM_TEMP;
process.env.TEMP = CUSTOM_TEMP;
process.env.TMPDIR = CUSTOM_TEMP;

process.on('uncaughtException', (err) => {
  if (err.code === 'EPERM' && err.path && err.path.includes('lighthouse')) return;
  console.error('Uncaught Exception:', err);
});

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
  const profileDir = path.join(CUSTOM_TEMP, `profile-${Date.now()}-${Math.random().toString(36).substr(2, 6)}`);

  const { browser } = await connect({
    headless: false,
    turnstile: true,
    disableXvfb: true,
    args: [
      '--no-sandbox',
      '--disable-dev-shm-usage',
      `--user-data-dir=${profileDir}`,
    ],
    ignoreDefaultArgs: ['--disable-extensions'],
    chromeLauncherOptions: {
      env: {
        ...process.env,
        TMPDIR: CUSTOM_TEMP,
        TEMP: CUSTOM_TEMP,
        TMP: CUSTOM_TEMP,
        LIGHTHOUSE_TMP_DIR: CUSTOM_TEMP,
      }
    }
  });

  const [page] = await browser.pages();
  await page.goto('about:blank');
  return { browser, page };
}

// ===== SOLVER =====
const cloudflare = require('./endpoints/cloudflare');

// ===== MIDDLEWARE =====
app.use(express.json({ limit: "50mb" }));
app.use(express.urlencoded({ extended: true, limit: "50mb" }));

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
function getLocalIp() {
  const interfaces = os.networkInterfaces();
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name]) {
      if (!iface.internal && iface.family === 'IPv4') return iface.address;
    }
  }
  return null;
}

const server = app.listen(port, () => {
  const localIp = getLocalIp();
  console.log(`IUAM Solver started on port ${port}`);
  if (localIp) console.log(`Local network: http://${localIp}:${port}`);
});

try { server.timeout = global.timeOut; } catch {}