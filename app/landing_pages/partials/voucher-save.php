<?php declare(strict_types=1); ?>
<script>
(function () {
    var saveButton  = document.getElementById('saveVoucherBtn');
    var voucherCard = document.getElementById('voucherCard');
    if (!saveButton || !voucherCard || typeof html2canvas === 'undefined') return;

    function dataUrlToFile(dataUrl, filename) {
        var arr = dataUrl.split(','), mime = arr[0].match(/:(.*?);/)[1];
        var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
        while (n--) u8[n] = bstr.charCodeAt(n);
        return new File([u8], filename, { type: mime });
    }

    function openFallback(dataUrl) {
        var w = window.open('', '_blank');
        if (!w) { alert('Allow pop-ups and try again.'); return; }
        w.document.write('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#111;color:#fff;text-align:center;padding:18px}img{max-width:100%;border-radius:14px}</style></head><body><p>Press and hold to save.</p><img src="' + dataUrl + '"></body></html>');
        w.document.close();
    }

    async function save() {
        var orig = saveButton.textContent;
        saveButton.disabled = true;
        saveButton.textContent = 'Preparing...';
        try {
            var canvas = await html2canvas(voucherCard, { backgroundColor: null, scale: Math.max(2, window.devicePixelRatio || 1.5), useCORS: true, allowTaint: false });
            var dataUrl = canvas.toDataURL('image/png');
            var filename = 'elite-smiles-voucher.png';
            if (navigator.canShare && navigator.share) {
                try {
                    var file = dataUrlToFile(dataUrl, filename);
                    if (navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: 'Elite Smiles Voucher' });
                        saveButton.textContent = 'Saved!';
                        setTimeout(() => { saveButton.textContent = orig; saveButton.disabled = false; }, 1200);
                        return;
                    }
                } catch (e) {}
            }
            var link = document.createElement('a');
            link.href = dataUrl; link.download = filename;
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            if (isIOS) openFallback(dataUrl);
            saveButton.textContent = 'Saved!';
        } catch (e) {
            alert('Could not save image. Please try again.');
        }
        setTimeout(() => { saveButton.textContent = orig; saveButton.disabled = false; }, 1200);
    }

    saveButton.addEventListener('click', save);
})();
</script>
