import os
from pathlib import Path
from playwright.sync_api import sync_playwright

session_id = os.environ["ELITE_TEST_SESSION_ID"]
base_url = os.environ.get("ELITE_TEST_BASE_URL", "http://127.0.0.1:8765")
artifacts = Path(__file__).parent / "artifacts"
artifacts.mkdir(exist_ok=True)

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, label in [(375, 812, "mobile"), (1440, 1000, "desktop")]:
        context = browser.new_context(viewport={"width": width, "height": height})
        context.add_cookies([{
            "name": "elite_smiles_mktg_session",
            "value": session_id,
            "url": base_url,
        }])
        page = context.new_page()
        console_errors = []
        page.on("console", lambda message: console_errors.append(message.text) if message.type == "error" else None)
        response = page.goto(f"{base_url}/lead-agent-operations.php", wait_until="networkidle")
        if response is None or not response.ok:
            print(page.content()[:800])
        assert response is not None and response.ok, f"Dashboard returned {response.status if response else 'no response'} at {page.url}"
        assert page.get_by_role("heading", name="Lead Agent executive summary").is_visible()
        assert page.get_by_role("heading", name="Executive summary", exact=True).is_visible()
        assert page.get_by_role("heading", name="Last 30 days", exact=True).is_visible()
        assert page.get_by_role("heading", name="Agent exceptions only").is_visible()
        assert page.get_by_role("button", name="Pause all automated lead follow-up").is_visible() or page.get_by_role("button", name="Resume all automated lead follow-up").is_visible()
        assert page.locator('script[src="https://cdn.tailwindcss.com"]').count() == 0
        assert page.locator('link[href*="assets/css/lead-agent.css"]').count() == 1
        assert page.locator("body").evaluate("el => el.scrollWidth <= el.clientWidth"), f"Horizontal overflow at {width}px"
        actionable_console_errors = [error for error in console_errors if "ERR_CONNECTION_REFUSED" not in error]
        assert not actionable_console_errors, f"Console errors at {width}px: {actionable_console_errors}"
        page.screenshot(path=str(artifacts / f"lead-agent-{label}.png"), full_page=True)
        context.close()
    browser.close()

print("Lead Agent dashboard visual tests passed at 375px and 1440px.")
