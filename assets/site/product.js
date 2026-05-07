(() => {
  const thumbs = Array.from(document.querySelectorAll(".gallery-thumb"));
  const currentImage = document.getElementById("galleryCurrentImage");
  let currentThumb = 0;

  const setThumb = (idx) => {
    if (!thumbs.length) return;
    currentThumb = (idx + thumbs.length) % thumbs.length;
    thumbs.forEach((thumb, index) => thumb.classList.toggle("active", index === currentThumb));

    if (!currentImage) return;
    const full = thumbs[currentThumb].getAttribute("data-full");
    if (full) currentImage.src = full;
  };

  thumbs.forEach((thumb, index) => {
    thumb.addEventListener("click", () => setThumb(index));
  });

  document.querySelectorAll("[data-gallery-direction]").forEach((button) => {
    button.addEventListener("click", () => {
      const direction = Number(button.getAttribute("data-gallery-direction") || 0);
      setThumb(currentThumb + direction);
    });
  });

  document.querySelectorAll(".tab-btn").forEach((button) => {
    button.addEventListener("click", () => {
      const target = button.getAttribute("data-tab");
      document.querySelectorAll(".tab-btn").forEach((item) => item.classList.remove("active"));
      document.querySelectorAll(".tab-content").forEach((item) => item.classList.remove("active"));
      button.classList.add("active");
      const panel = document.getElementById(target || "");
      if (panel) panel.classList.add("active");
    });
  });
})();
