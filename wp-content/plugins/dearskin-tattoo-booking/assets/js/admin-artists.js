(function(){
  if (typeof window.DSTB_Artists === 'undefined') {
    return;
  }

  const settings = window.DSTB_Artists;
  const ajaxUrl = settings.ajax_url;
  const nonce = settings.nonce;
  const i18n = settings.i18n || {};

  const addBtn = document.getElementById('dstb-add-artist-btn');
  const deleteBtn = document.getElementById('dstb-del-artist-btn');
  const addModal = document.getElementById('dstb-add-artist-modal');
  const deleteModal = document.getElementById('dstb-delete-artist-modal');
  const addForm = addModal ? addModal.querySelector('#dstb-add-artist-form') : null;
  const deleteForm = deleteModal ? deleteModal.querySelector('#dstb-delete-artist-form') : null;
  const addInput = addModal ? addModal.querySelector('#dstb-add-artist-input') : null;
  const addTypeInputs = addModal ? addModal.querySelectorAll('input[name="has_calendar"]') : [];
  const addFeedback = addModal ? addModal.querySelector('.dstb-modal-feedback') : null;
  const deleteFeedback = deleteModal ? deleteModal.querySelector('.dstb-modal-feedback') : null;
  const deleteOptions = deleteModal ? deleteModal.querySelector('#dstb-delete-artist-options') : null;
  const modals = [addModal, deleteModal];
  let openModals = 0;

  function openModal(modal) {
    if (!modal || !modal.hasAttribute('hidden')) {
      return;
    }
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    openModals += 1;
    document.body.classList.add('dstb-modal-open');
    const focusTarget = modal.querySelector('input, button, select, textarea');
    if (focusTarget) {
      focusTarget.focus();
    }
  }

  function closeModal(modal) {
    if (!modal || modal.hasAttribute('hidden')) {
      return;
    }
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');
    openModals = Math.max(0, openModals - 1);
    if (openModals === 0) {
      document.body.classList.remove('dstb-modal-open');
    }
  }

  function closeAllModals() {
    modals.forEach(function(modal){
      closeModal(modal);
    });
  }

  function bindModalEvents(modal) {
    if (!modal) {
      return;
    }
    modal.addEventListener('click', function(event){
      if (event.target === modal || event.target.hasAttribute('data-modal-close')) {
        closeModal(modal);
      }
    });
  }

  modals.forEach(bindModalEvents);

  document.addEventListener('keydown', function(event){
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });

  function showFeedback(el, message, type) {
    if (!el) {
      return;
    }
    el.textContent = message || '';
    el.classList.remove('is-error', 'is-success');
    if (type === 'error') {
      el.classList.add('is-error');
    } else if (type === 'success') {
      el.classList.add('is-success');
    }
  }

  function sendRequest(action, payload) {
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('nonce', nonce);
    Object.keys(payload || {}).forEach(function(key){
      if (payload[key] !== undefined && payload[key] !== null) {
        body.append(key, payload[key]);
      }
    });

    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    }).then(function(response){
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }

      return response.json().catch(function(){
        return response.text().then(function(text){
          throw new Error(text || 'Invalid JSON response');
        });
      });
    });
  }

  function updateSelect(list, options) {
    if (window.DSTBAvailability && typeof window.DSTBAvailability.updateArtists === 'function') {
      return window.DSTBAvailability.updateArtists(list, options || {});
    }
    return null;
  }

  function renderDeleteOptions(items) {
    if (!deleteOptions) {
      return;
    }
    deleteOptions.innerHTML = '';
    if (!items || !items.length) {
      deleteOptions.innerHTML = '<p class="dstb-modal-empty">' + (i18n.empty || 'Keine Artists vorhanden.') + '</p>';
      return;
    }
    items.forEach(function(item, index){
      const label = document.createElement('label');
      label.className = 'dstb-modal-option';

      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'delete_artist';
      input.value = item && item.name ? item.name : '';
      if (index === 0) {
        input.checked = true;
      }

      const span = document.createElement('span');
      span.textContent = item && item.name ? item.name : '';

      const badge = document.createElement('span');
      const hasCalendar = !(item && String(item.has_calendar) === '0');
      badge.className = 'dstb-badge ' + (hasCalendar ? 'dstb-badge--calendar' : 'dstb-badge--no-calendar');
      badge.textContent = hasCalendar ? 'mit Kalender' : 'ohne Kalender';

      label.appendChild(input);
      label.appendChild(span);
      label.appendChild(badge);
      deleteOptions.appendChild(label);
    });
  }

  function loadArtistOptions() {
    showFeedback(deleteFeedback, '', '');
    if (deleteOptions) {
      deleteOptions.innerHTML = '<p class="dstb-modal-empty">' + (i18n.loading || 'Bitte warten…') + '</p>';
    }
    return sendRequest('dstb_get_artists', {}).then(function(response){
      if (!response || !response.success) {
        const message = response && response.data && response.data.msg ? response.data.msg : (i18n.error || 'Es ist ein Fehler aufgetreten.');
        showFeedback(deleteFeedback, message, 'error');
        renderDeleteOptions([]);
        return [];
      }
      renderDeleteOptions(response.data || []);
      return response.data || [];
    }).catch(function(error){
      const message = (error && error.message) ? error.message : (i18n.error || 'Es ist ein Fehler aufgetreten.');
      showFeedback(deleteFeedback, message, 'error');
      renderDeleteOptions([]);
      return [];
    });
  }

  if (addBtn && addModal && addForm) {
    addBtn.addEventListener('click', function(){
      if (addForm) {
        addForm.reset();
      }
      showFeedback(addFeedback, '', '');
      openModal(addModal);
    });

    addForm.addEventListener('submit', function(event){
      event.preventDefault();
      if (!addInput) {
        return;
      }
      const rawName = addInput.value || '';
      const name = rawName.trim();
      if (!name) {
        showFeedback(addFeedback, i18n.invalidName || 'Bitte gib einen Namen ein.', 'error');
        addInput.focus();
        return;
      }

      let hasCalendar = '1';
      if (addTypeInputs && addTypeInputs.length) {
        const checked = Array.prototype.find.call(addTypeInputs, function(input){ return input.checked; });
        if (checked) {
          hasCalendar = checked.value;
        }
      }

      showFeedback(addFeedback, i18n.loading || 'Bitte warten…', '');
      sendRequest('dstb_add_artist', { name: name, has_calendar: hasCalendar }).then(function(response){
        if (!response || !response.success) {
          const message = response && response.data && response.data.msg ? response.data.msg : (i18n.error || 'Es ist ein Fehler aufgetreten.');
          showFeedback(addFeedback, message, 'error');
          return;
        }

        showFeedback(addFeedback, i18n.added || 'Artist hinzugefügt.', 'success');
        closeModal(addModal);
        updateSelect(response.data && response.data.artists ? response.data.artists : [], {
          focus: response.data && response.data.focus ? response.data.focus : name,
          reload: true
        });
      }).catch(function(error){
        const message = (error && error.message) ? error.message : (i18n.error || 'Es ist ein Fehler aufgetreten.');
        showFeedback(addFeedback, message, 'error');
      });
    });
  }

  if (deleteBtn && deleteModal && deleteForm) {
    deleteBtn.addEventListener('click', function(){
      loadArtistOptions().then(function(items){
        if (!items || !items.length) {
          showFeedback(deleteFeedback, i18n.empty || 'Keine Artists vorhanden.', 'error');
        }
        openModal(deleteModal);
      });
    });

    deleteForm.addEventListener('submit', function(event){
      event.preventDefault();
      const selected = deleteForm.querySelector('input[name="delete_artist"]:checked');
      if (!selected) {
        showFeedback(deleteFeedback, i18n.empty || 'Keine Artists vorhanden.', 'error');
        return;
      }
      const name = selected.value.trim();
      if (!name) {
        showFeedback(deleteFeedback, i18n.empty || 'Keine Artists vorhanden.', 'error');
        return;
      }
      const confirmText = i18n.confirmDelete || 'Diesen Artist wirklich löschen?';
      if (!window.confirm(confirmText)) {
        return;
      }

      showFeedback(deleteFeedback, i18n.loading || 'Bitte warten…', '');
      sendRequest('dstb_delete_artist', { name: name }).then(function(response){
        if (!response || !response.success) {
          const message = response && response.data && response.data.msg ? response.data.msg : (i18n.error || 'Es ist ein Fehler aufgetreten.');
          showFeedback(deleteFeedback, message, 'error');
          return;
        }

        showFeedback(deleteFeedback, i18n.deleted || 'Artist gelöscht.', 'success');
        closeModal(deleteModal);
        updateSelect(response.data && response.data.artists ? response.data.artists : [], {
          focus: response.data && response.data.focus ? response.data.focus : '',
          reload: true
        });
      }).catch(function(error){
        const message = (error && error.message) ? error.message : (i18n.error || 'Es ist ein Fehler aufgetreten.');
        showFeedback(deleteFeedback, message, 'error');
      });
    });
  }
})();
