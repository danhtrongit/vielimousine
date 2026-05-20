(function() {
  document.querySelectorAll('a._blank, a.blank, a[target="_blank"]').forEach((el) => {
    if (!el.hasAttribute("target") || el.getAttribute("target") !== "_blank") {
      el.setAttribute("target", "_blank");
    }
    const relValue = el == null ? void 0 : el.getAttribute("rel");
    if (!relValue || !relValue.includes("noopener") || !relValue.includes("nofollow")) {
      const newRelValue = (relValue ? relValue + " " : "") + "noopener noreferrer";
      el.setAttribute("rel", newRelValue);
    }
  });
  function fixA11yAttributes() {
    document.querySelectorAll('ul.sub-menu[role="menubar"]').forEach((menu) => {
      menu.setAttribute("role", "menu");
    });
    document.querySelectorAll('[aria-hidden="true"] a, [aria-hidden="true"] button').forEach((el) => {
      el.setAttribute("tabindex", "-1");
    });
  }
  fixA11yAttributes();
  const navEl = document.querySelector("nav.nav") || document.querySelector("#header");
  if (navEl) {
    const observer = new MutationObserver(fixA11yAttributes);
    observer.observe(navEl, { childList: true, subtree: true });
    setTimeout(() => observer.disconnect(), 5e3);
  }
})();
//# sourceMappingURL=global.js.map
