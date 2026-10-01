(() => {
  const root = document.documentElement;
  const KEY = "smartbuy_theme";

  function apply(t){
    root.setAttribute("data-theme", t);
    localStorage.setItem(KEY, t);
  }

  apply(localStorage.getItem(KEY) || "dark");

  document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("themeToggle");
    if(btn){
      btn.addEventListener("click", () => {
        apply(root.getAttribute("data-theme") === "dark" ? "light" : "dark");
      });
    }
  });
})();
