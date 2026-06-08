<?php
declare(strict_types=1);

/**
 * Elite Smiles Landing Engine
 * File: app/landing_pages/engine/lead-handler.php
 *
 * Handles all form POST processing:
 * - standard quiz form
 * - voucher_compact form
 * Writes lead to CRM, sends email + Pushover notifications.
 * Returns a $formResult array consumed by the renderer.
 */

if (!function_exists('lp_handle_post')) {

    function lp_handle_post(array $ctx): array
    {
        $result = [
            'success'          => false,
            'error'            => '',
            'successMessage'   => '',
            'voucherSubmitted' => false,
            'voucherReference' => '',
            'submittedLeadId'  => '',
            'standardForm'     => $ctx['standardForm'],
            'voucherForm'      => $ctx['voucherForm'],
        ];

        if (!is_post()) {
            return $result;
        }

        try {
            require_csrf();

            if ($ctx['layoutVariant'] === 'voucher_compact') {
                return lp_handle_voucher_post($ctx, $result);
            } else {
                return lp_handle_standard_post($ctx, $result);
            }
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage() !== ''
                ? $e->getMessage()
                : 'We could not submit your request right now. Please try again.';
            return $result;
        }
    }

}

if (!function_exists('lp_handle_standard_post')) {

    function lp_handle_standard_post(array $ctx, array $result): array
    {
        $sf = $ctx['standardForm'];
        $sf['first_name']         = trim((string) post('first_name'));
        $sf['last_name']          = trim((string) post('last_name'));
        $sf['email']              = trim((string) post('email'));
        $sf['phone']              = trim((string) post('phone'));
        $sf['procedure_interest'] = trim((string) post('procedure_interest', $ctx['procedureLabel']));
        $sf['financing_needed']   = trim((string) post('financing_needed', 'unsure'));
        $sf['preferred_contact']  = trim((string) post('preferred_contact'));
        $sf['sms_consent']        = post('sms_consent') === 'yes' ? 'yes' : '';

        foreach ($ctx['quizSteps'] as $step) {
            $field = (string) ($step['field'] ?? '');
            if ($field !== '') $sf[$field] = trim((string) post($field));
        }

        // Validate
        if ($sf['first_name'] === '') throw new RuntimeException('Please enter your first name.');
        if ($sf['phone'] === '')       throw new RuntimeException('Please enter your phone number.');
        if ($sf['email'] === '' || !filter_var($sf['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if (!in_array($sf['financing_needed'], ['yes','no','unsure'], true)) {
            $sf['financing_needed'] = 'unsure';
        }
        if (!in_array($sf['preferred_contact'], ['Call','Text','Email',''], true)) {
            $sf['preferred_contact'] = '';
        }

        if ($sf['preferred_contact'] === 'Text' && $sf['sms_consent'] !== 'yes') {
            $notesContactWarning = 'Preferred contact requested: Text, but SMS consent was not provided. Do not send text messages until consent is collected.';
        } else {
            $notesContactWarning = '';
        }

        $fullName = trim($sf['first_name'] . ' ' . $sf['last_name']);

        // Build notes
        $notes = [
            'Landing page intake submitted.',
            'Landing page: '    . $ctx['slug'],
            'Procedure type: '  . $ctx['procedureKey'],
            'City: '            . $ctx['cityLabel'],
            'Quiz type: '       . $ctx['quizType'],
        ];
        if ($ctx['angleKey'])      $notes[] = 'Angle: '            . $ctx['angleKey'];
        if ($ctx['querySource'])   $notes[] = 'Source: '           . $ctx['querySource'];
        if ($ctx['queryMedium'])   $notes[] = 'Source medium: '    . $ctx['queryMedium'];
        if ($ctx['queryType'])     $notes[] = 'Source type: '      . $ctx['queryType'];
        if ($ctx['queryCampaign']) $notes[] = 'Campaign: '         . $ctx['queryCampaign'];
        if ($ctx['queryKeyword'])  $notes[] = 'Trigger keyword: '  . $ctx['queryKeyword'];
        if ($ctx['queryIgUser'])   $notes[] = 'Instagram: '        . $ctx['queryIgUser'];
        if ($ctx['queryUtmId'])    $notes[] = 'UTM ID: '           . $ctx['queryUtmId'];
        if ($ctx['queryGclid'])    $notes[] = 'Google click ID: '  . $ctx['queryGclid'];
        if ($ctx['queryGbraid'])   $notes[] = 'Google GBRAID: '    . $ctx['queryGbraid'];
        if ($ctx['queryWbraid'])   $notes[] = 'Google WBRAID: '    . $ctx['queryWbraid'];
        if ($ctx['queryFbclid'])   $notes[] = 'Meta click ID: '     . $ctx['queryFbclid'];
        if ($ctx['queryFbp'])      $notes[] = 'Meta browser ID: '   . $ctx['queryFbp'];
        if ($ctx['queryFbc'])      $notes[] = 'Meta click cookie: ' . $ctx['queryFbc'];
        if ($ctx['queryMetaCampaignId']) $notes[] = 'Meta campaign ID: ' . $ctx['queryMetaCampaignId'];
        if ($ctx['queryMetaAdSetId'])    $notes[] = 'Meta ad set ID: '   . $ctx['queryMetaAdSetId'];
        if ($ctx['queryMetaAdId'])       $notes[] = 'Meta ad ID: '       . $ctx['queryMetaAdId'];
        if ($ctx['queryMetaPlacement'])  $notes[] = 'Meta placement: '   . $ctx['queryMetaPlacement'];
        if ($ctx['queryEventId'])        $notes[] = 'Conversion event ID: ' . $ctx['queryEventId'];

        foreach ($ctx['quizSteps'] as $step) {
            $field = $step['field'] ?? '';
            $value = $sf[$field] ?? '';
            if ($field !== '' && $value !== '') {
                $notes[] = ($step['title'] ?? $field) . ': ' . $value;
            }
        }
        if ($sf['preferred_contact'] !== '') {
            $notes[] = 'Preferred contact: ' . $sf['preferred_contact'];
        }
        $notes[] = 'SMS consent: ' . ($sf['sms_consent'] === 'yes' ? 'yes' : 'no');
        if ($notesContactWarning !== '') {
            $notes[] = $notesContactWarning;
        }

        $leadPayload = [
            'full_name'          => $fullName,
            'phone'              => $sf['phone'],
            'email'              => $sf['email'],
            'procedure_interest' => $sf['procedure_interest'],
            'source'             => $ctx['querySource'] ?: 'website',
            'source_medium'      => $ctx['queryMedium'] ?: 'landing',
            'source_type'        => $ctx['queryType']   ?: 'quiz_form',
            'landing_page'       => $ctx['slug'],
            'campaign'           => $ctx['queryCampaign'] ?: $ctx['slug'],
            'status'             => 'new_lead',
            'financing_needed'   => $sf['financing_needed'],
            'financing_option'   => 'none',
            'lead_value'         => '10000',
            'notes'              => implode("\n", $notes),
            'refresh_duplicate'  => true,
        ];

        $crmResult = lead_create_minimal($leadPayload, []);

        if (empty($crmResult['ok'])) {
            $result['error'] = (string) ($crmResult['message'] ?? 'We could not submit your request right now.');
            $result['standardForm'] = $sf;
            return $result;
        }

        $leadId = (string) ($crmResult['lead_id'] ?? '');
        lp_send_notifications($leadId, $fullName, $sf, $ctx, $notes);

        $result['success']        = true;
        $result['successMessage'] = 'Thank you - we received your information. Rod with Elite Smiles will text or call you shortly.';
        $result['submittedLeadId']= $leadId;
        $result['standardForm']   = lp_empty_standard_form($ctx['procedureLabel']);
        $result['detailsUrl']     = lp_post_submit_details_url($ctx);
        return $result;
    }

}

if (!function_exists('lp_post_submit_details_url')) {

    function lp_post_submit_details_url(array $ctx): string
    {
        $base = rtrim((string) APP_URL, '/') . '/l/' . rawurlencode((string) $ctx['slug']);
        $params = [
            'details' => '1',
            'submitted' => '1',
            'utm_source' => $ctx['querySource'] ?: 'website',
            'utm_medium' => $ctx['queryMedium'] ?: 'landing',
            'utm_campaign' => $ctx['queryCampaign'] ?: $ctx['slug'],
            'utm_content' => $ctx['queryType'] ?: 'quick_lead_form',
        ];

        foreach ([
            'utm_term' => 'queryKeyword',
            'utm_id' => 'queryUtmId',
            'gclid' => 'queryGclid',
            'gbraid' => 'queryGbraid',
            'wbraid' => 'queryWbraid',
            'fbclid' => 'queryFbclid',
            'fbp' => 'queryFbp',
            'fbc' => 'queryFbc',
            'ad_id' => 'queryMetaAdId',
            'adset_id' => 'queryMetaAdSetId',
            'campaign_id' => 'queryMetaCampaignId',
            'placement' => 'queryMetaPlacement',
            'event_id' => 'queryEventId',
        ] as $param => $ctxKey) {
            $value = trim((string) ($ctx[$ctxKey] ?? ''));
            if ($value !== '') {
                $params[$param] = $value;
            }
        }

        return $base . '?' . http_build_query($params);
    }

}

if (!function_exists('lp_handle_voucher_post')) {

    function lp_handle_voucher_post(array $ctx, array $result): array
    {
        $vf = $ctx['voucherForm'];
        $vf['full_name']        = trim((string) post('full_name'));
        $vf['phone']            = trim((string) post('phone'));
        $vf['email']            = trim((string) post('email'));
        $vf['what_brings_you_in']= trim((string) post('what_brings_you_in'));
        $vf['start_timing']     = trim((string) post('start_timing'));
        $vf['preferred_contact']= trim((string) post('preferred_contact'));

        if ($vf['full_name'] === '') throw new RuntimeException('Please enter your full name.');
        if ($vf['phone'] === '')     throw new RuntimeException('Please enter your phone number.');
        if ($vf['email'] === '' || !filter_var($vf['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        if (!in_array($vf['what_brings_you_in'], $ctx['voucherReasonOptions'], true)) {
            throw new RuntimeException('Please select what brings you in.');
        }
        if (!in_array($vf['start_timing'], $ctx['voucherTimingOptions'], true)) {
            throw new RuntimeException('Please select when you would like to start.');
        }
        if (!in_array($vf['preferred_contact'], $ctx['voucherContactOptions'], true)) {
            throw new RuntimeException('Please select your preferred contact method.');
        }

        $parts     = explode(' ', trim(preg_replace('/\s+/', ' ', $vf['full_name']) ?? ''));
        $firstName = array_shift($parts) ?? '';
        $lastName  = trim(implode(' ', $parts));

        $notes = [
            'Landing page intake submitted.',
            'Landing page: '   . $ctx['slug'],
            'Layout: voucher_compact',
            'Procedure type: ' . $ctx['procedureKey'],
            'Source: '         . ($ctx['querySource'] ?: 'website'),
            'What brings you in: ' . $vf['what_brings_you_in'],
            'When to start: '  . $vf['start_timing'],
            'Preferred contact: ' . $vf['preferred_contact'],
        ];
        if ($ctx['queryCampaign']) $notes[] = 'Campaign: ' . $ctx['queryCampaign'];
        if ($ctx['queryKeyword'])  $notes[] = 'Trigger keyword: ' . $ctx['queryKeyword'];
        if ($ctx['queryGclid'])    $notes[] = 'Google click ID: ' . $ctx['queryGclid'];
        if ($ctx['queryGbraid'])   $notes[] = 'Google GBRAID: ' . $ctx['queryGbraid'];
        if ($ctx['queryWbraid'])   $notes[] = 'Google WBRAID: ' . $ctx['queryWbraid'];
        if ($ctx['queryFbclid'])   $notes[] = 'Meta click ID: ' . $ctx['queryFbclid'];
        if ($ctx['queryFbp'])      $notes[] = 'Meta browser ID: ' . $ctx['queryFbp'];
        if ($ctx['queryFbc'])      $notes[] = 'Meta click cookie: ' . $ctx['queryFbc'];
        if ($ctx['queryMetaCampaignId']) $notes[] = 'Meta campaign ID: ' . $ctx['queryMetaCampaignId'];
        if ($ctx['queryMetaAdSetId'])    $notes[] = 'Meta ad set ID: ' . $ctx['queryMetaAdSetId'];
        if ($ctx['queryMetaAdId'])       $notes[] = 'Meta ad ID: ' . $ctx['queryMetaAdId'];
        if ($ctx['queryMetaPlacement'])  $notes[] = 'Meta placement: ' . $ctx['queryMetaPlacement'];
        if ($ctx['queryEventId'])        $notes[] = 'Conversion event ID: ' . $ctx['queryEventId'];

        $leadPayload = [
            'full_name'          => $vf['full_name'],
            'phone'              => $vf['phone'],
            'email'              => $vf['email'],
            'procedure_interest' => $vf['what_brings_you_in'],
            'source'             => $ctx['querySource'] ?: 'website',
            'source_medium'      => $ctx['queryMedium'] ?: 'landing',
            'source_type'        => $ctx['queryType']   ?: 'quiz_form',
            'landing_page'       => $ctx['slug'],
            'campaign'           => $ctx['queryCampaign'] ?: $ctx['slug'],
            'status'             => 'new_lead',
            'financing_needed'   => 'unsure',
            'financing_option'   => 'none',
            'lead_value'         => '10000',
            'notes'              => implode("\n", $notes),
            'refresh_duplicate'  => true,
        ];

        $crmResult = lead_create_minimal($leadPayload, []);

        if (empty($crmResult['ok'])) {
            $result['error']       = (string) ($crmResult['message'] ?? 'We could not submit your request right now.');
            $result['voucherForm'] = $vf;
            return $result;
        }

        $leadId          = (string) ($crmResult['lead_id'] ?? '');
        $voucherRef      = $leadId !== '' ? '#' . $leadId : strtoupper(substr(md5($vf['email'] . $vf['phone']), 0, 8));
        $sf              = ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$vf['email'],'phone'=>$vf['phone'],'procedure_interest'=>$vf['what_brings_you_in'],'financing_needed'=>'unsure','preferred_contact'=>$vf['preferred_contact']];

        lp_send_notifications($leadId, $vf['full_name'], $sf, $ctx, $notes);

        $result['success']          = true;
        $result['successMessage']   = 'Your consultation credit is ready.';
        $result['voucherSubmitted'] = true;
        $result['voucherReference'] = $voucherRef;
        $result['submittedLeadId']  = $leadId;
        $result['voucherForm']      = $vf;
        return $result;
    }

}

if (!function_exists('lp_send_notifications')) {

    function lp_send_notifications(string $leadId, string $fullName, array $sf, array $ctx, array $notes): void
    {
        try {
            if (function_exists('elite_send_lead_notification_email')) {
                elite_send_lead_notification_email(
                    [
                        'id'                 => $leadId,
                        'created_at'         => now(),
                        'full_name'          => $fullName,
                        'first_name'         => $sf['first_name']         ?? '',
                        'last_name'          => $sf['last_name']          ?? '',
                        'phone'              => $sf['phone']              ?? '',
                        'email'              => $sf['email']              ?? '',
                        'procedure_interest' => $sf['procedure_interest'] ?? '',
                        'financing_needed'   => $sf['financing_needed']   ?? 'unsure',
                        'preferred_contact'  => $sf['preferred_contact']  ?? '',
                        'landing_page'       => $ctx['slug'],
                        'source'             => $ctx['querySource'],
                        'source_medium'      => $ctx['queryMedium'],
                        'source_type'        => $ctx['queryType'],
                        'campaign'           => $ctx['queryCampaign'],
                        'trigger_keyword'    => $ctx['queryKeyword'],
                        'instagram_username' => $ctx['queryIgUser'],
                        'utm_id'             => $ctx['queryUtmId'] ?? '',
                        'gclid'              => $ctx['queryGclid'] ?? '',
                        'gbraid'             => $ctx['queryGbraid'] ?? '',
                        'wbraid'             => $ctx['queryWbraid'] ?? '',
                        'fbclid'             => $ctx['queryFbclid'] ?? '',
                        'fbp'                => $ctx['queryFbp'] ?? '',
                        'fbc'                => $ctx['queryFbc'] ?? '',
                        'meta_ad_id'         => $ctx['queryMetaAdId'] ?? '',
                        'meta_adset_id'      => $ctx['queryMetaAdSetId'] ?? '',
                        'meta_campaign_id'   => $ctx['queryMetaCampaignId'] ?? '',
                        'meta_placement'     => $ctx['queryMetaPlacement'] ?? '',
                        'conversion_event_id'=> $ctx['queryEventId'] ?? '',
                        'notes'              => implode("\n", $notes),
                        'transcription'      => '',
                    ],
                    [
                        'to'          => 'leads@hi.elitesmilesutah.com',
                        'lead_id'     => $leadId,
                        'created_at'  => now(),
                        'crm_url'     => base_url('dashboard.php'),
                        'landing_page'=> $ctx['slug'],
                        'transcription' => '',
                    ]
                );
            }
        } catch (Throwable $ignored) {}
    }

}
