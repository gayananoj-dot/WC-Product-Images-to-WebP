# WooCommerce Product Images to WebP

Convert WooCommerce product images (featured + gallery) from **JPEG/PNG to WebP**, update the attachment to use the `.webp` file, and optionally bulk convert existing products.

This plugin replaces the attachment file reference so WordPress and WooCommerce natively serve WebP images — no runtime rewriting, no output buffering, and no frontend performance penalty.

---

## Features

- Converts **featured product images**
- Converts **gallery product images**
- Bulk conversion tool (WP Admin → Tools → WC Product WebP)
- Automatic conversion on product save
- Imagick support (preferred)
- GD fallback support
- Optional original file retention
- WP-CLI support
- Zero frontend overhead
- No external services required

---

## How It Works

1. Detects JPEG/PNG attachments linked to WooCommerce products.
2. Generates a `.webp` version using Imagick or GD.
3. Updates `_wp_attached_file` to point to the new `.webp` file.
4. Updates the attachment MIME type to `image/webp`.
5. Regenerates attachment metadata.
6. Optionally preserves the original image file.

After conversion, WordPress and WooCommerce serve WebP files natively.

---

## Installation

### Manual Installation

1. Upload the plugin folder to:

   /wp-content/plugins/

2. Activate the plugin from:

   WordPress Admin → Plugins

3. Go to:

   Tools → WC Product WebP

4. Click **Bulk convert product images**

---

### Installation via Git

```bash
git clone https://github.com/YOUR_USERNAME/wc-product-webp.git
```

Move the folder into your `/wp-content/plugins/` directory and activate it.

---

## WP-CLI Support

Bulk convert product images via CLI:

```bash
wp wcpw convert --batch=100
```

Options:

- `--batch` Number of products processed per iteration (default: 50)

Recommended for large product catalogs.

---

## Requirements

- PHP 7.4 or higher
- WordPress 6.x+
- WooCommerce installed and active
- One of:
  - Imagick PHP extension (recommended)
  - GD with WebP support enabled

---

## Safety & Compatibility

- Converts only `image/jpeg` and `image/png`
- Skips images already converted
- Stores original file path in attachment meta:
  
  `_wcpw_original_attached_file`

- Works with most CDNs (may require cache purge after bulk conversion)
- Compatible with caching plugins and reverse proxies

---

## Performance Benefits

- No runtime image rewriting
- No output buffering
- No frontend hooks
- Reduced image file size
- Better Core Web Vitals (LCP improvements)

---

## Known Limitations

- Does not convert non-product media library images
- Does not generate `<picture>` fallback tags
- Requires server-level WebP support
- Very large libraries should use WP-CLI for best performance

---

## Versioning

This project follows semantic versioning:
MAJOR.MINOR.PATCH

Example:
v1.0.0

---

## License
GPL v2 or later

---

## Contributing
Pull requests are welcome.
For major changes, please open an issue first to discuss your proposal.

---

## Author
Gayan
