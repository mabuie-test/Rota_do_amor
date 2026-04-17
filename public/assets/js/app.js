document.addEventListener('DOMContentLoaded', () => {
  const qs = new URLSearchParams(window.location.search);
  document.body.classList.add('js-ready');

  const mobileNav = document.getElementById('rdMobileNav');
  const mobileNavToggle = document.querySelector('[data-rd-sidebar-toggle]');
  const mobileNavCloseTriggers = document.querySelectorAll('[data-rd-sidebar-close], #rdMobileNav .rd-sidebar__link');
  let lastFocusedElement = null;

  const closeMobileNav = () => {
    if (!mobileNav || mobileNav.hasAttribute('hidden')) return;
    mobileNav.classList.remove('is-open');
    mobileNav.setAttribute('hidden', 'hidden');
    document.body.classList.remove('rd-mobile-nav-open');
    mobileNavToggle?.setAttribute('aria-expanded', 'false');
    if (lastFocusedElement instanceof HTMLElement) {
      lastFocusedElement.focus();
    }
  };

  const openMobileNav = () => {
    if (!mobileNav) return;
    lastFocusedElement = document.activeElement;
    mobileNav.removeAttribute('hidden');
    requestAnimationFrame(() => mobileNav.classList.add('is-open'));
    document.body.classList.add('rd-mobile-nav-open');
    mobileNavToggle?.setAttribute('aria-expanded', 'true');
    mobileNav.querySelector('.rd-sidebar__link, button, [href]')?.focus();
  };

  mobileNavToggle?.addEventListener('click', () => {
    if (mobileNav?.hasAttribute('hidden')) {
      openMobileNav();
      return;
    }

    closeMobileNav();
  });

  mobileNavCloseTriggers.forEach((trigger) => {
    trigger.addEventListener('click', closeMobileNav);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMobileNav();
    }
  });

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
  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
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

  document.querySelectorAll('[data-enhanced-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
      const submitButton = form.querySelector('button[type="submit"], button:not([type])');
      if (submitButton) {
        submitButton.classList.add('is-submitting');
      }
    });
  });

  document.querySelectorAll('.rd-card').forEach((card) => {
    card.addEventListener('mouseenter', () => card.classList.add('is-hovered'));
    card.addEventListener('mouseleave', () => card.classList.remove('is-hovered'));
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

  document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-reply-toggle]');
    if (!btn) return;
    const postId = btn.getAttribute('data-post-id');
    const parentId = btn.getAttribute('data-parent-id');
    const form = document.getElementById(`reply-form-${postId}-${parentId}`);
    if (!form) return;
    form.classList.toggle('d-none');
    if (!form.classList.contains('d-none')) {
      form.querySelector('input[name="comment"]')?.focus();
    }
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
    const getLinkSource = (link) => {
      const href = (link.getAttribute('href') || '').trim();
      if (!href || href === '#') {
        return '';
      }
      return href;
    };

    const isOpen = (overlay) => overlay.classList.contains('is-open');
    let overlay = document.querySelector('.rd-lightbox');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'rd-lightbox';
      overlay.setAttribute('aria-hidden', 'true');
      overlay.innerHTML = `
        <button class="rd-lightbox__close" type="button" aria-label="Fechar"><i class="fa-solid fa-xmark"></i></button>
        <button class="rd-lightbox__nav rd-lightbox__nav--prev" type="button" aria-label="Anterior"><i class="fa-solid fa-chevron-left"></i></button>
        <img class="rd-lightbox__img" alt="imagem ampliada">
        <button class="rd-lightbox__nav rd-lightbox__nav--next" type="button" aria-label="Seguinte"><i class="fa-solid fa-chevron-right"></i></button>
      `;
      document.body.appendChild(overlay);
    }

    const img = overlay.querySelector('.rd-lightbox__img');
    const closeBtn = overlay.querySelector('.rd-lightbox__close');
    const prevBtn = overlay.querySelector('.rd-lightbox__nav--prev');
    const nextBtn = overlay.querySelector('.rd-lightbox__nav--next');
    let currentGroup = [];
    let currentIndex = 0;

    const render = () => {
      const link = currentGroup[currentIndex];
      const source = link ? getLinkSource(link) : '';
      if (!img || source === '') {
        close();
        return;
      }

      img.src = source;
      prevBtn?.toggleAttribute('hidden', currentGroup.length <= 1);
      nextBtn?.toggleAttribute('hidden', currentGroup.length <= 1);
    };

    function close() {
      overlay.classList.remove('is-open');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('rd-lightbox-open');
      document.querySelectorAll('[data-feed-fab]').forEach((fab) => fab.classList.remove('is-hidden-by-lightbox'));
      if (img) {
        img.removeAttribute('src');
      }
      currentGroup = [];
      currentIndex = 0;
    }

    const open = (link) => {
      const source = getLinkSource(link);
      const groupName = (link.getAttribute('data-lightbox-group') || '').trim();
      if (!source || !groupName) {
        return;
      }

      currentGroup = lightboxLinks.filter((item) => item.getAttribute('data-lightbox-group') === groupName && getLinkSource(item) !== '');
      currentIndex = Math.max(0, currentGroup.indexOf(link));
      render();
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.classList.add('rd-lightbox-open');
      document.querySelectorAll('[data-feed-fab]').forEach((fab) => fab.classList.add('is-hidden-by-lightbox'));
    };

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
        const source = getLinkSource(link);
        if (source === '') return;
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
      if (!isOpen(overlay)) return;
      if (event.key === 'Escape') close();
      if (event.key === 'ArrowRight') next();
      if (event.key === 'ArrowLeft') prev();
    });
  }


  const feedFeedbackForPost = (postId) => document.querySelector(`[data-feed-feedback][data-post-id="${CSS.escape(String(postId))}"]`);
  const feedRoot = document.querySelector('.rd-feed-shell');
  const getCsrfToken = (scope = null) => {
    const scopedToken = scope?.querySelector?.('input[name="_token"]')?.value;
    if (scopedToken) return scopedToken;
    const rootToken = feedRoot?.getAttribute('data-csrf-token') || '';
    if (rootToken) return rootToken;
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  };
  const postAsForm = async (url, payload, token = '') => fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
    body: new URLSearchParams(token ? { ...payload, _token: token } : payload),
  });

  document.querySelectorAll('[data-feed-like-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[data-feed-like-button]');
      const postId = form.querySelector('input[name="post_id"]')?.value || '0';
      const token = getCsrfToken(form);
      const feedback = feedFeedbackForPost(postId);
      if (!button) {
        form.submit();
        return;
      }

      button.disabled = true;
      try {
        const response = await postAsForm('/feed/like', { post_id: String(postId) }, token);
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
      const token = getCsrfToken(form);
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
        const response = await postAsForm('/feed/comment', { post_id: String(postId), parent_comment_id: String(parentCommentId), comment }, token);
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || 'Não foi possível enviar comentário.');
        }

        showInlineFeedback(feedback, payload.message || 'Comentário enviado.', 'success');
        commentInput.value = '';
        const commentsCountNode = document.querySelector(`[data-post-comments-count="${CSS.escape(String(postId))}"]`);
        if (commentsCountNode) {
          commentsCountNode.textContent = String(Number(commentsCountNode.textContent || '0') + 1);
        }

        const targetParentId = Number(parentCommentId || 0);
        const thread = document.querySelector(`[data-comment-thread][data-post-id="${CSS.escape(String(postId))}"]`);
        if (thread) {
          const author = escapeHtml(payload.comment?.author_name || 'Tu');
          const text = escapeHtml(payload.comment?.comment_text || comment);
          const createdId = Number(payload.created_id || 0);
          if (targetParentId > 0) {
            const parentNode = thread.querySelector(`#comment-${CSS.escape(String(targetParentId))}`);
            if (parentNode) {
              let repliesWrap = parentNode.querySelector('.rd-feed-replies');
              if (!repliesWrap) {
                repliesWrap = document.createElement('div');
                repliesWrap.className = 'rd-feed-replies mt-2 d-grid gap-2';
                parentNode.appendChild(repliesWrap);
              }
              const replyNode = document.createElement('div');
              replyNode.className = 'rd-feed-comment-item rd-comment-highlight';
              if (createdId > 0) replyNode.id = `comment-${createdId}`;
              replyNode.innerHTML = `<a href="#" class="fw-semibold text-decoration-none">${author}</a>: ${text}`;
              repliesWrap.appendChild(replyNode);
            }
          } else {
            const node = document.createElement('div');
            node.className = 'rd-feed-comment-item rd-comment-highlight';
            if (createdId > 0) node.id = `comment-${createdId}`;
            node.innerHTML = `
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div><a href="#" class="fw-semibold text-decoration-none">${author}</a>: ${text}</div>
                <button type="button" class="btn btn-link btn-sm p-0" data-reply-toggle data-post-id="${postId}" data-parent-id="${createdId || 0}">Responder</button>
              </div>
            `;
            thread.prepend(node);
          }
        }
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao enviar comentário.', 'error');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });
  });


  document.querySelectorAll('[data-feed-reaction]').forEach((button) => {
    button.addEventListener('click', async () => {
      const postId = button.getAttribute('data-post-id') || '0';
      const reactionType = button.getAttribute('data-reaction-type') || '';
      const token = getCsrfToken();
      const feedback = feedFeedbackForPost(postId);
      button.disabled = true;
      try {
        const response = await postAsForm('/feed/react', { post_id: String(postId), reaction_type: reactionType }, token);
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Falha ao reagir.');
        showInlineFeedback(feedback, payload.message || 'Reação atualizada.', 'success');

        const viewerReaction = String(payload.viewer_reaction || '');
        const reactions = payload.reaction_counts || {};
        const wrapper = button.closest('.rd-feed-reactions');
        if (wrapper) {
          wrapper.querySelectorAll('[data-feed-reaction]').forEach((reactionBtn) => {
            const type = reactionBtn.getAttribute('data-reaction-type') || '';
            const active = viewerReaction !== '' && type === viewerReaction;
            reactionBtn.classList.toggle('btn-primary', active);
            reactionBtn.classList.toggle('btn-outline-secondary', !active);
            const badge = reactionBtn.querySelector('.badge');
            if (badge && Object.prototype.hasOwnProperty.call(reactions, type)) {
              badge.textContent = String(Number(reactions[type] || 0));
            }
          });
        }

        const totalReactions = Object.values(reactions).reduce((acc, value) => acc + Number(value || 0), 0);
        const reactionTotalEl = document.querySelector(`[data-post-reactions-total="${CSS.escape(String(postId))}"]`);
        if (reactionTotalEl) reactionTotalEl.textContent = String(totalReactions);
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao reagir.', 'error');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-feed-poll-vote]').forEach((button) => {
    button.addEventListener('click', async () => {
      const pollId = button.getAttribute('data-poll-id') || '0';
      const optionId = button.getAttribute('data-option-id') || '0';
      const token = getCsrfToken();
      const pollCard = button.closest('[data-poll-id]');
      const postId = pollCard?.getAttribute('data-post-id') || '0';
      const feedback = feedFeedbackForPost(postId);
      button.disabled = true;
      try {
        const response = await postAsForm('/feed/poll/vote', { poll_id: String(pollId), option_id: String(optionId) }, token);
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Falha ao votar.');
        showInlineFeedback(feedback, payload.message || 'Voto registado.', 'success');
        const poll = payload.poll || {};
        if (pollCard) {
          const viewerOptionId = Number(poll.viewer_option_id || 0);
          pollCard.querySelectorAll('[data-feed-poll-vote]').forEach((pollButton) => {
            const thisOptionId = Number(pollButton.getAttribute('data-option-id') || '0');
            const state = (poll.options || []).find((opt) => Number(opt.id || 0) === thisOptionId);
            pollButton.classList.toggle('btn-primary', viewerOptionId === thisOptionId);
            pollButton.classList.toggle('btn-outline-primary', viewerOptionId !== thisOptionId);
            const percentageNode = pollButton.querySelector('[data-poll-option-percentage]');
            if (percentageNode && state) {
              percentageNode.textContent = String(Number(state.percentage || 0));
            }
          });

          const totalVotesNode = pollCard.querySelector('[data-poll-total-votes]');
          if (totalVotesNode) totalVotesNode.textContent = String(Number(poll.total_votes || 0));
        }
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-feed-private-interest-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const postId = form.querySelector('input[name="post_id"]')?.value || '0';
      const type = form.querySelector('select[name="interest_type"]')?.value || '';
      const msg = form.querySelector('input[name="message_optional"]')?.value || '';
      const token = getCsrfToken(form);
      const feedback = feedFeedbackForPost(postId);
      try {
        const response = await postAsForm('/feed/private-interest', { post_id: String(postId), interest_type: type, message_optional: msg }, token);
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Falha ao enviar interesse.');
        showInlineFeedback(feedback, payload.message || 'Interesse enviado.', 'success');
        const countEl = document.querySelector(`[data-post-private-interest-count="${CSS.escape(String(postId))}"]`);
        if (countEl) countEl.textContent = String(Number(countEl.textContent || '0') + 1);
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao enviar interesse.', 'error');
      }
    });
  });

  document.querySelectorAll('[data-feed-availability-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const token = getCsrfToken(form);
      const availabilityType = form.querySelector('select[name="availability_type"]')?.value || '';
      const duration = form.querySelector('select[name="duration_minutes"]')?.value || '180';
      let feedback = form.querySelector('[data-feed-availability-feedback]');
      if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'small mt-2';
        feedback.setAttribute('data-feed-availability-feedback', '1');
        form.appendChild(feedback);
      }
      try {
        const response = await postAsForm('/feed/availability', { availability_type: availabilityType, duration_minutes: duration }, token);
        const payload = await parseJsonSafe(response);
        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || 'Falha ao ativar disponibilidade.');
        }

        showInlineFeedback(feedback, payload.message || 'Estado social ativado.', 'success');
        const badgeText = availabilityType.replaceAll('_', ' ');
        document.querySelectorAll('.rd-feed-post__meta').forEach((metaWrap) => {
          const ownedPostCard = metaWrap.closest('.rd-feed-post');
          if (!ownedPostCard) return;
          const deleteForm = ownedPostCard.querySelector('form[action="/feed/delete"]');
          if (!deleteForm) return;
          let badge = metaWrap.querySelector('.rd-badge-availability');
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge text-bg-info rd-badge-availability';
            metaWrap.appendChild(badge);
          }
          badge.textContent = badgeText;
        });
      } catch (error) {
        showInlineFeedback(feedback, error.message || 'Falha ao ativar disponibilidade.', 'error');
      }
    });
  });

  const safetyLevelSelect = document.querySelector('[data-safe-date-safety-level]');
  if (safetyLevelSelect) {
    const ensureEnabledOption = () => {
      const selectedOption = safetyLevelSelect.options[safetyLevelSelect.selectedIndex];
      if (selectedOption && !selectedOption.disabled) {
        return;
      }

      const firstEnabled = Array.from(safetyLevelSelect.options).find((option) => !option.disabled);
      if (firstEnabled) {
        safetyLevelSelect.value = firstEnabled.value;
      }
    };

    ensureEnabledOption();
    safetyLevelSelect.addEventListener('change', ensureEnabledOption);
  }

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


  const submitButtons = document.querySelectorAll('form button[type="submit"], form button:not([type])');
  submitButtons.forEach((button) => {
    const form = button.closest('form');
    if (!form || form.hasAttribute('data-no-enhance-submit') || form.hasAttribute('data-feed-like-form') || form.hasAttribute('data-feed-comment-form') || form.id === 'chat-form') return;

    button.setAttribute('data-enhanced-submit', '1');
    form.addEventListener('submit', () => {
      if (button.disabled) return;
      button.classList.add('is-submitting');
      button.disabled = true;
      const originalText = button.dataset.originalLabel || button.textContent || '';
      button.dataset.originalLabel = originalText;
      if (!button.querySelector('i')) {
        button.textContent = `${originalText.trim()}...`;
      }
    });
  });

  document.querySelectorAll('.rd-card, .rd-list-item, .rd-soft-panel').forEach((node, index) => {
    node.style.animationDelay = `${Math.min(index * 20, 180)}ms`;
    node.classList.add('fade-in');
  });

});
