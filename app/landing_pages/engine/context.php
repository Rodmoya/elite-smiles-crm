<?php
declare(strict_types=1);

/**
 * Shared landing context builder
 * File: crm/app/landing_pages/engine/context.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

if (!function_exists('lp_context_safe_array_merge')) {
    function lp_context_safe_array_merge(array ...$arrays): array
    {
        $out = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($out[$key]) && is_array($out[$key])) {
                    $out[$key] = lp_context_safe_array_merge($out[$key], $value);
                } else {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('lp_context_infer_parts_from_slug')) {
    function lp_context_infer_parts_from_slug(string $slug, array $registry): array
    {
        return landing_pages_parse_slug(
            $slug,
            $registry['procedures'] ?? [],
            $registry['cities'] ?? []
        );
    }
}

if (!function_exists('lp_context_query_value')) {
    function lp_context_query_value(string ...$keys): string
    {
        $sources = [];
        if (is_post()) {
            $sources[] = $_POST;
        }
        $sources[] = $_GET;

        foreach ($keys as $key) {
            foreach ($sources as $source) {
                $value = trim((string) ($source[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('lp_context_first_nonempty')) {
    function lp_context_first_nonempty(mixed ...$values): string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('lp_build_context')) {
    function lp_build_context(array $pageRow = [], array $runtime = []): array
    {
        $registry = landing_pages_registry();

        $slug = trim((string) ($runtime['slug'] ?? $pageRow['slug'] ?? ''));
        $map = $registry['landing_map'][$slug] ?? [];
        $parsed = lp_context_infer_parts_from_slug($slug, $registry);

        $procedureKey = (string) (
            $pageRow['procedure_type']
            ?? $map['procedure']
            ?? $map['procedure_type']
            ?? $parsed['procedure']
            ?? ''
        );

        $cityKey = (string) (
            $pageRow['city']
            ?? $pageRow['city_target']
            ?? $map['city']
            ?? $parsed['city']
            ?? ''
        );

        $angleKey = (string) (
            $pageRow['angle']
            ?? $pageRow['page_angle']
            ?? $map['angle']
            ?? $parsed['angle']
            ?? ''
        );

        $procedure = $registry['procedures'][$procedureKey] ?? [];
        $city = lp_normalize_city_config($cityKey, $registry['cities'][$cityKey] ?? []);
        $angle = $registry['angles'][$procedureKey][$angleKey] ?? [];

        if ($angle === [] && !empty($procedure['default_angle'])) {
            $defaultAngle = (string) $procedure['default_angle'];
            if ($angleKey === '') {
                $angleKey = $defaultAngle;
            }
            $angle = $registry['angles'][$procedureKey][$angleKey] ?? $registry['angles'][$procedureKey][$defaultAngle] ?? [];
        }

        $sectionOrder = [];
        if (!empty($map['section_order']) && is_array($map['section_order'])) {
            $sectionOrder = $map['section_order'];
        } elseif (!empty($angle['section_order']) && is_array($angle['section_order'])) {
            $sectionOrder = $angle['section_order'];
        } elseif (!empty($procedure['section_order']) && is_array($procedure['section_order'])) {
            $sectionOrder = $procedure['section_order'];
        }

        $layoutVariant = trim((string) (
            $pageRow['layout_variant']
            ?? $map['layout_variant']
            ?? $map['layout']
            ?? $runtime['layoutVariant']
            ?? 'standard'
        ));
        if ($layoutVariant === '') {
            $layoutVariant = 'standard';
        }

        $templateFile = trim((string) (
            $runtime['templateFile']
            ?? $pageRow['template_file']
            ?? ''
        ));

        $questionSetFile = trim((string) (
            $runtime['question_set']
            ?? $pageRow['question_set']
            ?? $map['question_set']
            ?? ''
        ));
        if ($questionSetFile === '') {
            $questionSetFile = lp_default_question_set_filename($procedureKey, $angleKey !== '' ? $angleKey : (string) ($procedure['default_angle'] ?? ''));
        }

        $quizSteps = lp_question_set_steps($registry, $questionSetFile);
        $procedureLabel = (string) ($procedure['label'] ?? ucwords(str_replace('_', ' ', $procedureKey)));
        $cityLabel = (string) ($city['city_label'] ?? $city['label'] ?? '');
        $formAction = lp_resolve_form_action($slug);
        $standardForm = lp_empty_standard_form($procedureLabel, $quizSteps);
        $voucherForm = lp_default_voucher_form();

        $querySource = lp_context_first_nonempty($runtime['querySource'] ?? '', $pageRow['source'] ?? '', lp_context_query_value('utm_source', 'source'), $map['source'] ?? '');
        $queryMedium = lp_context_first_nonempty($runtime['queryMedium'] ?? '', $pageRow['source_medium'] ?? '', lp_context_query_value('utm_medium', 'medium'), $map['source_medium'] ?? '');
        $queryType = lp_context_first_nonempty($runtime['queryType'] ?? '', $pageRow['source_type'] ?? '', lp_context_query_value('utm_content', 'type'), $map['source_type'] ?? '');
        $queryCampaign = lp_context_first_nonempty($runtime['queryCampaign'] ?? '', $pageRow['campaign'] ?? '', lp_context_query_value('utm_campaign', 'campaign'), $map['campaign'] ?? '');
        $queryKeyword = (string) ($runtime['queryKeyword'] ?? lp_context_query_value('utm_term', 'keyword'));
        $queryIgUser = (string) ($runtime['queryIgUser'] ?? lp_context_query_value('ig_username', 'instagram'));
        $queryUtmId = (string) ($runtime['queryUtmId'] ?? lp_context_query_value('utm_id'));
        $queryGclid = (string) ($runtime['queryGclid'] ?? lp_context_query_value('gclid'));
        $queryGbraid = (string) ($runtime['queryGbraid'] ?? lp_context_query_value('gbraid'));
        $queryWbraid = (string) ($runtime['queryWbraid'] ?? lp_context_query_value('wbraid'));
        $queryFbclid = (string) ($runtime['queryFbclid'] ?? lp_context_query_value('fbclid'));
        $queryFbp = (string) ($runtime['queryFbp'] ?? lp_context_query_value('fbp', '_fbp'));
        $queryFbc = (string) ($runtime['queryFbc'] ?? lp_context_query_value('fbc', '_fbc'));
        $queryMetaAdId = (string) ($runtime['queryMetaAdId'] ?? lp_context_query_value('ad_id', 'utm_ad_id'));
        $queryMetaAdSetId = (string) ($runtime['queryMetaAdSetId'] ?? lp_context_query_value('adset_id', 'utm_adset_id'));
        $queryMetaCampaignId = (string) ($runtime['queryMetaCampaignId'] ?? lp_context_query_value('campaign_id', 'utm_campaign_id'));
        $queryMetaPlacement = (string) ($runtime['queryMetaPlacement'] ?? lp_context_query_value('placement', 'utm_placement'));
        $queryEventId = (string) ($runtime['queryEventId'] ?? lp_context_query_value('event_id', 'conversion_event_id'));
        $detailsMode = lp_context_query_value('details', 'show_details') === '1';
        $submittedDetailsView = lp_context_query_value('submitted') === '1';
        $miniLandingGate = $slug === 'veneers-draper-v1' && !$detailsMode;
        $attribution = [
            'source' => $querySource,
            'source_medium' => $queryMedium,
            'source_type' => $queryType,
            'campaign' => $queryCampaign,
            'keyword' => $queryKeyword,
            'instagram' => $queryIgUser,
            'utm_source' => $querySource,
            'utm_medium' => $queryMedium,
            'utm_campaign' => $queryCampaign,
            'utm_content' => $queryType,
            'utm_term' => $queryKeyword,
            'utm_id' => $queryUtmId,
            'gclid' => $queryGclid,
            'gbraid' => $queryGbraid,
            'wbraid' => $queryWbraid,
            'fbclid' => $queryFbclid,
            'fbp' => $queryFbp,
            'fbc' => $queryFbc,
            'ad_id' => $queryMetaAdId,
            'adset_id' => $queryMetaAdSetId,
            'campaign_id' => $queryMetaCampaignId,
            'placement' => $queryMetaPlacement,
            'event_id' => $queryEventId,
        ];

        $head = lp_build_head_meta($slug, $procedure, $city);
        $logoUrl = (string) ($runtime['logoUrl'] ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png'));

        $ctx = [
            'slug' => $slug,
            'pageSlug' => $slug,
            'pageRow' => $pageRow,
            'page' => $pageRow,
            'mapEntry' => $map,
            'registry' => $registry,
            'procedure_key' => $procedureKey,
            'city_key' => $cityKey,
            'angle_key' => $angleKey,
            'procedure' => $procedure,
            'city' => $city,
            'angle' => $angle,
            'layoutVariant' => $layoutVariant,
            'templateFile' => $templateFile,
            'section_order' => $sectionOrder,
            'question_set' => $questionSetFile,
            'quizSteps' => $quizSteps,
            'quizType' => pathinfo($questionSetFile, PATHINFO_FILENAME),
            'procedureLabel' => $procedureLabel,
            'cityLabel' => $cityLabel,
            'formAction' => $formAction,
            'standardForm' => $standardForm,
            'voucherForm' => $voucherForm,
            'voucherReasonOptions' => ['Veneers consultation', 'Smile makeover consultation', 'Cosmetic dentistry consultation', 'I am not sure yet'],
            'voucherTimingOptions' => ['As soon as possible', 'Within the next 1 to 3 months', 'Within the next 6 months', 'Just researching for now'],
            'voucherContactOptions' => ['Call', 'Text', 'Email'],
            'querySource' => $querySource,
            'queryMedium' => $queryMedium,
            'queryType' => $queryType,
            'queryCampaign' => $queryCampaign,
            'queryKeyword' => $queryKeyword,
            'queryIgUser' => $queryIgUser,
            'queryUtmId' => $queryUtmId,
            'queryGclid' => $queryGclid,
            'queryGbraid' => $queryGbraid,
            'queryWbraid' => $queryWbraid,
            'queryFbclid' => $queryFbclid,
            'queryFbp' => $queryFbp,
            'queryFbc' => $queryFbc,
            'queryMetaAdId' => $queryMetaAdId,
            'queryMetaAdSetId' => $queryMetaAdSetId,
            'queryMetaCampaignId' => $queryMetaCampaignId,
            'queryMetaPlacement' => $queryMetaPlacement,
            'queryEventId' => $queryEventId,
            'detailsMode' => $detailsMode,
            'submittedDetailsView' => $submittedDetailsView,
            'miniLandingGate' => $miniLandingGate,
            'attribution' => $attribution,
            'googleAdsConversionEvent' => (string) ($runtime['googleAdsConversionEvent'] ?? $pageRow['google_ads_conversion_event'] ?? $map['google_ads_conversion_event'] ?? ''),
            'googleAdsConversionSendTo' => (string) ($runtime['googleAdsConversionSendTo'] ?? $pageRow['google_ads_conversion_send_to'] ?? $map['google_ads_conversion_send_to'] ?? ''),
            'logoUrl' => $logoUrl,
            'head' => $head,
            'metaTitle' => (string) ($head['title'] ?? ''),
            'metaDesc' => (string) ($head['description'] ?? ''),
            'canonicalUrl' => (string) ($head['canonical'] ?? ''),
            'primaryCtaText' => (string) ($procedure['cta']['button_label'] ?? 'Take Advantage of the $750 Offer'),
            'offerBadge' => (string) ($procedure['defaults']['offer_badge'] ?? '$750 VALUE'),
            'runtime' => $runtime,
        ];

        $ctx['procedureKey'] = $procedureKey;
        $ctx['cityKey'] = $cityKey;
        $ctx['angleKey'] = $angleKey;

        if ($procedureKey === 'veneers') {
            $landingView = landing_pages_build_veneers_view($ctx, $runtime);

            $ctx['landingView'] = $landingView;
            $ctx['sections'] = $landingView['sections'] ?? [];
            $ctx['section_order'] = $landingView['section_order'] ?? $ctx['section_order'];
            $ctx['headerCtaText'] = $landingView['header_cta_text'] ?? $ctx['primaryCtaText'];
        } elseif ($procedureKey === 'implants') {
            $landingView = landing_pages_build_implants_view($ctx, $runtime);

            $ctx['landingView'] = $landingView;
            $ctx['sections'] = $landingView['sections'] ?? [];
            $ctx['section_order'] = $landingView['section_order'] ?? $ctx['section_order'];
            $ctx['headerCtaText'] = $landingView['header_cta_text'] ?? $ctx['primaryCtaText'];
        }

        $ctx['modal'] = [
            'steps' => $ctx['quizSteps'],
            'action' => $ctx['formAction'],
            'csrf_token' => function_exists('csrf_token') ? (string) csrf_token() : '',
            'form_state' => $ctx['standardForm'],
            'attribution' => $ctx['attribution'],
            'form_variant' => (string) ($map['form_variant'] ?? 'quiz-standard.php'),
        ];

        $ctx['landingContext'] = $ctx;
        $ctx['ctx'] = $ctx;

        return $ctx;
    }
}

if (!function_exists('lp_build_full_context')) {
    function lp_build_full_context(string $slug, array $pageRow = [], array $runtimeOrRegistry = []): array
    {
        $runtime = $runtimeOrRegistry;

        if (isset($runtimeOrRegistry['procedures'], $runtimeOrRegistry['cities']) && !isset($runtimeOrRegistry['runtime'])) {
            $runtime = [];
        }

        if (!isset($pageRow['slug']) || trim((string) $pageRow['slug']) === '') {
            $pageRow['slug'] = $slug;
        }

        return lp_build_context($pageRow, $runtime);
    }
}
