document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-dismiss-alert]').forEach((btn) => {
    btn.addEventListener('click', () => btn.closest('.alert')?.remove());
  });

  document.querySelectorAll('[data-swipe-action]').forEach((button) => {
    button.addEventListener('click', () => {
      button.classList.add('btn-rd-primary');
      setTimeout(() => button.classList.remove('btn-rd-primary'), 500);
    });
  });

  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('shadow', window.scrollY > 4);
    });
  }
});
