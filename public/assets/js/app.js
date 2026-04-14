document.addEventListener('DOMContentLoaded', () => {
  const qs = new URLSearchParams(window.location.search);

  const showInlineFeedback = (container, message, kind = 'info') => {
    if (!container) return;
    container.classList.remove('d-none', 'text-success', 'text-danger', 'text-muted');
    container.classList.add(kind === 'error' ? 'text-danger' : kind === 'success' ? 'text-success' : 'text-muted');
    container.textContent = message;
  };

  const parseJsonSafe = async (response) => {
    try {
      return await response.json();
    } catch (_error) {
      return {};
    }
  };

  const scrollToElement = (selector) => {
    const element = document.querySelector(selector);
    if (!element) return;
    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

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

  if (qs.get('comment')) {
    scrollToElement(`#comment-${CSS.escape(qs.get('comment'))}`);
  } else if (qs.get('story')) {
    scrollToElement(`#story-${CSS.escape(qs.get('story'))}`);
  } else if (qs.get('duel')) {
    scrollToElement(`#duel-${CSS.escape(qs.get('duel'))}`);
  } else {
    const selectedPost = document.querySelector('.rd-post-highlight');
    if (selectedPost) {
      selectedPost.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
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


  const feedFeedbackForPost = (postId) => document.querySelector(`[data-feed-feedback][data-post-id="${CSS.escape(String(postId))}"]`);

  document.querySelectorAll('[data-feed-like-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[data-feed-like-button]');
      const postId = form.querySelector('input[name="post_id"]')?.value || '0';
      const token = form.querySelector('input[name="_token"]')?.value || '';
      const feedback = feedFeedbackForPost(postId);
      if (!button) {
        form.submit();
        return;
      }

      button.disabled = true;
      try {
        const response = await fetch('/feed/like', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
          body: new URLSearchParams({post_id: String(postId), _token: token})
        });
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || 'Não foi possível atualizar like.');
        }

        const liked = Number(payload.state?.liked_by_viewer ?? payload.liked_by_viewer ?? 0) === 1;
        const likesCount = Number(payload.state?.likes_count ?? payload.likes_count ?? 0);

        button.dataset.liked = liked ? '1' : '0';
        button.classList.toggle('btn-danger', liked);
        button.classList.toggle('btn-outline-danger', !liked);
        button.innerHTML = `<i class="fa-solid fa-heart me-1"></i>${liked ? 'Gostado' : 'Gostar'}`;
        showInlineFeedback(feedback, payload.message || (liked ? 'Like registado.' : 'Like removido.'), 'success');

        const countEl = document.querySelector(`[data-post-like-count="${CSS.escape(String(postId))}"]`);
        if (countEl) countEl.textContent = String(likesCount);
      } catch (error) {
        button.classList.add('btn-outline-danger');
        button.setAttribute('title', error.message || 'Falha ao atualizar like.');
        showInlineFeedback(feedback, error.message || 'Falha ao atualizar like.', 'error');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-feed-comment-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const postId = form.querySelector('input[name="post_id"]')?.value || form.getAttribute('data-post-id') || '0';
      const parentCommentId = form.querySelector('input[name="parent_comment_id"]')?.value || '0';
      const commentInput = form.querySelector('input[name="comment"]');
      const token = form.querySelector('input[name="_token"]')?.value || '';
      const feedback = feedFeedbackForPost(postId);
      const submitButton = form.querySelector('button[type="submit"], button:not([type])');

      if (!commentInput || token === '') {
        form.submit();
        return;
      }

      const comment = commentInput.value.trim();
      if (comment === '') {
        showInlineFeedback(feedback, 'Escreva um comentário antes de enviar.', 'error');
        return;
      }

      if (submitButton) {
        submitButton.disabled = true;
      }

      try {
        const response = await fetch('/feed/comment', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body: new URLSearchParams({ post_id: String(postId), parent_comment_id: String(parentCommentId), comment, _token: token })
        });
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || 'Não foi possível enviar comentário.');
        }

        showInlineFeedback(feedback, payload.message || 'Comentário enviado.', 'success');
        const createdCommentId = Number(payload.created_id || 0);
        const destinationPostId = Number(payload.post_id || payload.target_id || postId || 0);
        const destination = new URL('/feed', window.location.origin);
        destination.searchParams.set('post', String(destinationPostId));
        if (createdCommentId > 0) {
          destination.searchParams.set('comment', String(createdCommentId));
        }
        destination.hash = `post-${destinationPostId}`;
        setTimeout(() => { window.location.assign(destination.toString()); }, 220);
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao enviar comentário.', 'error');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });
  });

  document.querySelectorAll('.duel-action-btn').forEach((button) => {
    button.addEventListener('click', async () => {
      button.disabled = true;
      const token = button.getAttribute('data-csrf-token') || '';

      try {
        const response = await fetch('/compatibility-duel/action', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body: new URLSearchParams({ _token: token, duel_id: button.dataset.duelId || '0', action_type: button.dataset.action || '' })
        });
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || 'Não foi possível concluir esta ação.');
        }

        button.classList.remove('btn-outline-primary', 'btn-outline-danger');
        button.classList.add('btn-success');
        button.setAttribute('title', payload.message || 'Ação concluída');
      } catch (error) {
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-danger');
        button.setAttribute('title', error.message || 'Falha ao concluir ação');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.rd-profile-actions').forEach((wrapper) => {
    const targetUser = wrapper.getAttribute('data-target-user');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const feedback = wrapper.parentElement?.querySelector('.rd-profile-feedback');
    const favoriteBtn = wrapper.querySelector('[data-action="favorite"]');
    const favoriteLabel = wrapper.querySelector('[data-favorite-label]');
    let isFavorited = wrapper.getAttribute('data-is-favorited') === '1';

    const renderFavoriteState = () => {
      if (!favoriteBtn || !favoriteLabel) return;
      favoriteBtn.classList.toggle('btn-rd-soft', !isFavorited);
      favoriteBtn.classList.toggle('btn-warning', isFavorited);
      favoriteLabel.textContent = isFavorited ? 'Favoritado' : 'Favoritar';
    };
    renderFavoriteState();

    favoriteBtn?.addEventListener('click', async () => {
      try {
        const res = await fetch('/favorite/toggle', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
          body: new URLSearchParams({target_user_id: String(targetUser)})
        });
        const data = await parseJsonSafe(res);
        if (!res.ok || !data.ok) {
          throw new Error(data.message || 'Falha ao atualizar favorito.');
        }

        const favoriteState = data.state && typeof data.state.active !== 'undefined' ? data.state.active : data.active;
        isFavorited = favoriteState === true;
        renderFavoriteState();
        showInlineFeedback(feedback, data.message || (isFavorited ? 'Perfil adicionado aos favoritos.' : 'Favorito removido.'), 'success');
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Não foi possível atualizar favorito.', 'error');
      }
    });

    wrapper.querySelector('[data-action="block"]')?.addEventListener('click', async () => {
      if (!window.confirm('Deseja bloquear este membro?')) return;
      try {
        const res = await fetch('/block', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
          body: new URLSearchParams({target_user_id: String(targetUser), reason: 'Ação do perfil vivo'})
        });
        const data = await parseJsonSafe(res);
        if (!res.ok || !data.ok || Number(data.block_id || 0) <= 0) {
          throw new Error(data.message || 'Não foi possível bloquear o membro.');
        }

        showInlineFeedback(feedback, 'Membro bloqueado com sucesso. A redirecionar...', 'success');
        setTimeout(() => { window.location.href = '/discover'; }, 450);
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao bloquear membro.', 'error');
      }
    });

    wrapper.querySelector('[data-action="report"]')?.addEventListener('click', async () => {
      try {
        const res = await fetch('/report', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
          body: new URLSearchParams({report_type: 'profile', target_user_id: String(targetUser), reason: 'abuse', details: 'Denúncia enviada via perfil público'})
        });
        const data = await parseJsonSafe(res);
        if (!res.ok || !data.ok || Number(data.created_id || data.report_id || 0) <= 0) {
          throw new Error(data.message || 'Não foi possível enviar denúncia.');
        }

        showInlineFeedback(feedback, data.message || 'Denúncia enviada para revisão.', 'success');
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao enviar denúncia.', 'error');
      }
    });
  });
});
