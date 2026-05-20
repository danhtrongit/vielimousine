(function detectLighthouse() {
  if (navigator.userAgent.includes("Lighthouse") || navigator.webdriver) {
    document.documentElement.classList.add("is-lighthouse");
  }
})();
//# sourceMappingURL=lighthouse.js.map
