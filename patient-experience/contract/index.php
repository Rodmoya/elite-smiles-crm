<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/core/helpers.php';
require_once __DIR__ . '/../../app/core/db.php';
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/patient_experience/patient_experience_service.php';

$token = trim((string)get('t', ''));
$error = '';
$success = get('signed') === '1' ? 'Your treatment agreement was signed successfully.' : '';

if (is_post() && post('action') === 'sign_contract') {
    require_csrf();
    $token = trim((string)post('token', ''));
    $result = patient_experience_contract_sign($token, $_POST);
    if (!empty($result['ok'])) {
        redirect(base_url('patient-experience/contract/?t=' . rawurlencode($token) . '&signed=1'));
    }
    $error = (string)($result['message'] ?? 'The signature could not be saved.');
}

$contract = patient_experience_contract_from_token($token, true);
if (!$contract) {
    http_response_code(404);
}
$snapshot = (array)($contract['snapshot'] ?? []);
$agreement = (array)($snapshot['contract'] ?? []);
$financials = (array)($snapshot['financials'] ?? []);
$terms = (array)($snapshot['terms'] ?? []);
$sedationBody = preg_replace('/^Optional\s*-?\s*/i', '', (string)($terms['sedation'] ?? '')) ?? (string)($terms['sedation'] ?? '');
$practice = (array)($snapshot['practice'] ?? []);
$signature = (array)($contract['signature'] ?? []);
$money = static fn(mixed $amount): string => '$' . number_format((float)$amount, 2);
$legacyArchLabel = match ((string)($agreement['arch_scope'] ?? '')) {
    'upper' => 'Upper arch', 'lower' => 'Lower arch', 'both' => 'Both arches', default => '',
};
$legacyTeeth = array_map('intval', (array)($agreement['selected_teeth'] ?? []));
$legacyAreaLabel = $legacyArchLabel !== '' ? $legacyArchLabel : ($legacyTeeth ? 'Teeth ' . patient_experience_contract_format_teeth($legacyTeeth) : '');
$lineItemAreaLabel = static function (array $item) use ($legacyAreaLabel): string {
    $teeth = patient_experience_contract_normalize_teeth($item['teeth'] ?? []);
    if ($teeth) return 'Teeth ' . patient_experience_contract_format_teeth($teeth);
    $arch = (string)($item['arch_scope'] ?? '');
    if ($arch === 'both') return 'Both arches';
    if (in_array($arch, ['upper', 'lower'], true)) return ucfirst($arch) . ' arch';
    return $legacyAreaLabel;
};
$hasOriginalTerms = isset($terms['cashier_check']);
$financialLanguage = (string)($agreement['patient_name'] ?? '') . ', Your estimated out of pocket portion of your Dental Treatment cost will be ' . $money($financials['final_price'] ?? 0) . ' after a professional discount is applied. A deposit of ' . $money($financials['deposit_amount'] ?? 0) . ' will be made prior to your appointment. ';
if ((float)($financials['insurance_estimate'] ?? 0) > 0) $financialLanguage .= 'Your insurance estimated payment is ' . $money($financials['insurance_estimate']) . '. ';
$financialLanguage .= 'Your remaining balance of ' . $money($financials['remaining_balance'] ?? 0) . ' is due the day of your procedure. The Payment would be in a form of a cashier’s check made to Walter Meden DDS.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Elite Smiles | Secure Treatment Agreement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .agreement-page { display:flex; width:min(100%, 8.5in); min-height:11in; flex-direction:column; font-family:Calibri, Arial, sans-serif; color:#111827; }
        .paper-body { display:flex; min-height:0; flex:1; flex-direction:column; }
        .agreement-treatment-list { margin:0 0 16pt; padding-left:.42in; }
        .agreement-treatment-list li { margin:0 0 3pt; padding-left:.04in; line-height:1.08; }
        .agreement-treatment-list li:last-child { margin-bottom:0; }
        .agreement-original-copy > p, .agreement-legal-copy > p { margin:0 0 8pt; }
        .agreement-closing-block { margin-top:auto; margin-bottom:5px; }
        .agreement-signature-original { display:grid; grid-template-columns:minmax(0,1fr) 1.65in; gap:.25in; align-items:start; margin:8pt 0 20px; }
        .agreement-signature-primary { display:grid; grid-template-columns:auto minmax(0,1fr); column-gap:.08in; align-items:end; }
        .agreement-signature-patient { grid-column:2; margin-top:2px; font-size:9pt; line-height:1.1; }
        .agreement-signature-field { display:flex; align-items:flex-end; gap:.08in; }
        .agreement-signature-field > span:first-child { white-space:nowrap; }
        .agreement-signature-rule { display:flex; min-width:0; min-height:.3in; flex:1; align-items:flex-end; border-bottom:1px solid #111827; }
        .agreement-signature-rule img { max-width:100%; max-height:.42in; object-fit:contain; object-position:left bottom; }
        .agreement-cancellation-bottom { margin:0; }
        .agreement-payment-notice { margin-bottom:16pt; background:#fef3c7; border:1px solid #fcd34d; white-space:nowrap; font-size:10.5px; line-height:1.25; }
        .agreement-sedation { color:#b91c1c; }
        .agreement-page.preprinted .digital-letterhead,
        .agreement-page.preprinted .digital-footer { display:none; }
        .agreement-page.preprinted .paper-body { padding-top:1.65in; }
        #signature-canvas { touch-action:none; }
        @media print {
            @page { size:letter; margin:0; }
            body { background:#fff !important; }
            body * { visibility:hidden !important; }
            #agreement-document, #agreement-document * { visibility:visible !important; }
            #agreement-document { position:absolute; inset:0; box-sizing:border-box; width:8.5in; height:11in; min-height:11in; overflow:hidden; border:0; box-shadow:none; }
            #agreement-document .digital-letterhead { padding-top:0.13in !important; padding-bottom:0.10in !important; }
            #agreement-document .digital-letterhead img { width:1.20in !important; }
            #agreement-document .paper-body { padding-right:1in !important; padding-bottom:0.58in !important; padding-left:1in !important; font-size:11pt !important; line-height:1.08 !important; }
            #agreement-document:not(.preprinted) .paper-body { padding-top:0.35in !important; }
            #agreement-document.preprinted .paper-body { padding-top:1.65in !important; }
            #agreement-document .agreement-treatment-list li, #agreement-document .agreement-signature { break-inside:avoid; page-break-inside:avoid; }
            #agreement-document .agreement-legal-copy { font-size:11pt !important; line-height:1.08 !important; }
            #agreement-document .agreement-payment-notice { white-space:nowrap !important; font-size:10.5px !important; line-height:1.25 !important; }
            .no-print { display:none !important; }
        }
        @media screen and (max-width:700px) {
            body { background:#f1f5f9; }
            .agreement-page { min-height:0; aspect-ratio:auto; overflow:visible; border-radius:18px; }
            .agreement-page .digital-letterhead { padding:20px 16px; }
            .agreement-page .digital-letterhead img { width:132px; }
            .agreement-page .paper-body,
            .agreement-page.preprinted .paper-body { padding:24px 20px 30px; font-size:15px; line-height:1.45; }
            .agreement-original-copy > p,
            .agreement-legal-copy > p { margin-bottom:14px; }
            .agreement-payment-notice { margin-bottom:20px; padding:10px; white-space:normal; font-size:12px; line-height:1.35; }
            .agreement-treatment-list { margin-bottom:22px; padding-left:24px; }
            .agreement-treatment-list li { margin-bottom:7px; line-height:1.35; }
            .agreement-legal-copy { font-size:15px; line-height:1.45; }
            .agreement-closing-block { margin-top:28px; margin-bottom:0; }
            .agreement-signature-original { grid-template-columns:1fr; gap:18px; margin:20px 0 24px; }
            .agreement-signature-primary { display:block; }
            .agreement-signature-primary > span:first-child { display:block; margin-bottom:8px; }
            .agreement-signature-primary .agreement-signature-rule { min-height:54px; width:100%; }
            .agreement-signature-primary .agreement-signature-rule img { max-height:50px; }
            .agreement-signature-patient { display:block; margin-top:5px; font-size:13px; }
            .agreement-signature-field { display:grid; grid-template-columns:auto minmax(0,1fr); align-items:end; gap:10px; }
            .agreement-signature-field .agreement-signature-rule { min-height:30px; }
            .agreement-cancellation-bottom { font-size:13px; line-height:1.45; }
            .agreement-page .digital-footer { position:static; padding:14px 16px; font-size:9px; line-height:1.45; }
        }
        @media (prefers-reduced-motion:reduce) { * { scroll-behavior:auto !important; transition:none !important; } }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
<?php if (!$contract): ?>
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-5 py-12">
        <div class="w-full rounded-[2rem] border border-slate-200 bg-white p-8 text-center shadow-sm">
            <img src="<?= e(base_url('assets/img/ES-Logo-Stack-500-x-150-px.png')) ?>" alt="Elite Smiles" class="mx-auto w-52">
            <h1 class="mt-8 text-2xl font-semibold">This secure link is unavailable</h1>
            <p class="mt-3 leading-7 text-slate-600">The agreement link may have expired or been replaced. Please contact Elite Smiles for a new link.</p>
        </div>
    </main>
<?php else: ?>
    <header class="no-print sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Secure document</p><p class="font-semibold text-slate-900"><?= e((string)($agreement['number'] ?? 'Treatment Agreement')) ?></p></div>
            <div class="flex flex-wrap gap-2">
                <div class="inline-flex rounded-xl bg-slate-100 p-1"><button type="button" data-mode="digital" class="min-h-10 rounded-lg bg-white px-3 text-sm font-semibold shadow-sm">Digital</button><button type="button" data-mode="preprinted" class="min-h-10 rounded-lg px-3 text-sm font-semibold text-slate-600">Preprinted paper</button></div>
                <button type="button" data-print class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold hover:bg-slate-100">Print</button>
            </div>
        </div>
    </header>

    <main class="mx-auto grid max-w-6xl items-start gap-6 px-3 py-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-6">
        <article id="agreement-document" class="agreement-page relative mx-auto overflow-hidden border border-slate-300 bg-white shadow-xl">
            <header class="digital-letterhead border-b border-slate-200 px-[7%] py-5 text-center">
                <img src="<?= e(base_url('assets/img/ES-Logo-Stack-500-x-150-px.png')) ?>" alt="Elite Smiles" class="mx-auto w-[147px] max-w-full">
                <p class="mt-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Dental Treatment Agreement</p>
            </header>
            <div class="paper-body px-[1in] pb-[0.65in] pt-[0.42in] text-[11pt] leading-[1.08]">
                <div class="agreement-original-copy">
                    <p><?= e((string)($agreement['date'] ?? '')) ?></p>
                    <p>Dental Treatment for <?= e((string)($agreement['patient_name'] ?? '')) ?>:</p>
                    <p><?= e($financialLanguage) ?></p>
                </div>
                <?php if ($hasOriginalTerms): ?><div class="agreement-payment-notice rounded px-2 py-1.5 text-center font-semibold text-slate-950"><span><?= e((string)$terms['cashier_check']) ?></span><span class="mx-1.5 text-amber-700" aria-hidden="true">&bull;</span><span><?= e((string)$terms['credit_card']) ?></span></div><?php endif; ?>
                <section><ul class="agreement-treatment-list">
                    <?php foreach ((array)($agreement['line_items'] ?? []) as $index => $item): ?><?php $itemArea = $lineItemAreaLabel((array)$item); ?><li class="list-disc"><?= e((string)($item['label'] ?? '')) ?><?= $itemArea !== '' && (!empty($item['teeth']) || !empty($item['arch_scope']) || $index === 0) ? ' — ' . e($itemArea) : '' ?></li><?php endforeach; ?>
                </ul></section>
                <section class="agreement-legal-copy text-[11pt] leading-[1.08]">
                    <?php if ($hasOriginalTerms): ?>
                        <p><?= e((string)$terms['treatment_changes']) ?></p>
                        <p class="font-semibold"><?= e((string)$terms['insurance_responsibility']) ?></p>
                        <?php if ((float)($financials['insurance_estimate'] ?? 0) > 0): ?><p><?= e((string)$terms['insurance_estimate']) ?></p><?php endif; ?>
                        <p class="agreement-sedation"><strong>Optional</strong> - <?= e($sedationBody) ?></p>
                        <p><strong><?= e((string)$terms['discount_acceptance']) ?></strong></p>
                    <?php else: ?>
                        <p><?= e((string)($terms['insurance'] ?? '')) ?></p><p><?= e((string)($terms['treatment_changes'] ?? '')) ?></p><p><?= e((string)($terms['payment'] ?? '')) ?></p><p><?= e((string)($terms['sedation'] ?? '')) ?></p>
                    <?php endif; ?>
                </section>
                <div class="agreement-closing-block">
                    <section class="agreement-signature agreement-signature-original">
                        <div class="agreement-signature-primary"><span>Patient Signature/Responsible Party:</span><span class="agreement-signature-rule"><?php if ($signature): ?><img src="<?= e((string)$signature['signature_data']) ?>" alt="Patient signature"><?php endif; ?></span><span class="agreement-signature-patient"><?= e((string)($agreement['patient_name'] ?? '')) ?></span></div>
                        <div class="agreement-signature-field"><span>Date:</span><span class="agreement-signature-rule"><?= $signature ? e(format_datetime((string)$signature['signed_at'])) : '' ?></span></div>
                    </section>
                    <div class="agreement-cancellation-bottom">
                        <?php if ($hasOriginalTerms): ?><p class="mb-[8pt] font-semibold"><?= e((string)$terms['original_cancellation']) ?></p><?php endif; ?>
                        <p class="text-[9pt] leading-[1.15]"><strong>Treatment Plan Cancellation.</strong> <?= e((string)($terms['cancellation_text'] ?? '')) ?></p>
                    </div>
                </div>
            </div>
            <footer class="digital-footer absolute inset-x-0 bottom-0 border-t border-slate-200 bg-white px-[7%] py-3 text-center text-[10px] leading-4 text-slate-500"><?= e((string)($practice['name'] ?? 'Elite Smiles')) ?> by Dr. Walter Meden · <?= e((string)($practice['address'] ?? '')) ?><br>Confidential Patient Document · <?= e((string)($agreement['number'] ?? '')) ?> · SHA-256 <?= e(substr((string)$contract['snapshot_hash'], 0, 12)) ?></footer>
        </article>

        <aside class="no-print space-y-4 lg:sticky lg:top-24">
            <?php if ($error !== ''): ?><div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($signature): ?>
                <div class="rounded-[2rem] border border-emerald-200 bg-white p-6 shadow-sm"><div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-6 w-6" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></div><h2 class="mt-4 text-xl font-semibold">Agreement signed</h2><p class="mt-2 text-sm leading-6 text-slate-600">Signed by <?= e((string)$signature['signer_name']) ?> on <?= e(format_datetime((string)$signature['signed_at'])) ?>. A permanent signature record and document hash were saved.</p></div>
            <?php else: ?>
                <form id="signature-form" method="POST" class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <?= csrf_input() ?><input type="hidden" name="action" value="sign_contract"><input type="hidden" name="token" value="<?= e($token) ?>"><input id="signature-data" type="hidden" name="signature_data">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Final step</p><h2 class="mt-2 text-xl font-semibold">Review and sign</h2><p class="mt-2 text-sm leading-6 text-slate-600">Your signature applies only to the exact version displayed.</p>
                    <label for="signer-name" class="mt-4 block text-sm font-medium">Full legal name</label><input id="signer-name" name="signer_name" required class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 px-3 text-base" autocomplete="name">
                    <label for="signer-relationship" class="mt-4 block text-sm font-medium">Relationship to patient</label><select id="signer-relationship" name="signer_relationship" class="mt-1.5 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base"><option value="self">Self</option><option value="parent">Parent</option><option value="guardian">Legal guardian</option><option value="responsible_party">Responsible party</option></select>
                    <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950"><input type="checkbox" name="cancellation_acknowledged" value="1" required class="mt-1 h-5 w-5 shrink-0"><span>I have read and understand the treatment-plan cancellation policy, including the possible fee of up to $1,500.</span></label>
                    <div class="mt-4"><div class="flex items-center justify-between"><label for="signature-canvas" class="text-sm font-medium">Draw signature</label><button id="clear-signature" type="button" class="min-h-10 px-2 text-sm font-semibold text-slate-600 hover:text-slate-900">Clear</button></div><canvas id="signature-canvas" width="600" height="220" class="mt-1.5 h-36 w-full rounded-xl border border-slate-400 bg-white" aria-label="Signature drawing area"></canvas><p class="mt-1 text-xs text-slate-500">Use your finger, mouse, or stylus.</p></div>
                    <button type="submit" class="mt-5 min-h-12 w-full rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">Sign treatment agreement</button>
                    <p class="mt-3 text-xs leading-5 text-slate-500">The signing record includes the date, time, IP address, device information, contract version, and document hash.</p>
                </form>
            <?php endif; ?>
        </aside>
    </main>
    <script>
    (function(){
        const doc=document.getElementById('agreement-document');
        document.querySelectorAll('[data-mode]').forEach(button=>button.addEventListener('click',()=>{const pre=button.dataset.mode==='preprinted';doc.classList.toggle('preprinted',pre);document.querySelectorAll('[data-mode]').forEach(other=>{const active=other===button;other.classList.toggle('bg-white',active);other.classList.toggle('shadow-sm',active);other.classList.toggle('text-slate-600',!active);});}));
        document.querySelector('[data-print]')?.addEventListener('click',()=>window.print());
        const canvas=document.getElementById('signature-canvas'); if(!canvas)return;
        const ratio=Math.min(window.devicePixelRatio||1,2);canvas.width=Math.round(canvas.clientWidth*ratio);canvas.height=Math.round(canvas.clientHeight*ratio);
        const ctx=canvas.getContext('2d');ctx.lineWidth=2.5*ratio;ctx.lineCap='round';ctx.lineJoin='round';ctx.strokeStyle='#0f172a';ctx.imageSmoothingEnabled=true;let drawing=false;let hasInk=false;
        const point=(clientX,clientY)=>{const r=canvas.getBoundingClientRect();return{x:(clientX-r.left)*(canvas.width/r.width),y:(clientY-r.top)*(canvas.height/r.height)}};
        const beginStroke=(clientX,clientY)=>{drawing=true;const p=point(clientX,clientY);ctx.beginPath();ctx.moveTo(p.x,p.y)};
        const continueStroke=(clientX,clientY)=>{if(!drawing)return;const p=point(clientX,clientY);ctx.lineTo(p.x,p.y);ctx.stroke();hasInk=true};
        const endStroke=()=>{drawing=false};
        if('PointerEvent' in window){
            canvas.addEventListener('pointerdown',e=>{e.preventDefault();beginStroke(e.clientX,e.clientY);canvas.setPointerCapture(e.pointerId)});
            canvas.addEventListener('pointermove',e=>{if(!drawing)return;e.preventDefault();continueStroke(e.clientX,e.clientY)});
            canvas.addEventListener('pointerup',endStroke);canvas.addEventListener('pointercancel',endStroke);
        }else{
            canvas.addEventListener('mousedown',e=>{e.preventDefault();beginStroke(e.clientX,e.clientY)});
            window.addEventListener('mousemove',e=>{if(drawing)continueStroke(e.clientX,e.clientY)});window.addEventListener('mouseup',endStroke);
            canvas.addEventListener('touchstart',e=>{e.preventDefault();const touch=e.changedTouches[0];if(touch)beginStroke(touch.clientX,touch.clientY)},{passive:false});
            canvas.addEventListener('touchmove',e=>{e.preventDefault();const touch=e.changedTouches[0];if(touch)continueStroke(touch.clientX,touch.clientY)},{passive:false});
            canvas.addEventListener('touchend',endStroke,{passive:false});canvas.addEventListener('touchcancel',endStroke,{passive:false});
        }
        document.getElementById('clear-signature').addEventListener('click',()=>{ctx.clearRect(0,0,canvas.width,canvas.height);hasInk=false});
        document.getElementById('signature-form').addEventListener('submit',e=>{if(!hasInk){e.preventDefault();alert('Please draw your signature before submitting.');return;}document.getElementById('signature-data').value=canvas.toDataURL('image/png');});
    })();
    </script>
<?php endif; ?>
</body>
</html>
