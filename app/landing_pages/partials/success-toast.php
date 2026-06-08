<?php declare(strict_types=1); ?>
<div id="successToast"
    class="fixed inset-x-0 top-5 z-50 mx-auto w-[calc(100%-2rem)] max-w-xl rounded-[1.5rem] border border-emerald-200 bg-white px-5 py-4 shadow-2xl">
    <div class="text-sm font-semibold text-emerald-700">Assessment submitted</div>
    <div class="mt-1 text-sm text-slate-700"><?= e($successMessage) ?></div>
</div>
<script>
setTimeout(function () {
    window.location.href = 'https://www.instagram.com/direct/inbox/';
}, 2600);
</script>
