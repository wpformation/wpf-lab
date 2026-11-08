"""Tests fonctionnels et de sécurité de WPF Édition au clic, sur le laboratoire monté par lab/setup.sh.

Usage : python tests/test_wpf_clic.py captures/   (WPF_LAB_URL pour un autre port)
"""
import json
import os
import urllib.request
import sys
import time
from pathlib import Path
from playwright.sync_api import sync_playwright

sys.stdout.reconfigure(encoding="utf-8")
BASE = os.environ.get("WPF_LAB_URL", "http://localhost:8088")
URL = BASE + "/atelier-html/"
with urllib.request.urlopen(BASE + "/wp-json/wp/v2/pages?slug=atelier-html&_fields=id") as r:
    PAGE_ID = json.load(r)[0]["id"]
OUT = Path(sys.argv[1])
OUT.mkdir(parents=True, exist_ok=True)
R = {}


def login(page, user, pwd):
    page.goto(f"{BASE}/wp-login.php")
    page.fill("#user_login", user)
    page.fill("#user_pass", pwd)
    page.click("#wp-submit")
    page.wait_for_load_state("networkidle")


def rest_call(page, payload, nonce=None, base=None):
    return page.evaluate(
        """async ([url, payload, nonce]) => {
            const r = await fetch(url, {method: 'POST', credentials: 'same-origin',
              headers: Object.assign({'Content-Type': 'application/json'}, nonce ? {'X-WP-Nonce': nonce} : {}),
              body: JSON.stringify(payload)});
            return {status: r.status, body: await r.json().catch(() => null)};
        }""",
        [f"{BASE}/wp-json/wpf-clic/v1/posts/{PAGE_ID}/fields", payload, nonce],
    )


with sync_playwright() as p:
    browser = p.chromium.launch()

    # Visiteur
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    page = ctx.new_page()
    urls = []
    page.on("response", lambda r: urls.append(r.url))
    page.goto(URL)
    page.wait_for_load_state("networkidle")
    html = page.content()
    R["anon_markers_left"] = html.count("data-wpf-")
    R["anon_style_kept"] = ".lumen-landing .hero" in html
    R["anon_wpf_assets"] = [u for u in urls if "wpf-clic" in u]
    R["anon_rest_status"] = rest_call(page, {"field": "hero.title", "base": "x", "value": "pirate"})["status"]
    page.screenshot(path=str(OUT / "wpf-clic-01-visiteur.png"))
    ctx.close()

    # Abonné : nonce REST obtenu par admin-ajax, puis tentative d'écriture
    ctx = browser.new_context()
    page = ctx.new_page()
    login(page, "abonne", "lab-abonne-local")
    page.goto(URL)
    R["subscriber_toggle"] = page.locator(".wpf-clic-toggle").count()
    nonce = page.evaluate("fetch('/wp-admin/admin-ajax.php?action=rest-nonce',{credentials:'same-origin'}).then(r=>r.text())")
    R["subscriber_rest_status"] = rest_call(page, {"field": "hero.title", "base": "x", "value": "pirate"}, nonce)["status"]
    ctx.close()

    # Éditrice
    ctx = browser.new_context(viewport={"width": 1440, "height": 900})
    page = ctx.new_page()
    login(page, "cliente", "lab-cliente-local")
    sizes = {}
    def size_of(r):
        if "wpf-clic/assets" in r.url:
            try:
                sizes[r.url.split("/wp-content/")[-1]] = len(r.body())
            except Exception:
                pass
    page.on("response", size_of)
    page.goto(URL)
    page.wait_for_load_state("networkidle")
    R["editor_assets"] = sizes
    page.click(".wpf-clic-toggle")
    page.screenshot(path=str(OUT / "wpf-clic-02-mode-edition.png"))

    # Texte simple
    t0 = time.time()
    page.click('[data-wpf-id="hero.title"]')
    page.keyboard.press("Control+A")
    page.keyboard.type("Des bols qui passent au lave-vaisselle")
    page.screenshot(path=str(OUT / "wpf-clic-03-titre.png"))
    page.keyboard.press("Enter")
    page.wait_for_selector(".wpf-clic-toast:not([hidden])")
    R["toast_text"] = page.inner_text(".wpf-clic-toast")
    R["text_save_seconds"] = round(time.time() - t0, 1)

    # Texte riche : saisie puis gras
    page.click('[data-wpf-id="card1.text"]')
    page.keyboard.press("Control+A")
    page.keyboard.type("Séries courtes, grès local")
    page.keyboard.press("Shift+Home")
    page.click(".wpf-clic-bar button:has-text('Gras')")
    page.click(".wpf-clic-bar button:has-text('Enregistrer')")
    time.sleep(1)

    # Lien
    page.click('[data-wpf-id="hero.cta"]')
    page.wait_for_selector(".wpf-clic-bar input")
    inputs = page.locator(".wpf-clic-bar input")
    inputs.nth(0).fill("Réserver un cours")
    inputs.nth(1).fill("/cours/")
    page.screenshot(path=str(OUT / "wpf-clic-04-lien.png"))
    page.click(".wpf-clic-bar button:has-text('Enregistrer')")
    time.sleep(1)

    # Image : médiathèque
    box_before = page.locator(".frame").bounding_box()
    page.click('[data-wpf-id="hero.image"]')
    page.wait_for_selector(".media-modal .attachment", timeout=15000)
    time.sleep(1)
    page.screenshot(path=str(OUT / "wpf-clic-05-mediatheque.png"))
    page.click('.media-modal .attachment[aria-label="bol-portrait"]')
    page.click(".media-modal .media-button-select")
    time.sleep(1.5)

    # Contrôles après rechargement
    page.goto(URL)
    page.wait_for_load_state("networkidle")
    R["h1_after"] = page.inner_text('[data-wpf-id="hero.title"]')
    R["card1_html_after"] = page.inner_html('[data-wpf-id="card1.text"]')
    R["cta_after"] = [page.inner_text('[data-wpf-id="hero.cta"]'), page.get_attribute('[data-wpf-id="hero.cta"]', "href")]
    R["img_src_after"] = page.get_attribute('[data-wpf-id="hero.image"]', "src")
    box_after = page.locator(".frame").bounding_box()
    R["frame_box_same"] = [round(box_before["width"]), round(box_before["height"])] == [round(box_after["width"]), round(box_after["height"])]
    page.screenshot(path=str(OUT / "wpf-clic-06-apres.png"))

    # Sécurité côté serveur, avec le nonce de l'éditrice
    cfg = page.evaluate("window.wpfClic")
    inj = rest_call(page, {"field": "card2.text", "base": cfg["base"], "value": '<script>alert(1)</script><img src=x onerror=alert(1)><strong>ok</strong><a href="javascript:alert(1)">x</a>'}, cfg["nonce"])
    R["injection_status"] = inj["status"]
    R["injection_saved"] = (inj["body"] or {}).get("html")
    R["stale_base_status"] = rest_call(page, {"field": "hero.title", "base": cfg["base"], "value": "conflit"}, cfg["nonce"])["status"]
    base = (inj["body"] or {}).get("base")
    R["marker_in_value_status"] = rest_call(page, {"field": "card3.text", "base": base, "value": 'x data-wpf-id="card1.title"'}, cfg["nonce"])["status"]
    unb = rest_call(page, {"field": "hero.text", "base": base, "value": "Texte</li></ul><p>suite"}, cfg["nonce"])
    R["unbalanced_saved"] = (unb["body"] or {}).get("html")
    page.goto(URL)
    cfg = page.evaluate("window.wpfClic")
    R["unknown_field_status"] = rest_call(page, {"field": "rien.ici", "base": cfg["base"], "value": "x"}, cfg["nonce"])["status"]
    R["style_still_there"] = ".lumen-landing .hero" in page.content()
    ctx.close()
    browser.close()

print(json.dumps(R, ensure_ascii=False, indent=1))
