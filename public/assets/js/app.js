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
// ===== Auth Enhancements =====
document.addEventListener("DOMContentLoaded", () => {

  // 1) Show/Hide password
  document.querySelectorAll("[data-pass-toggle]").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-pass-toggle");
      const input = document.getElementById(id);
      if (!input) return;
      const isPass = input.getAttribute("type") === "password";
      input.setAttribute("type", isPass ? "text" : "password");
      btn.textContent = isPass ? "Hide" : "Show";
    });
  });

  // 2) OTP 6-box auto focus + backspace + paste
  const wrap = document.querySelector("[data-otp-wrap]");
  if (wrap) {
    const boxes = [...wrap.querySelectorAll(".otp-box")];

    const collect = () => boxes.map(b => (b.value || "").replace(/\D/g,"").slice(0,1)).join("");
    const setHidden = () => {
      const hidden = document.querySelector('input[name="otp"]');
      if (hidden) hidden.value = collect();
    };

    boxes.forEach((box, idx) => {
      box.addEventListener("input", () => {
        box.value = (box.value || "").replace(/\D/g,"").slice(0,1);
        setHidden();
        if (box.value && idx < boxes.length - 1) boxes[idx + 1].focus();
      });

      box.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !box.value && idx > 0) {
          boxes[idx - 1].focus();
        }
      });

      box.addEventListener("paste", (e) => {
        e.preventDefault();
        const text = (e.clipboardData.getData("text") || "").replace(/\D/g,"").slice(0,6);
        text.split("").forEach((ch, i) => { if (boxes[i]) boxes[i].value = ch; });
        setHidden();
        const last = Math.min(text.length, 6) - 1;
        if (boxes[last]) boxes[last].focus();
      });
    });

    // auto focus first box
    boxes[0]?.focus();
  }

  // 3) Resend countdown timer
  const resendBtn = document.querySelector("[data-resend-btn]");
  const timerEl  = document.querySelectorAll("[data-resend-timer]")[0];

  if (resendBtn && timerEl) {
    const key = "smartbuy_resend_until";
    const seconds = parseInt(resendBtn.getAttribute("data-seconds") || "60", 10);

    function startCountdown(sec){
      const until = Date.now() + sec*1000;
      localStorage.setItem(key, String(until));
      tick();
    }

    function tick(){
      const until = parseInt(localStorage.getItem(key) || "0", 10);
      const left = Math.max(0, Math.ceil((until - Date.now())/1000));

      if (left > 0) {
        resendBtn.setAttribute("aria-disabled","true");
        resendBtn.classList.add("disabled");
        resendBtn.style.pointerEvents = "none";
        timerEl.textContent = `Resend available in ${left}s`;
        setTimeout(tick, 250);
      } else {
        resendBtn.classList.remove("disabled");
        resendBtn.style.pointerEvents = "auto";
        resendBtn.removeAttribute("aria-disabled");
        timerEl.textContent = "You can resend OTP now.";
      }
    }

    // If you want timer start when arriving verify page first time:
    if (!localStorage.getItem(key)) startCountdown(seconds);
    else tick();

    // If you click resend, reset timer (UI side)
    resendBtn.addEventListener("click", () => startCountdown(seconds));
  }

});
