<?php
declare(strict_types=1);

$tracking = $tracking ?? [];
$pageSlug = (string) ($tracking['landing_page'] ?? '');
$procedureType = (string) ($tracking['procedure_type'] ?? '');
$campaign = (string) ($tracking['campaign'] ?? $pageSlug);
$source = (string) ($tracking['source'] ?? 'website');
$medium = (string) ($tracking['medium'] ?? 'landing');
?>

<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');

fbq('init', '985439904008555');
fbq('track', 'PageView');
</script>

<noscript>
<img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=985439904008555&ev=PageView&noscript=1"
/>
</noscript>
<!-- End Meta Pixel Code -->

<script>
window.EliteLandingTracking = window.EliteLandingTracking || {
    events: [],
    push: function (eventName, payload) {
        this.events.push({
            name: eventName,
            payload: payload || {},
            ts: Date.now()
        });
    }
};

function trackEvent(eventName, payload) {
    payload = payload || {};

    window.EliteLandingTracking.push(eventName, payload);

    if (typeof fbq === 'function') {
        if (
            eventName === 'lead_submit' ||
            eventName === 'lead_success' ||
            eventName === 'form_submit'
        ) {
            fbq('track', 'Lead', payload);
        } else if (eventName !== 'page_view') {
            fbq('trackCustom', eventName, payload);
        }
    }
}

trackEvent('page_view', {
    landing_page: '<?= e($pageSlug) ?>',
    procedure_type: '<?= e($procedureType) ?>',
    source: '<?= e($source) ?>',
    medium: '<?= e($medium) ?>',
    campaign: '<?= e($campaign) ?>'
});
</script>