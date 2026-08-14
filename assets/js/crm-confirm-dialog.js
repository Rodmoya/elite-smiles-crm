(() => {
    'use strict';

    if (window.crmConfirm) return;

    let dialog = null;
    let title = null;
    let message = null;
    let confirmButton = null;
    let cancelButton = null;
    let resolvePending = null;
    let returnFocus = null;

    const styles = `
        .crm-confirm-dialog{width:min(92vw,440px);max-width:440px;border:0;border-radius:20px;padding:0;background:transparent;color:#0f172a;box-shadow:0 24px 70px rgba(15,23,42,.28)}
        .crm-confirm-dialog::backdrop{background:rgba(15,23,42,.58);backdrop-filter:blur(3px)}
        .crm-confirm-card{overflow:hidden;border:1px solid #e2e8f0;border-radius:20px;background:#fff}
        .crm-confirm-content{display:flex;gap:16px;padding:24px}
        .crm-confirm-icon{display:grid;width:44px;height:44px;flex:0 0 44px;place-items:center;border-radius:999px;background:#e0f2fe;color:#0369a1}
        .crm-confirm-icon[data-tone="danger"]{background:#ffe4e6;color:#be123c}
        .crm-confirm-icon svg{width:22px;height:22px}
        .crm-confirm-copy{min-width:0;flex:1}
        .crm-confirm-title{margin:1px 0 0;font-size:18px;line-height:1.35;font-weight:700;color:#0f172a}
        .crm-confirm-message{max-height:34vh;overflow:auto;margin:8px 0 0;white-space:pre-line;font-size:14px;line-height:1.55;color:#475569}
        .crm-confirm-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;border-top:1px solid #e2e8f0;padding:16px 24px 20px}
        .crm-confirm-button{min-height:46px;border-radius:12px;padding:0 16px;font:600 14px/1 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;cursor:pointer;transition:background-color .18s ease,border-color .18s ease,color .18s ease,box-shadow .18s ease}
        .crm-confirm-button:focus-visible{outline:3px solid rgba(37,99,235,.32);outline-offset:2px}
        .crm-confirm-cancel{border:1px solid #cbd5e1;background:#fff;color:#334155}
        .crm-confirm-cancel:hover{background:#f8fafc;border-color:#94a3b8}
        .crm-confirm-accept{border:1px solid #047857;background:#047857;color:#fff}
        .crm-confirm-accept:hover{border-color:#065f46;background:#065f46}
        .crm-confirm-accept[data-tone="danger"]{border-color:#be123c;background:#be123c}
        .crm-confirm-accept[data-tone="danger"]:hover{border-color:#9f1239;background:#9f1239}
        @media(max-width:480px){.crm-confirm-content{padding:20px}.crm-confirm-actions{padding:14px 20px 18px}.crm-confirm-message{max-height:42vh}}
        @media(prefers-reduced-motion:no-preference){.crm-confirm-dialog[open]{animation:crm-confirm-enter .2s ease-out both}.crm-confirm-dialog[open]::backdrop{animation:crm-confirm-backdrop .18s ease-out both}@keyframes crm-confirm-enter{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}@keyframes crm-confirm-backdrop{from{opacity:0}to{opacity:1}}}
    `;

    const inferActionLabel = text => {
        const normalized = String(text || '').toLowerCase();
        if (/\bdelete\b/.test(normalized)) return 'Delete';
        if (/\bclear\b/.test(normalized)) return 'Clear';
        if (/\bsend\b|\bmailing\b/.test(normalized)) return 'Send now';
        if (/\bdeactivate\b/.test(normalized)) return 'Deactivate';
        if (/\bactivate\b/.test(normalized)) return 'Activate';
        if (/\bimport\b/.test(normalized)) return 'Import leads';
        if (/\bpublish\b|\bpost\b/.test(normalized)) return 'Post now';
        return 'Continue';
    };

    const ensureDialog = () => {
        if (dialog) return;

        const style = document.createElement('style');
        style.id = 'crm-confirm-dialog-styles';
        style.textContent = styles;
        document.head.appendChild(style);

        dialog = document.createElement('dialog');
        dialog.id = 'crm-confirm-dialog';
        dialog.className = 'crm-confirm-dialog';
        dialog.setAttribute('aria-labelledby', 'crm-confirm-title');
        dialog.setAttribute('aria-describedby', 'crm-confirm-message');
        dialog.innerHTML = `
            <section class="crm-confirm-card">
                <div class="crm-confirm-content">
                    <span class="crm-confirm-icon" data-crm-confirm-icon aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/><circle cx="12" cy="12" r="9"/></svg>
                    </span>
                    <div class="crm-confirm-copy">
                        <h2 id="crm-confirm-title" class="crm-confirm-title"></h2>
                        <p id="crm-confirm-message" class="crm-confirm-message"></p>
                    </div>
                </div>
                <div class="crm-confirm-actions">
                    <button type="button" class="crm-confirm-button crm-confirm-cancel" data-crm-confirm-cancel>Cancel</button>
                    <button type="button" class="crm-confirm-button crm-confirm-accept" data-crm-confirm-accept>Continue</button>
                </div>
            </section>`;
        document.body.appendChild(dialog);

        title = dialog.querySelector('#crm-confirm-title');
        message = dialog.querySelector('#crm-confirm-message');
        confirmButton = dialog.querySelector('[data-crm-confirm-accept]');
        cancelButton = dialog.querySelector('[data-crm-confirm-cancel]');

        cancelButton.addEventListener('click', () => finish(false));
        confirmButton.addEventListener('click', () => finish(true));
        dialog.addEventListener('cancel', event => {
            event.preventDefault();
            finish(false);
        });
        dialog.addEventListener('click', event => {
            if (event.target === dialog) finish(false);
        });
    };

    const finish = accepted => {
        if (!resolvePending) return;
        const resolve = resolvePending;
        resolvePending = null;
        dialog.close();
        resolve(accepted);
        if (returnFocus && document.contains(returnFocus)) returnFocus.focus({ preventScroll: true });
        returnFocus = null;
    };

    window.crmConfirm = (prompt, options = {}) => {
        ensureDialog();
        if (resolvePending) finish(false);

        const text = String(prompt || 'Continue with this action?').trim();
        const danger = options.tone === 'danger' || (/\b(delete|clear|permanent|cannot be undone|remove)\b/i).test(text);
        const icon = dialog.querySelector('[data-crm-confirm-icon]');
        title.textContent = options.title || (danger ? 'Please confirm this action' : 'Ready to continue?');
        message.textContent = text;
        confirmButton.textContent = options.confirmLabel || inferActionLabel(text);
        icon.dataset.tone = danger ? 'danger' : 'default';
        confirmButton.dataset.tone = danger ? 'danger' : 'default';
        returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        dialog.showModal();
        window.requestAnimationFrame(() => cancelButton.focus());
        return new Promise(resolve => { resolvePending = resolve; });
    };

    document.addEventListener('submit', event => {
        const form = event.target instanceof HTMLFormElement ? event.target.closest('form[data-crm-confirm]') : null;
        if (!form) return;
        if (form.dataset.crmConfirmBypass === '1') {
            delete form.dataset.crmConfirmBypass;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const prompt = form.dataset.crmConfirm || 'Continue with this action?';
        const options = {
            title: form.dataset.crmConfirmTitle || '',
            confirmLabel: form.dataset.crmConfirmLabel || '',
            tone: form.dataset.crmConfirmTone || '',
        };

        window.crmConfirm(prompt, options).then(accepted => {
            if (!accepted) return;
            form.dataset.crmConfirmBypass = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined);
            else form.submit();
        });
    }, true);
})();
