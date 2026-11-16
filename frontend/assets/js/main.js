document.addEventListener("DOMContentLoaded", () => {
  if (window.Auth) Auth.mountHeader();
  if (window.Shop && document.querySelector('[data-cart-count]')) Shop.updateCartCount();
});
