/*
Generic Asset Loader

Purpose:
- Keeps asset filenames easy to change through assets/asset-map.json.
- Supports images and background images.

Usage:
  <img data-asset="brand.logo.primary" alt="Brand logo">
  <section data-bg-asset="hero.home"></section>
*/

(function () {
  const manifestPath = "assets/asset-map.json";

  async function loadAssetMap() {
    const response = await fetch(manifestPath, { cache: "no-store" });

    if (!response.ok) {
      throw new Error("Failed to load asset map: " + manifestPath);
    }

    return response.json();
  }

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

  function resolveAssets(assetMap) {
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

  document.addEventListener("DOMContentLoaded", async () => {
    try {
      const assetMap = await loadAssetMap();
      resolveAssets(assetMap);
    } catch (error) {
      console.error(error);
    }
  });
})();
