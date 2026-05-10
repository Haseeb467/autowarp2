(() => {
  const body = document.body;
  const header = document.querySelector(".site-header");
  const menuToggle = document.querySelector(".menu-toggle");
  const navLinks = document.querySelector(".nav-links");
  const cursorDot = document.querySelector(".cursor-dot");
  const cursorRing = document.querySelector(".cursor-ring");

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  let showSiteLoader = () => {};

  const setupSiteLoader = () => {
    const loader = document.querySelector(".site-loader");
    if (!loader) {
      body.classList.add("page-loaded");
      return;
    }

    const hideLoader = () => {
      body.classList.add("loader-complete", "page-loaded");
      body.classList.remove("loader-active");
      loader.setAttribute("aria-hidden", "true");
    };

    showSiteLoader = () => {
      body.classList.remove("loader-complete", "page-exiting");
      body.classList.add("loader-active");
      loader.setAttribute("aria-hidden", "false");
    };

    showSiteLoader();

    if (document.readyState === "complete") {
      hideLoader();
    } else {
      window.addEventListener("load", hideLoader, { once: true });
    }

    window.addEventListener("pageshow", (event) => {
      if (event.persisted) hideLoader();
    });
  };

  setupSiteLoader();

  window.addEventListener("load", () => {
    body.classList.add("page-loaded");
    if (window.lucide) {
      window.lucide.createIcons();
    }
  });

  const setHeaderState = () => {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 24);
  };

  setHeaderState();
  window.addEventListener("scroll", setHeaderState, { passive: true });

  if (menuToggle && navLinks) {
    menuToggle.addEventListener("click", () => {
      const isOpen = menuToggle.classList.toggle("is-active");
      navLinks.classList.toggle("is-open", isOpen);
      header?.classList.toggle("is-open", isOpen);
      body.classList.toggle("menu-open", isOpen);
      menuToggle.setAttribute("aria-expanded", String(isOpen));
    });

    navLinks.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        menuToggle.classList.remove("is-active");
        navLinks.classList.remove("is-open");
        header?.classList.remove("is-open");
        body.classList.remove("menu-open");
        menuToggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  const setupCursor = () => {
    if (!cursorDot || !cursorRing || window.matchMedia("(pointer: coarse)").matches) return;

    let dotX = 0;
    let dotY = 0;
    let ringX = 0;
    let ringY = 0;

    const moveRing = () => {
      ringX += (dotX - ringX) * 0.18;
      ringY += (dotY - ringY) * 0.18;
      cursorDot.style.transform = `translate3d(${dotX}px, ${dotY}px, 0) translate(-50%, -50%)`;
      cursorRing.style.transform = `translate3d(${ringX}px, ${ringY}px, 0) translate(-50%, -50%)`;
      requestAnimationFrame(moveRing);
    };

    window.addEventListener("pointermove", (event) => {
      dotX = event.clientX;
      dotY = event.clientY;
      cursorDot.style.opacity = "1";
      cursorRing.style.opacity = "1";
    });

    document.querySelectorAll("a, button, input, textarea, select, .gallery-item").forEach((item) => {
      item.addEventListener("pointerenter", () => cursorRing.classList.add("is-hovering"));
      item.addEventListener("pointerleave", () => cursorRing.classList.remove("is-hovering"));
    });

    moveRing();
  };

  setupCursor();

  const setupPageTransitions = () => {
    document.querySelectorAll('a[href]:not([target]):not([href^="#"]):not([href^="mailto:"]):not([href^="tel:"])').forEach((link) => {
      link.addEventListener("click", (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
        event.preventDefault();
        showSiteLoader();
        window.setTimeout(() => {
          window.location.href = url.href;
        }, prefersReducedMotion ? 0 : 180);
      });
    });
  };

  setupPageTransitions();

  const setupAnimations = () => {
    const revealItems = document.querySelectorAll(".reveal");

    if (prefersReducedMotion) {
      revealItems.forEach((item) => item.classList.add("is-visible"));
      return;
    }

    if (window.gsap && window.ScrollTrigger) {
      window.gsap.registerPlugin(window.ScrollTrigger);

      revealItems.forEach((item) => {
        window.gsap.fromTo(
          item,
          { y: 34, opacity: 0 },
          {
            y: 0,
            opacity: 1,
            duration: 0.9,
            ease: "power3.out",
            scrollTrigger: {
              trigger: item,
              start: "top 86%",
              once: true,
            },
          }
        );
      });

      window.gsap.utils.toArray("[data-speed]").forEach((item) => {
        const speed = Number(item.dataset.speed || 0.12);
        window.gsap.to(item, {
          yPercent: speed * -100,
          ease: "none",
          scrollTrigger: {
            trigger: item,
            scrub: true,
          },
        });
      });
    } else if ("IntersectionObserver" in window) {
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("is-visible");
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.14 }
      );
      revealItems.forEach((item) => observer.observe(item));
    } else {
      revealItems.forEach((item) => item.classList.add("is-visible"));
    }
  };

  setupAnimations();

  const setupGalleryFilters = () => {
    const filterButtons = document.querySelectorAll("[data-filter]");
    const galleryItems = document.querySelectorAll("[data-category]");
    if (!filterButtons.length || !galleryItems.length) return;

    filterButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const filter = button.dataset.filter;
        filterButtons.forEach((item) => item.classList.remove("is-active"));
        button.classList.add("is-active");

        galleryItems.forEach((item) => {
          const shouldShow = filter === "all" || item.dataset.category === filter;
          item.classList.toggle("is-hidden", !shouldShow);
        });
      });
    });
  };

  setupGalleryFilters();

  const setupLightbox = () => {
    const lightbox = document.querySelector(".lightbox");
    const lightboxImage = document.querySelector(".lightbox img");
    const closeButton = document.querySelector(".lightbox-close");
    const galleryItems = document.querySelectorAll(".gallery-page-grid .gallery-item");
    if (!lightbox || !lightboxImage || !galleryItems.length) return;

    const close = () => {
      lightbox.classList.remove("is-open");
      lightbox.setAttribute("aria-hidden", "true");
      lightboxImage.removeAttribute("src");
      body.style.overflow = "";
    };

    galleryItems.forEach((item) => {
      item.addEventListener("click", () => {
        const img = item.querySelector("img");
        if (!img) return;
        lightboxImage.src = img.src;
        lightboxImage.alt = img.alt || "Autoskins gallery image";
        lightbox.classList.add("is-open");
        lightbox.setAttribute("aria-hidden", "false");
        body.style.overflow = "hidden";
      });
    });

    closeButton?.addEventListener("click", close);
    lightbox.addEventListener("click", (event) => {
      if (event.target === lightbox) close();
    });
    window.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && lightbox.classList.contains("is-open")) close();
    });
  };

  setupLightbox();

  const setupContactForm = () => {
    const form = document.querySelector(".contact-form");
    const status = document.querySelector(".form-status");
    if (!form || !status) return;

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      status.textContent = "Thanks. Your quote request is ready for review.";
      form.reset();
    });
  };

  setupContactForm();

})();
