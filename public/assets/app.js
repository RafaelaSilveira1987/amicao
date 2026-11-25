'use strict';
const dialog = document.getElementById('confirm-dialog');
let pending = null;
let approved = null;
for (const form of document.querySelectorAll('form[data-confirm]')) {
  form.addEventListener('submit', event => {
    if (approved === form) { approved = null; return; }
    event.preventDefault();
    const button = event.submitter || form.querySelector('button[value="transfer"]');
    const archive = button.value === 'archive';
    pending = {form, button};
    document.getElementById('confirm-title').textContent = archive ? 'Arquivar cadastro?' : 'Confirmar transferência?';
    document.getElementById('confirm-description').textContent = archive ? 'O cadastro deixará de aparecer nas listas ativas. O histórico será preservado.' : `${button.textContent.trim()}. Todos os dados e imagens serão preservados e a movimentação ficará registrada no histórico.`;
    const accept = document.getElementById('confirm-accept');
    accept.textContent = archive ? 'Arquivar cadastro' : 'Confirmar transferência';
    accept.classList.toggle('danger', archive);
    dialog.querySelector('.dialog-symbol').textContent = archive ? '!' : '↔';
    dialog.showModal();
  });
}
document.getElementById('confirm-cancel').addEventListener('click', () => dialog.close());
dialog.addEventListener('close', () => { if(pending) pending.button.focus(); pending = null; });
document.getElementById('confirm-accept').addEventListener('click', () => {
  if (!pending) return;
  const {form, button} = pending;
  pending = null;
  dialog.close();
  approved = form;
  form.requestSubmit(button);
});
for(const form of document.querySelectorAll('.upload-form')) {
  const input = form.querySelector('input[type=file]');
  const info = form.querySelector('.file-info');
  input.addEventListener('change', () => {
    const file = input.files[0];
    input.setCustomValidity(file && file.size > 5242880 ? 'Selecione uma imagem de até 5 MB.' : '');
    info.textContent = file ? `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB` : 'JPG, PNG ou WEBP · até 5 MB';
    input.reportValidity();
  });
  form.addEventListener('submit', () => { const button = form.querySelector('button'); button.disabled = true; button.textContent = 'Enviando imagem…'; });
}
const gallery=document.getElementById('gallery-dialog');
for(const link of document.querySelectorAll('.gallery-link')) link.addEventListener('click',e=>{if(typeof gallery.showModal!=='function')return;e.preventDefault();gallery.querySelector('img').src=link.href;gallery.showModal();});
document.getElementById('gallery-close').addEventListener('click',()=>gallery.close());
gallery.addEventListener('close',()=>gallery.querySelector('img').removeAttribute('src'));

// Password visibility: only the value typed in this form, never stored credentials.
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
