/* MARKAZ shared book authentication interactions. Portal-specific requests remain in their existing scripts. */
(function () {
  "use strict";

  var book = document.getElementById("book");
  if (!book) return;

  var openBtn = document.getElementById("openBook");
  var formPage = document.getElementById("formPage");
  var closeBtns = document.querySelectorAll("[data-close-book]");
  var isOpen = false;
  var reduceMotion = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : { matches: false };
  var hasMouse = window.matchMedia ? window.matchMedia("(hover: hover) and (pointer: fine)") : { matches: false };

  function each(list, fn) {
    Array.prototype.forEach.call(list, fn);
  }

  function lockForm(locked) {
    if (!formPage) return;
    if ("inert" in formPage) formPage.inert = locked;
    formPage.setAttribute("aria-hidden", locked ? "true" : "false");
  }

  function flipDuration() {
    return reduceMotion.matches ? 0 : 750;
  }

  function openBook() {
    if (isOpen) return;
    isOpen = true;
    book.classList.add("is-open");
    if (openBtn) openBtn.setAttribute("aria-expanded", "true");
    lockForm(false);
    if (hasMouse.matches && formPage) {
      window.setTimeout(function () {
        var first = formPage.querySelector("input:not([type=checkbox]), select");
        if (first && isOpen) {
          try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
        }
      }, flipDuration());
    }
  }

  function closeBook() {
    if (!isOpen) return;
    isOpen = false;
    book.classList.remove("is-open");
    if (openBtn) openBtn.setAttribute("aria-expanded", "false");
    lockForm(true);
    window.scrollTo(0, 0);
    if (openBtn) {
      window.setTimeout(function () {
        if (!isOpen) {
          try { openBtn.focus({ preventScroll: true }); } catch (e) { openBtn.focus(); }
        }
      }, flipDuration());
    }
  }

  lockForm(true);
  if (openBtn) openBtn.addEventListener("click", openBook);
  each(closeBtns, function (btn) { btn.addEventListener("click", closeBook); });

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape" || !isOpen) return;
    if (/^(INPUT|SELECT|TEXTAREA)$/.test(event.target.tagName)) return;
    closeBook();
  });

  if (/(^|[?&#])open(=|&|$)/.test(window.location.search + window.location.hash)) {
    book.classList.add("no-anim");
    openBook();
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () { book.classList.remove("no-anim"); });
    });
  }

  each(document.querySelectorAll("[data-toggle-password]"), function (button) {
    var input = document.getElementById(button.getAttribute("aria-controls"));
    var showIcon = button.querySelector(".icon-show");
    var hideIcon = button.querySelector(".icon-hide");
    if (!input) return;
    button.addEventListener("click", function () {
      var reveal = input.type === "password";
      input.type = reveal ? "text" : "password";
      button.setAttribute("aria-pressed", reveal ? "true" : "false");
      button.setAttribute("aria-label", reveal ? "Hide password" : "Show password");
      if (showIcon) showIcon.toggleAttribute("hidden", reveal);
      if (hideIcon) hideIcon.toggleAttribute("hidden", !reveal);
    });
  });

  function getForm(form) {
    return form || document.querySelector("[data-auth-form]");
  }

  function showMessage(message, type, form) {
    var target = getForm(form);
    var box = target && target.querySelector(".form-message");
    if (!box) return;
    box.textContent = message;
    box.classList.remove("is-error", "is-success");
    box.classList.add("is-visible", type === "success" ? "is-success" : "is-error");
  }

  function clearMessage(form) {
    var target = getForm(form);
    var box = target && target.querySelector(".form-message");
    if (!box) return;
    box.textContent = "";
    box.classList.remove("is-visible", "is-error", "is-success");
  }

  function setLoading(loading, form) {
    var target = getForm(form);
    var button = target && target.querySelector("button[type=submit]");
    if (!button) return;
    if (loading) {
      button.setAttribute("aria-busy", "true");
      button.disabled = true;
    } else {
      button.removeAttribute("aria-busy");
      button.disabled = false;
    }
  }

  each(document.querySelectorAll("[data-auth-form]"), function (form) {
    function firstInvalidField() {
      var first = null;
      each(form.elements, function (element) {
        if (!element.willValidate) return;
        var valid = element.checkValidity();
        element.setAttribute("aria-invalid", valid ? "false" : "true");
        if (!valid && !first) first = element;
      });
      return first;
    }

    form.addEventListener("submit", function (event) {
      var invalid = firstInvalidField();
      if (invalid) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var message = form.getAttribute("data-required-message") || "Please complete all required fields.";
        if (invalid.validity && invalid.validity.typeMismatch && invalid.type === "email") {
          message = "Enter a valid email address.";
        }
        showMessage(message, "error", form);
        invalid.focus();
      } else {
        clearMessage(form);
      }
    });

    function onEdit(event) {
      var element = event.target;
      if (element.getAttribute && element.getAttribute("aria-invalid") === "true") {
        element.setAttribute("aria-invalid", "false");
      }
      var box = form.querySelector(".form-message.is-error");
      if (box) clearMessage(form);
    }
    form.addEventListener("input", onEdit);
    form.addEventListener("change", onEdit);
  });

  each(document.querySelectorAll(".logo-ring img"), function (img) {
    function fail() { if (img.parentNode) img.parentNode.classList.add("no-logo"); }
    img.addEventListener("error", fail);
    if (img.complete && img.naturalWidth === 0) fail();
  });

  var year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  /* Support number is read from the existing public settings endpoint, never hardcoded here. */
  each(document.querySelectorAll("[data-support-whatsapp]"), function (link) {
    link.removeAttribute("href");
    fetch("api/settings/support-contact.php", {
      credentials: "same-origin",
      cache: "no-store",
      headers: { "Accept": "application/json" }
    }).then(function (response) {
      return response.ok ? response.json() : null;
    }).then(function (data) {
      if (!data || !data.success || !data.whatsapp_number) return;
      var digits = String(data.whatsapp_number).replace(/\D/g, "");
      if (digits.indexOf("0") === 0) digits = "234" + digits.slice(1);
      else if (digits.indexOf("2340") === 0) digits = "234" + digits.slice(4);
      if (digits.length < 10 || digits.length > 15) return;
      link.href = "https://wa.me/" + digits;
      link.setAttribute("target", "_blank");
      link.setAttribute("rel", "noopener noreferrer");
    }).catch(function () {});
  });

  window.MarkazAuthUI = {
    open: openBook,
    close: closeBook,
    showMessage: showMessage,
    clearMessage: clearMessage,
    setLoading: setLoading
  };
})();
