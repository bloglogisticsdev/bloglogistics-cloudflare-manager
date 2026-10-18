/* global BlogLogisticsCFM */
(() => {
  'use strict';
  const root = document.getElementById('blcfm');
  if (!root) return;
  const config = BlogLogisticsCFM;
  const forms = [...root.querySelectorAll('form[data-operation]')];
  const byId = (id) => document.getElementById(`blcfm-${id}`);
  let saved = root.dataset.saved === '1';
  let connected = false;
  let busy = false;
  let development = null;
  let checkedAt = 0;
  let connectionUncertain = false;

  function developmentExpired() {
    return development && development.value === 'on' && development.remaining !== null &&
      Math.floor((Date.now() - checkedAt) / 1000) >= development.remaining;
  }

  function refreshButtons() {
    root.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = busy || (button.hasAttribute('data-needs-saved') && !saved && !connectionUncertain) ||
        (button.hasAttribute('data-needs-connection') && !connected) ||
        (button.hasAttribute('data-needs-development') && (!connected || !development || !development.editable || developmentExpired()));
    });
  }

  function showResult(form, message, kind = 'success') {
    const result = form.querySelector('.blcfm-result');
    result.className = `blcfm-result blcfm-result-${kind}`;
    result.textContent = message;
  }

  function showConnection(ok, message) {
    connected = ok;
    const status = byId('status');
    status.className = `blcfm-status blcfm-status-${ok ? 'success' : 'error'}`;
    status.textContent = message;
    refreshButtons();
  }

  function clearDevelopment() {
    development = null;
    byId('development-set').elements.value.value = '';
    byId('development-set').querySelector('button').textContent = config.unknown;
    byId('development-status').textContent = config.unknown;
    refreshButtons();
  }

  function paintDevelopment() {
    if (!development) return;
    const elapsed = Math.floor((Date.now() - checkedAt) / 1000);
    const remaining = development.value === 'off' ? 0 : development.remaining === null ? null : Math.max(0, development.remaining - elapsed);
    let message = `${config.mode} ${development.value}; `;
    message += remaining === null ? config.unavailable : `${remaining} ${config.seconds}.`;
    if (development.value === 'on') message += ` ${remaining === 0 ? config.expired : config.estimate}`;
    byId('development-status').textContent = message;
    byId('development-set').elements.value.value = development.value === 'on' ? 'off' : 'on';
    byId('development-set').querySelector('button').textContent = developmentExpired() ? config.unknown : development.value === 'on' ? config.off : config.on;
    refreshButtons();
  }

  function applyData(data) {
    if (typeof data.connected === 'boolean') {
      connectionUncertain = false;
      if (!data.connected) {
        saved = false;
        clearDevelopment();
        // Keep the result visible in its action area after disconnecting.
        byId('zone-details').hidden = true;
        if (config.disconnectedDomainSummary) byId('zone-summary').textContent = config.disconnectedDomainSummary;
      } else {
        saved = true;
        byId('zone-details').hidden = false;
      }
      showConnection(data.connected, data.message);
    }
    if (data.details) {
      const detail = data.details;
      byId('zone-summary').textContent = `Cloudflare domain: ${detail.zone}. WordPress site host: ${detail.host}.`;
      ['id', 'status'].forEach((key) => { byId(`zone-${key}`).textContent = detail[key] || '…'; });
      byId('token-suggestion').textContent = `${detail.zone} - BlogLogistics Cloudflare Manager`;
    }
    if (data.lastPurge) byId('last-purge').textContent = data.lastPurge;
    if (data.development) {
      development = data.development;
      checkedAt = Date.now();
      paintDevelopment();
    }
    if (data.inspection) {
      const inspection = byId('inspection');
      const pre = document.createElement('pre');
      pre.textContent = `${data.inspection.url}\nHTTP ${data.inspection.http}\n\n` +
        Object.entries(data.inspection.headers).map(([key, value]) => `${key}: ${value || '(not present)'}`).join('\n');
      inspection.replaceChildren(pre);
      inspection.hidden = false;
    }
  }

  async function run(form, automatic = false) {
    if (busy) return false;
    const operation = form.dataset.operation;
    busy = true;
    refreshButtons();
    form.setAttribute('aria-busy', 'true');
    if (operation === 'check_url') byId('inspection').hidden = true;
    showResult(form, config.working, 'working');
    let timeout;
    try {
      const body = new URLSearchParams(new FormData(form));
      body.set('action', 'bloglogistics_cfm');
      body.set('operation', operation);
      body.set('nonce', config.nonce);
      const controller = new AbortController();
      timeout = setTimeout(() => controller.abort(), 180000);
      const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body, signal: controller.signal });
      const json = await response.json();
      if (!json || typeof json.success !== 'boolean' || !json.data || typeof json.data.message !== 'string') throw new Error(config.networkError);
      if (!json.success) {
        const error = new Error(json.data.message);
        error.fromAPI = true;
        throw error;
      }
      applyData(json.data);
      showResult(form, json.data.message);
      if (operation === 'purge_all' || operation === 'disconnect') form.elements.confirm.checked = false;
      if (operation === 'connect') {
        clearDevelopment();
        byId('disconnect').hidden = false;
        byId('disconnect').open = false;
        byId('disconnect').querySelector('.blcfm-result').textContent = '';
        byId('disconnect').querySelector('[name="confirm"]').checked = false;
      }
      if (!automatic) form.querySelector('.blcfm-result').focus({ preventScroll: true });
      return true;
    } catch (error) {
      const message = error && error.fromAPI ? error.message : config.networkError;
      showResult(form, message, 'error');
      if (operation === 'connect' && !(error && error.fromAPI)) {
        connectionUncertain = true;
        byId('disconnect').hidden = false;
      }
      if (operation === 'status') {
        showConnection(false, message);
        clearDevelopment();
      }
      if (operation.startsWith('development_')) clearDevelopment();
      if (!automatic) form.querySelector('.blcfm-result').focus({ preventScroll: true });
      return false;
    } finally {
      clearTimeout(timeout);
      if (operation === 'connect') form.elements.token.value = '';
      if (operation === 'purge_all' || operation === 'disconnect') form.elements.confirm.checked = false;
      busy = false;
      form.removeAttribute('aria-busy');
      refreshButtons();
    }
  }

  forms.forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const ok = await run(form);
      if (ok && ['connect', 'status'].includes(form.dataset.operation)) await run(byId('development-get'), true);
    });
  });
  byId('disconnect').addEventListener('toggle', () => {
    if (!byId('disconnect').open) byId('disconnect').querySelector('[name="confirm"]').checked = false;
  });
  refreshButtons();
  setInterval(paintDevelopment, 1000);
  if (saved) {
    run(byId('status-form'), true).then((ok) => { if (ok) return run(byId('development-get'), true); });
  }
})();
