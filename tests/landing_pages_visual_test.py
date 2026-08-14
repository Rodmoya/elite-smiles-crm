from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8765/_local_preview_veneers.php?slug="
SLUGS = [
    "veneers-lehi-v1",
    "implants-draper-v1",
    "all-on-x-south-jordan-v1",
    "smile-makeover-alpine-v1",
    "lip-repositioning-park-city-v1",
]
ARTIFACTS = Path(__file__).resolve().parent / "artifacts" / "landing-pages"
ARTIFACTS.mkdir(parents=True, exist_ok=True)

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, suffix in [(375, 812, "mobile"), (1440, 1000, "desktop")]:
        page = browser.new_page(viewport={"width": width, "height": height})
        console_errors = []
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)
        for slug in SLUGS:
            response = page.goto(BASE + slug, wait_until="domcontentloaded", timeout=30000)
            assert response and response.status == 200, f"{slug} returned {response.status if response else 'no response'}"
            page.locator("form#quickLeadForm").wait_for(state="attached", timeout=10000)
            assert page.locator("h1").count() == 1, f"{slug} must render one semantic H1"
            visible_h1 = page.locator("h1:visible").first.inner_text().strip()
            assert visible_h1, f"{slug} has no visible H1"
            assert page.locator("text=Check Current Traffic in Google Maps").count() == 1, f"{slug} has no traffic link"
            assert page.locator("form#quickLeadForm").count() == 1, f"{slug} has no quick lead form"
            overflow = page.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth")
            assert not overflow, f"{slug} overflows horizontally at {width}px"
            assert not console_errors, f"{slug} console errors: {console_errors}"
            if slug in ("all-on-x-south-jordan-v1", "lip-repositioning-park-city-v1"):
                page.screenshot(path=str(ARTIFACTS / f"{slug}-{suffix}.png"), full_page=True)
        page.close()
    browser.close()

print("Organic landing page visual tests passed.")
