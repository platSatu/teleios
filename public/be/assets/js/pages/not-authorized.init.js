/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: not-authorized init js
*/

const title = document.querySelector('.error_title');

// Default soft glowing shadow
const defaultShadow = `
  13px 12px 10px rgba(227, 102, 102, 0.20),
  6px 12px 14px rgba(255, 156, 156, 0.15),
  1px 12px 18px rgba(255, 200, 200, 0.10)
`;

window.addEventListener('load', () => {
  // Animate title
  if (title) {
    gsap.fromTo(title,
      { y: -150, opacity: 0, scale: 0.8 },
      {
        y: 0,
        opacity: 1,
        scale: 1,
        duration: 1.2,
        ease: "bounce.out",
        onComplete: () => gsap.set(title, { textShadow: defaultShadow })
      }
    );
  }
});

// Mouse hover effect for title shadow
window.addEventListener("mousemove", (e) => {
  if (!title) return;

  const x = e.clientX - window.innerWidth / 2;
  const y = e.clientY - window.innerHeight / 2;
  const rad = Math.atan2(y, x);
  const length = Math.min(Math.round(Math.sqrt(x ** 2 + y ** 2) / 20), 40);

  const xShadow = Math.round(length * Math.cos(rad));
  const yShadow = Math.round(length * Math.sin(rad));

  gsap.to(title, {
    duration: 0.3,
    textShadow: `
      ${-xShadow}px ${-yShadow}px 10px rgba(227, 102, 102, 0.20),
      ${xShadow * 0.5}px ${yShadow * 0.5}px 14px rgba(255, 156, 156, 0.15),
      0px 0px 18px rgba(255, 200, 200, 0.10)
    `,
    ease: "power2.out"
  });
});

// Reset shadow on mouse leave
window.addEventListener("mouseleave", () => {
  if (!title) return;

  gsap.to(title, {
    duration: 0.4,
    textShadow: defaultShadow,
    ease: "power2.out"
  });
});
