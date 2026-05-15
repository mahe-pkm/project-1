/*
Generic Asset Loader

Purpose:
- Keeps asset filenames easy to change through assets/asset-map.js.
- Supports images and background images.
- Works locally (file://) without CORS issues.

Usage:
  <img data-asset="brand.logo" alt="Brand logo">
  <section data-bg-asset="hero.main"></section>
*/

(function () {
  function applyAsset(element, src) {
    const tag = element.tagName.toLowerCase();

    if (tag === "img") {
      element.src = src;
      return;
    }

    if (tag === "source") {
      element.srcset = src;
      return;
    }

    element.setAttribute("data-resolved-asset", src);
  }

  function applyBackgroundAsset(element, src) {
    element.style.backgroundImage = `url("${src}")`;
    element.setAttribute("data-resolved-bg-asset", src);
  }

  function resolveAssets() {
    const assetMap = window.ASSET_MAP;

    if (!assetMap) {
      console.error("Asset map not found. Make sure assets/asset-map.js is loaded.");
      return;
    }

    document.querySelectorAll("[data-asset]").forEach((element) => {
      const key = element.getAttribute("data-asset");
      const src = assetMap[key];

      if (!src) {
        console.warn("Missing asset key:", key);
        element.setAttribute("data-missing-asset", key);
        return;
      }

      applyAsset(element, src);
    });

    document.querySelectorAll("[data-bg-asset]").forEach((element) => {
      const key = element.getAttribute("data-bg-asset");
      const src = assetMap[key];

      if (!src) {
        console.warn("Missing background asset key:", key);
        element.setAttribute("data-missing-bg-asset", key);
        return;
      }

      applyBackgroundAsset(element, src);
    });
  }

  // Run on load
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", resolveAssets);
  } else {
    resolveAssets();
  }
})();
