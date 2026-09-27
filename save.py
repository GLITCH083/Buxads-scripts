#!/usr/bin/env python3
"""
Async Captcha Downloader for CryptoCollect using requests
"""

import asyncio
import os
import re
import base64
import aiohttp
import aiofiles
from bs4 import BeautifulSoup
from datetime import datetime

class AsyncCaptchaDownloader:
    def __init__(self, url="https://cryptocollect.in/login", output_dir="captcha_images", max_images=50):
        self.url = url
        self.output_dir = output_dir
        self.max_images = max_images
        self.session = None
        self.csrf_token = None
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none',
            'Sec-Fetch-User': '?1',
            'Cache-Control': 'max-age=0',
        }
    
    def create_output_dir(self):
        if not os.path.exists(self.output_dir):
            os.makedirs(self.output_dir)
            print(f"📁 Created folder: {self.output_dir}")
        else:
            print(f"📁 Using existing folder: {self.output_dir}")
    
    async def fetch_page(self, session, url):
        """Fetch a page and return the HTML content"""
        try:
            async with session.get(url, headers=self.headers) as response:
                return await response.text()
        except Exception as e:
            print(f"❌ Error fetching page: {e}")
            return None
    
    def extract_csrf_token(self, html):
        """Extract CSRF token from HTML"""
        match = re.search(r'name="csrf_token_name" value="([^"]*)"', html)
        if match:
            return match.group(1)
        return None
    
    def extract_captcha(self, html):
        """Extract base64 captcha image from HTML"""
        match = re.search(r'src="data:image/jpeg;base64,([^"]*)"', html)
        if match:
            return match.group(1)
        return None
    
    async def download_captcha(self, session, index):
        """Download a single captcha image"""
        try:
            # Fetch page
            html = await self.fetch_page(session, self.url)
            if not html:
                return False
            
            # Extract CSRF token if not already set
            if not self.csrf_token:
                self.csrf_token = self.extract_csrf_token(html)
                if self.csrf_token:
                    print(f"✅ CSRF Token: {self.csrf_token}")
            
            # Extract captcha
            base64_data = self.extract_captcha(html)
            if not base64_data:
                print(f"⚠️ No captcha found for image {index}")
                return False
            
            # Save image
            filename = os.path.join(self.output_dir, f"captcha_{index:02d}.jpg")
            image_data = base64.b64decode(base64_data)
            
            async with aiofiles.open(filename, 'wb') as f:
                await f.write(image_data)
            
            print(f"💾 Saved: captcha_{index:02d}.jpg")
            return True
            
        except Exception as e:
            print(f"❌ Error downloading captcha {index}: {e}")
            return False
    
    async def download_all(self):
        """Download all captcha images asynchronously"""
        self.create_output_dir()
        
        async with aiohttp.ClientSession() as session:
            # Download first image to get CSRF token
            await self.download_captcha(session, 1)
            
            # Create tasks for remaining images
            tasks = []
            for i in range(2, self.max_images + 1):
                tasks.append(self.download_captcha(session, i))
            
            # Wait for all tasks to complete
            results = await asyncio.gather(*tasks, return_exceptions=True)
            
            success_count = sum(1 for r in results if r is True)
            fail_count = len(results) - success_count
            
            print(f"\n✅ Complete! Downloaded {success_count + 1} images to '{self.output_dir}'")
            print(f"❌ Failed: {fail_count} images")

# Simplified version using requests (synchronous but with retries)
import requests
import time
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

def download_captchas_sync(url="https://cryptocollect.in/login", output_dir="captcha_images", max_images=150):
    """Synchronous version with retry logic"""
    
    # Create output directory
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
        print(f"📁 Created folder: {output_dir}")
    
    # Setup session with retries
    session = requests.Session()
    retry = Retry(total=3, backoff_factor=1, status_forcelist=[500, 502, 503, 504])
    adapter = HTTPAdapter(max_retries=retry)
    session.mount('http://', adapter)
    session.mount('https://', adapter)
    
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.5',
        'Accept-Encoding': 'gzip, deflate, br',
        'Connection': 'keep-alive',
        'Upgrade-Insecure-Requests': '1',
    }
    
    success_count = 0
    
    for i in range(1, max_images + 1):
        try:
            # Fetch page
            response = session.get(url, headers=headers, timeout=10)
            html = response.text
            
            # Extract captcha
            match = re.search(r'src="data:image/jpeg;base64,([^"]*)"', html)
            if not match:
                print(f"⚠️ No captcha found for image {i}")
                continue
            
            # Save image
            base64_data = match.group(1)
            image_data = base64.b64decode(base64_data)
            
            filename = os.path.join(output_dir, f"captcha_{i:02d}.jpg")
            with open(filename, 'wb') as f:
                f.write(image_data)
            
            success_count += 1
            print(f"💾 Saved: captcha_{i:02d}.jpg ({i}/{max_images})")
            
            # Small delay to avoid rate limiting
            time.sleep(0.5)
            
        except Exception as e:
            print(f"❌ Error on image {i}: {e}")
            time.sleep(2)
    
    print(f"\n✅ Complete! Downloaded {success_count} images to '{output_dir}'")

if __name__ == "__main__":
    # Run async version
    # asyncio.run(AsyncCaptchaDownloader().download_all())
    
    # Or run sync version (simpler, more stable)
    download_captchas_sync()