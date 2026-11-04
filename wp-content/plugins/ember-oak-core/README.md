# Ember & Oak Core

Custom CSS/JS/PHP for the site (no core, Elementor or WooCommerce files are edited).

| File | Purpose |
|---|---|
| `assets/css/ember-oak.css` | Design tokens (`:root` vars mirror Elementor Global Colors/Fonts), coffee-bag visuals, ticker, floating hero cards, product card, sticky mobile bar (<720px), reduced-motion, focus states |
| `assets/js/ember-oak.js` | Toast after WC `added_to_cart`, mobile menu, roast filter, cadence preview, variation → price (data from WooCommerce variations), AJAX add-to-cart for the variable form, sticky bar visibility (IntersectionObserver) |
| `inc/fields.php` | SCF field groups (Homepage, Product): hero, trust, values, stats, testimonials, cafe, FAQ repeater, farm story, brew guide repeater |
| `inc/sections.php`, `inc/product.php` | Shortcodes used by Elementor Shortcode widgets in the Header/Footer/Single Product templates and Home page |
| `inc/commerce.php` | 15% subscription price + cadence (1/2/4 wk) per cart line, bridged to *Subscriptions for WooCommerce*; 10% bundle discount; `_eo_subscribable` / `_eo_bundle` product flags |
| `inc/seo.php` | FAQPage + Store entity merged into Rank Math JSON-LD (Product schema stays Rank Math/WooCommerce only) |

Editing: homepage text = Pages → Home (SCF). Coffee data = Products (WooCommerce + SCF "Coffee details"). Header/Footer/Single Product = Templates → Theme Builder.
Caching: never cache `/cart/`, `/checkout/`, `/my-account/`, and `wc-ajax` requests.

## Store pages (cart, my account, search)
- `inc/search.php` — products-only search (`pre_get_posts`), `eo_search_form()` (header dropdown + inline), `[eo_search]` shortcode used by the Elementor Pro **Search Results** template (condition `include/archive/search`).
- `inc/account.php` — My Account menu labels; hides Downloads and the unused WP Swings "Memberships" tab.
- CSS section "Store pages" in `ember-oak.css`: Cart block (`.wc-block-*`) and classic My Account overrides.
- CSS tokens `--eo-display` / `--eo-sans` are deliberately prefixed: Elementor defines its own `--display` (flex) inside containers, which broke the heading font when we used the unprefixed name.
