# Generic Asset Management Guide

## Goal

Make all website assets easy to rename, replace, and manage through Git.

---

## Recommended Folder Structure

```txt
assets/
  asset-map.json
  js/
    asset-loader.js
  logos/
  images/
  icons/
  backgrounds/
  fonts/
  documents/
  videos/
```

---

## Central Asset Map

Use:

```txt
assets/asset-map.json
```

Example:

```json
{
  "brand.logo.primary": "/assets/logos/logo-primary.svg",
  "brand.logo.light": "/assets/logos/logo-light.svg",
  "hero.home": "/assets/images/hero-home.webp",
  "icon.menu": "/assets/icons/icon-menu.svg"
}
```

---

## HTML Usage

```html
<img data-asset="brand.logo.primary" alt="Brand logo">
```

For background images:

```html
<section data-bg-asset="hero.home"></section>
```

---

## Why This Helps

When an asset filename changes, update only:

```txt
assets/asset-map.json
```

Instead of editing many HTML files.

---

## Naming Rules

Good names:

```txt
logo-primary.svg
logo-light.svg
hero-home.webp
service-web-design.webp
icon-phone.svg
```

Bad names:

```txt
final-logo-new-copy.png
IMG_12345.jpg
WhatsApp Image 2026.jpeg
image copy 2.png
```

---

## Asset Replacement Flow

```txt
1. Add new asset file
2. Update asset-map.json
3. Test page
4. Remove old asset only if unused
5. Commit asset and manifest change together
```
