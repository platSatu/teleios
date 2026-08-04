/*
Template Name: Mirbal - Admin & Dashboard Template
Author: SRBThemes
File: Auth init js
*/

document.addEventListener("DOMContentLoaded", function () {

  // ✅ 1. Hover distortion effect for decoration section
  const decoSection = document.querySelector(".decoration-section");
  new hoverEffect({
    parent: decoSection,
    intensity: 0.4,                  // distortion strength
    image1: "assets/images/auth/img-1.avif", // first image
    image2: "assets/images/auth/img-3.jpg",  // second image
    displacementImage: "assets/images/auth/img-1.avif" // displacement map
  });

  // ✅ 2. GSAP 3D tilt effect for main-wrapper
  const wrapper = document.querySelector(".main-wrapper");

  wrapper.addEventListener("mousemove", (e) => {
    const bounds = wrapper.getBoundingClientRect();
    const centerX = bounds.left + bounds.width / 1.5;
    const centerY = bounds.top + bounds.height / 1.5;
    const mouseX = e.clientX - centerX;
    const mouseY = e.clientY - centerY;

    const rotateX = (mouseY / bounds.height) * 5;  // tilt up/down
    const rotateY = (mouseX / bounds.width) * -5; // tilt left/right

    gsap.to(wrapper, {
      rotationX: rotateX,
      rotationY: rotateY,
      transformPerspective: 1200,
      transformOrigin: "center",
      duration: 0.5,
      ease: "power2.out"
    });
  });

  wrapper.addEventListener("mouseleave", () => {
    gsap.to(wrapper, {
      rotationX: 0,
      rotationY: 0,
      duration: 0.6,
      ease: "power3.out"
    });
  });

  wrapper.addEventListener("mouseenter", () => {
    gsap.to(wrapper, {
      duration: 0.3,
      ease: "power1.out"
    });
  });

});