'use strict';

const confirmDialog = document.getElementById('confirm-dialog');
let pendingConfirm = null;
let approvedForm = null;

if (confirmDialog) {
  for (const form of document.querySelectorAll('form[data-confirm]')) {
    form.addEventListener('submit', event => {
      if (approvedForm === form) {
        approvedForm = null;
        return;
      }
      event.preventDefault();
      const button = event.submitter || form.querySelector('button');
      if (!button) return;
      const isArchive = button.value === 'archive';
      pendingConfirm = { form, button };
      document.getElementById('confirm-title').textContent = isArchive ? 'Arquivar cadastro?' : 'Confirmar transferência?';
      document.getElementById('confirm-description').textContent = isArchive
        ? 'O cadastro deixará de aparecer nas listas ativas. O histórico será preservado.'
        : `${button.textContent.trim()}. Todos os dados e imagens serão preservados e a movimentação ficará registrada no histórico.`;
      const accept = document.getElementById('confirm-accept');
      accept.textContent = isArchive ? 'Arquivar cadastro' : 'Confirmar transferência';
      accept.classList.toggle('danger', isArchive);
      confirmDialog.querySelector('.dialog-symbol').textContent = isArchive ? '!' : '↔';
      confirmDialog.showModal();
    });
  }

  document.getElementById('confirm-cancel')?.addEventListener('click', () => confirmDialog.close());
  document.getElementById('confirm-accept')?.addEventListener('click', () => {
    if (!pendingConfirm) return;
    const { form, button } = pendingConfirm;
    pendingConfirm = null;
    confirmDialog.close();
    approvedForm = form;
    form.requestSubmit(button);
  });
  confirmDialog.addEventListener('close', () => {
    if (pendingConfirm) pendingConfirm.button.focus();
    pendingConfirm = null;
  });
}

for (const form of document.querySelectorAll('.upload-form')) {
  const input = form.querySelector('input[type=file]');
  const info = form.querySelector('.file-info');
  if (!input || !info) continue;
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    input.setCustomValidity(file && file.size > 5242880 ? 'Selecione uma imagem de até 5 MB.' : '');
    info.textContent = file ? `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB` : 'JPG, PNG ou WEBP · até 5 MB';
    input.reportValidity();
  });
  form.addEventListener('submit', () => {
    const button = form.querySelector('button');
    if (!button) return;
    button.disabled = true;
    button.textContent = 'Enviando imagem…';
  });
}

const gallery = document.getElementById('gallery-dialog');
if (gallery) {
  for (const link of document.querySelectorAll('.gallery-link')) {
    link.addEventListener('click', event => {
      if (typeof gallery.showModal !== 'function') return;
      event.preventDefault();
      gallery.querySelector('img').src = link.href;
      gallery.showModal();
    });
  }
  document.getElementById('gallery-close')?.addEventListener('click', () => gallery.close());
  gallery.addEventListener('close', () => gallery.querySelector('img')?.removeAttribute('src'));
}

for (const toggle of document.querySelectorAll('.password-toggle')) {
  const field = document.getElementById(toggle.getAttribute('aria-controls'));
  if (!field) continue;
  toggle.hidden = false;
  toggle.addEventListener('click', () => {
    const visible = field.type === 'password';
    field.type = visible ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(visible));
    toggle.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
  });
}

const toggleUserForm = document.getElementById('toggle-user-form');
const newUserPanel = document.getElementById('new-user-panel');
if (toggleUserForm && newUserPanel) {
  toggleUserForm.addEventListener('click', () => {
    const hidden = newUserPanel.hasAttribute('hidden');
    if (hidden) {
      newUserPanel.removeAttribute('hidden');
      toggleUserForm.setAttribute('aria-expanded', 'true');
      toggleUserForm.textContent = 'Fechar novo usuário';
      newUserPanel.querySelector('input,select,textarea')?.focus();
    } else {
      newUserPanel.setAttribute('hidden', 'hidden');
      toggleUserForm.setAttribute('aria-expanded', 'false');
      toggleUserForm.textContent = '+ Novo usuário';
    }
  });
}

// v1.0.7.3: progress is estimated until the server confirms authentication.
const loginForm = document.getElementById('login-form');
const loginLoader = document.getElementById('login-loader');
if (loginForm && loginLoader && typeof loginLoader.showModal === 'function' && typeof fetch === 'function') {
  const submit = loginForm.querySelector('button:not([type])');
  const errorBox = document.getElementById('login-error');
  const progress = document.getElementById('login-progress');
  const percent = document.getElementById('login-percent');
  const status = document.getElementById('login-loader-status');
  let busy = false;
  let timer;
  const setProgress = n => { progress.value = n; percent.textContent = `${n}%`; };
  loginLoader.addEventListener('cancel', event => event.preventDefault());
  loginForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    busy = true;
    const data = new FormData(loginForm);
    submit.disabled = true;
    errorBox.hidden = true;
    status.textContent = 'Aguarde a confirmação do acesso.';
    setProgress(0);
    loginLoader.showModal();
    const start = performance.now();
    timer = setInterval(() => {
      setProgress(Math.min(95, Math.floor(95 * (1 - Math.exp(-(performance.now() - start) / 1800)))));
    }, 100);
    try {
      const response = await fetch(location.href, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Amicao-Login': '1', 'Accept': 'application/json' }, cache: 'no-store', redirect: 'error' });
      const result = await response.json();
      if (!response.ok || result.ok !== true) throw new Error(typeof result.message === 'string' && result.message ? result.message : 'Não foi possível confirmar o acesso.');
      clearInterval(timer);
      setProgress(100);
      status.textContent = 'Acesso confirmado! Abrindo painel…';
      // Keep the overlay visible during the normal dashboard navigation.
      setTimeout(() => location.assign('?page=dashboard'), 250);
    } catch (error) {
      clearInterval(timer);
      loginLoader.close();
      busy = false;
      submit.disabled = false;
      errorBox.textContent = error instanceof SyntaxError || error instanceof TypeError ? 'Não foi possível confirmar a resposta do servidor. Verifique a conexão e tente novamente.' : error.message;
      errorBox.hidden = false;
      submit.focus();
    }
  });
  window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    clearInterval(timer);
    if (loginLoader.open) loginLoader.close();
    busy = false;
    submit.disabled = false;
  });
}
