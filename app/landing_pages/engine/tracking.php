<?php
declare(strict_types=1);

/**
 * Elite Smiles Landing Engine
 * File: app/landing_pages/engine/tracking.php
 *
 * Renders all tracking scripts:
 * - Google Tag Manager / gtag
 * - Meta Pixel
 * - dataLayer events (page_view, wizard_start, quiz_step_answered, form_submitted)
 *
 * Called from the template head and at bottom of body.
 * All values are properly escaped.
 */

if (!function_exists('lp_tracking_head')) {
    /**
     * Outputs <head> tracking scripts (GTM + Meta Pixel).
     */
    function lp_tracking_head(): void
    {
        ?>
<!-- Google Tag Manager -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GT-NFXX2L9W"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'GT-NFXX2L9W');
gtag('config', 'AW-11288096208');
</script>

<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','1641771440413801');
fbq('track','PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=1641771440413801&ev=PageView&noscript=1"></noscript>
        <?php
    }
}

if (!function_exists('lp_tracking_page_view')) {
    /**
     * Outputs the inline page_view dataLayer push.
     * Call this inside the bottom <script> block on page load.
     */
    function lp_tracking_page_view(array $ctx): void
    {
        $slug     = e($ctx['slug']);
        $proc     = e($ctx['procedureKey']);
        $source   = e($ctx['querySource'] ?: 'website');
        $medium   = e($ctx['queryMedium'] ?: 'landing');
        $campaign = e($ctx['queryCampaign'] ?: $ctx['slug']);
        $keyword  = e($ctx['queryKeyword'] ?? '');
        $gclid    = e($ctx['queryGclid'] ?? '');
        $gbraid   = e($ctx['queryGbraid'] ?? '');
        $wbraid   = e($ctx['queryWbraid'] ?? '');
        $fbclid   = e($ctx['queryFbclid'] ?? '');
        $adId     = e($ctx['queryMetaAdId'] ?? '');
        $adsetId  = e($ctx['queryMetaAdSetId'] ?? '');
        $campId   = e($ctx['queryMetaCampaignId'] ?? '');
        $place    = e($ctx['queryMetaPlacement'] ?? '');
        echo "trackEvent('page_view',{landing_page:'$slug',procedure_type:'$proc',source:'$source',medium:'$medium',campaign:'$campaign',keyword:'$keyword',gclid:'$gclid',gbraid:'$gbraid',wbraid:'$wbraid',fbclid:'$fbclid',ad_id:'$adId',adset_id:'$adsetId',campaign_id:'$campId',placement:'$place'});";
    }
}

if (!function_exists('lp_tracking_js_fn')) {
    /**
     * Outputs the shared trackEvent JS helper function.
     * Place once per page, inside a <script> tag.
     */
    function lp_tracking_js_fn(): void
    {
        ?>
function trackEvent(name, payload) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({ event: name }, payload || {}));
    if (typeof gtag === 'function') { gtag('event', name, payload || {}); }
    if (typeof fbq  === 'function') {
        if (name === 'lead_success') {
            const eventID = payload && payload.event_id ? { eventID: payload.event_id } : undefined;
            fbq('track', 'Lead', payload || {}, eventID);
        } else {
            fbq('trackCustom', name, payload || {});
        }
    }
}
        <?php
    }
}
