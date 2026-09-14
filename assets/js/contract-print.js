/* Print only the agreement, never the surrounding CRM layout. */
(() => {
    let copy;
    const style = document.createElement('style');
    style.textContent = `
      .contract-print-copy { display:none; }
      #contract-preview .contract-paper-body, #agreement-document .paper-body,
      #contract-preview .contract-legal-copy, #agreement-document .agreement-legal-copy { font-size:12pt; line-height:1.2; }
      #contract-preview .contract-payment-notice, #agreement-document .agreement-payment-notice { font-size:11pt; white-space:normal; }
      .contract-reorder { display:inline-flex; gap:4px; margin-right:6px; }
      .contract-reorder button { padding:2px 6px; border:1px solid #cbd5e1; border-radius:4px; font-size:12px; }
      @media print {
        @page { size:letter; margin:.35in 0; }
        body > :not(.contract-print-copy) { display:none !important; }
        html, body { margin:0 !important; padding:0 !important; height:auto !important; overflow:visible !important; }
        body > .contract-print-copy { display:block !important; }
        #contract-preview.contract-print-copy, #agreement-document.contract-print-copy {
          position:static !important; inset:auto !important; width:8.5in !important;
          height:auto !important; min-height:0 !important; max-height:none !important;
          overflow:visible !important; aspect-ratio:auto !important; margin:0 !important;
        }
        #contract-preview.contract-print-copy .contract-paper-body, #agreement-document.contract-print-copy .paper-body {
          display:block !important; font-size:12pt !important; line-height:1.2 !important;
        }
        #contract-preview.contract-print-copy.preprinted .contract-paper-body,
        #agreement-document.contract-print-copy.preprinted .paper-body { padding-top:1.3in !important; }
        #contract-preview.contract-print-copy .contract-legal-copy, #agreement-document.contract-print-copy .agreement-legal-copy { font-size:12pt !important; line-height:1.2 !important; }
        #contract-preview.contract-print-copy .contract-payment-notice, #agreement-document.contract-print-copy .agreement-payment-notice { font-size:11pt !important; white-space:normal !important; }
        .contract-print-copy .contract-cancellation-bottom, .contract-print-copy .contract-cancellation-bottom p,
        .contract-print-copy .agreement-cancellation-bottom, .contract-print-copy .agreement-cancellation-bottom p { font-size:11pt !important; line-height:1.15 !important; }
        .contract-print-copy .contract-reorder { display:none !important; }
        .contract-print-copy li, .contract-print-copy .contract-signature-original,
        .contract-print-copy .agreement-signature-original { break-inside:avoid; }
        .contract-print-copy .contract-closing-block, .contract-print-copy .agreement-closing-block { break-inside:avoid; }
      }`;
    document.head.append(style);
    const source = document.querySelector('#contract-preview, #agreement-document');
    if (source) {
        const notice = document.createElement('p');
        notice.setAttribute('role', 'status');
        notice.style.cssText = 'margin:8px 0;color:#92400e;font-size:13px';
        notice.textContent = 'Print uses readable type. Long agreements continue onto another page; no contract wording is cut off. Check the page count in Print Preview.';
        source.before(notice);
    }
    window.addEventListener('beforeprint', () => {
        copy?.remove();
        const source = document.querySelector('#contract-preview, #agreement-document');
        if (!source) return;
        copy = source.cloneNode(true);
        copy.classList.add('contract-print-copy');
        copy.querySelectorAll('.contract-reorder').forEach(el => el.remove());
        document.body.append(copy);
    });
    window.addEventListener('afterprint', () => { copy?.remove(); copy = null; });
})();
