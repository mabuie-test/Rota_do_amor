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
});
