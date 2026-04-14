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

  const provinceSelect = document.getElementById('provinceSelect');
  const citySelect = document.getElementById('citySelect');
  if (provinceSelect && citySelect) {
    const cityOptions = Array.from(citySelect.querySelectorAll('option[data-province]'));

    const updateCities = () => {
      const provinceId = provinceSelect.value;
      citySelect.value = '';
      citySelect.disabled = !provinceId;

      cityOptions.forEach((option) => {
        option.hidden = option.dataset.province !== provinceId;
      });
    };

    provinceSelect.addEventListener('change', updateCities);
    updateCities();
  }

  const passwordInput = document.getElementById('registerPassword');
  const meter = document.querySelector('[data-password-meter]');
  if (passwordInput && meter) {
    const meterText = meter.querySelector('.rd-password-meter__text');
    const meterBar = meter.querySelector('.rd-password-meter__bar');

    const evaluatePasswordStrength = (password) => {
      if (!password) {
        return { level: '', label: 'Força da senha: —', progress: 0 };
      }

      let score = 0;
      if (password.length >= 8) score += 1;
      if (password.length >= 12) score += 1;
      if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
      if (/\d/.test(password)) score += 1;
      if (/[^\w\s]/.test(password)) score += 1;

      if (password.length < 8) {
        return { level: 'is-weak', label: 'Força da senha: Fraca', progress: 33 };
      }

      if (score >= 5) {
        return { level: 'is-strong', label: 'Força da senha: Forte', progress: 100 };
      }

      if (score >= 3) {
        return { level: 'is-medium', label: 'Força da senha: Razoável', progress: 66 };
      }

      return { level: 'is-weak', label: 'Força da senha: Fraca', progress: 33 };
    };

    const renderStrength = () => {
      const { level, label, progress } = evaluatePasswordStrength(passwordInput.value);
      meter.classList.remove('is-weak', 'is-medium', 'is-strong');
      if (level) {
        meter.classList.add(level);
      }

      if (meterText) {
        meterText.textContent = label;
      }

      if (meterBar) {
        meterBar.setAttribute('aria-valuenow', String(progress));
      }
    };

    passwordInput.addEventListener('input', renderStrength);
    renderStrength();
  }

  document.querySelectorAll('[data-reply-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const postId = btn.getAttribute('data-post-id');
      const parentId = btn.getAttribute('data-parent-id');
      const form = document.getElementById(`reply-form-${postId}-${parentId}`);
      if (!form) return;
      form.classList.toggle('d-none');
      if (!form.classList.contains('d-none')) {
        form.querySelector('input[name="comment"]')?.focus();
      }
    });
  });

  const selectedPost = document.querySelector('.rd-post-highlight');
  if (selectedPost) {
    selectedPost.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  const lightboxLinks = Array.from(document.querySelectorAll('[data-lightbox-group]'));
  if (lightboxLinks.length > 0) {
    const overlay = document.createElement('div');
    overlay.className = 'rd-lightbox';
    overlay.innerHTML = `
      <button class="rd-lightbox__close" type="button" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
      <button class="rd-lightbox__nav rd-lightbox__nav--prev" type="button" aria-label="Anterior"><i class="fa-solid fa-chevron-left"></i></button>
      <img class="rd-lightbox__img" alt="imagem ampliada">
      <button class="rd-lightbox__nav rd-lightbox__nav--next" type="button" aria-label="Seguinte"><i class="fa-solid fa-chevron-right"></i></button>
    `;
    document.body.appendChild(overlay);

    const img = overlay.querySelector('.rd-lightbox__img');
    const closeBtn = overlay.querySelector('.rd-lightbox__close');
    const prevBtn = overlay.querySelector('.rd-lightbox__nav--prev');
    const nextBtn = overlay.querySelector('.rd-lightbox__nav--next');
    let currentGroup = [];
    let currentIndex = 0;

    const render = () => {
      const link = currentGroup[currentIndex];
      if (!link || !img) return;
      img.src = link.getAttribute('href') || '';
    };

    const open = (link) => {
      const groupName = link.getAttribute('data-lightbox-group');
      currentGroup = lightboxLinks.filter((item) => item.getAttribute('data-lightbox-group') === groupName);
      currentIndex = Math.max(0, currentGroup.indexOf(link));
      render();
      overlay.classList.add('is-open');
    };

    const close = () => overlay.classList.remove('is-open');
    const next = () => {
      if (!currentGroup.length) return;
      currentIndex = (currentIndex + 1) % currentGroup.length;
      render();
    };
    const prev = () => {
      if (!currentGroup.length) return;
      currentIndex = (currentIndex - 1 + currentGroup.length) % currentGroup.length;
      render();
    };

    lightboxLinks.forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        open(link);
      });
    });

    closeBtn?.addEventListener('click', close);
    nextBtn?.addEventListener('click', next);
    prevBtn?.addEventListener('click', prev);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) close();
    });
    document.addEventListener('keydown', (event) => {
      if (!overlay.classList.contains('is-open')) return;
      if (event.key === 'Escape') close();
      if (event.key === 'ArrowRight') next();
      if (event.key === 'ArrowLeft') prev();
    });
  }

  document.querySelectorAll('.rd-profile-actions').forEach((wrapper) => {
    const targetUser = wrapper.getAttribute('data-target-user');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    wrapper.querySelector('[data-action="favorite"]')?.addEventListener('click', async () => {
      const res = await fetch('/favorite/toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
        body: new URLSearchParams({target_user_id: String(targetUser)})
      });
      if (res.ok) window.location.reload();
    });
    wrapper.querySelector('[data-action="block"]')?.addEventListener('click', async () => {
      if (!confirm('Deseja bloquear este membro?')) return;
      await fetch('/block', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
        body: new URLSearchParams({target_user_id: String(targetUser), reason: 'Ação do perfil vivo'})
      });
      window.location.href = '/discover';
    });
    wrapper.querySelector('[data-action="report"]')?.addEventListener('click', async () => {
      await fetch('/report', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
        body: new URLSearchParams({report_type: 'profile', target_user_id: String(targetUser), reason: 'abuse', details: 'Denúncia enviada via perfil público'})
      });
      alert('Denúncia enviada para revisão.');
    });
  });
});
