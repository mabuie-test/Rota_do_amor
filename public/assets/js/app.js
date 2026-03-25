document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-swipe-action]').forEach((button) => {
    button.addEventListener('click', () => {
      button.classList.add('btn-success');
    });
  });
});
