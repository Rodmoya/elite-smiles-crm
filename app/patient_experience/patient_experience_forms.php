<?php
declare(strict_types=1);

if (!function_exists('patient_experience_form_definition_catalog')) {
    function patient_experience_form_definition_catalog(): array
    {
        return [
            'elite_smiles_new_patient_packet' => patient_experience_elite_smiles_packet_definition(),
        ];
    }
}

if (!function_exists('patient_experience_active_packet_key')) {
    function patient_experience_active_packet_key(): string
    {
        return 'elite_smiles_new_patient_packet';
    }
}

if (!function_exists('patient_experience_packet_definition')) {
    function patient_experience_packet_definition(?string $packetKey = null): array
    {
        $packetKey = trim((string)($packetKey ?? patient_experience_active_packet_key()));
        $catalog = patient_experience_form_definition_catalog();
        return $catalog[$packetKey] ?? $catalog[patient_experience_active_packet_key()];
    }
}

if (!function_exists('patient_experience_elite_smiles_packet_definition')) {
    function patient_experience_elite_smiles_packet_definition(): array
    {
        return [
            'packet_key' => 'elite_smiles_new_patient_packet',
            'version' => 1,
            'title' => 'Elite Smiles Digital New Patient Packet',
            'description' => 'Reusable digital forms packet for check-in, consents, history, and acknowledgements.',
            'supports' => [
                'conditional_logic' => true,
                'signatures' => true,
                'review' => true,
                'versioning_ready' => true,
                'multilingual_ready' => true,
                'pdf_ready' => true,
            ],
            'sections' => [
                [
                    'section_key' => 'welcome',
                    'title' => 'Welcome',
                    'template_key' => 'patient_welcome',
                    'sort_order' => 10,
                    'fields' => [
                        ['key' => 'welcome_heading', 'type' => 'heading', 'label' => 'Welcome to Elite Smiles'],
                        [
                            'key' => 'welcome_paragraph',
                            'type' => 'paragraph',
                            'body' => 'We will guide you through a secure digital packet. Your answers save as you go, and the front desk can review everything before your visit begins.',
                        ],
                        ['key' => 'welcome_divider', 'type' => 'divider'],
                        [
                            'key' => 'welcome_acknowledged',
                            'type' => 'acknowledgement_checkbox',
                            'label' => 'I am ready to begin my secure check-in.',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'patient_information',
                    'title' => 'Patient Information',
                    'template_key' => 'patient_information',
                    'sort_order' => 20,
                    'fields' => [
                        ['key' => 'legal_first_name', 'type' => 'text', 'label' => 'Legal first name', 'required' => true],
                        ['key' => 'legal_last_name', 'type' => 'text', 'label' => 'Legal last name', 'required' => true],
                        ['key' => 'preferred_name', 'type' => 'text', 'label' => 'Preferred name'],
                        ['key' => 'date_of_birth', 'type' => 'dob', 'label' => 'Date of birth', 'required' => true],
                        ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                        ['key' => 'mobile_phone', 'type' => 'phone', 'label' => 'Mobile phone', 'required' => true],
                        [
                            'key' => 'preferred_contact_method',
                            'type' => 'dropdown',
                            'label' => 'Preferred contact method',
                            'required' => true,
                            'options' => [
                                'text' => 'Text message',
                                'phone' => 'Phone call',
                                'email' => 'Email',
                            ],
                        ],
                        ['key' => 'street_address', 'type' => 'address', 'label' => 'Street address', 'required' => true],
                        ['key' => 'city', 'type' => 'city', 'label' => 'City', 'required' => true],
                        ['key' => 'state', 'type' => 'state', 'label' => 'State', 'required' => true],
                        ['key' => 'zip', 'type' => 'zip', 'label' => 'ZIP code', 'required' => true],
                        [
                            'key' => 'sms_consent',
                            'type' => 'yes_no',
                            'label' => 'May we text you about appointments and treatment updates?',
                            'required' => true,
                        ],
                        [
                            'key' => 'email_consent',
                            'type' => 'yes_no',
                            'label' => 'May we email you about appointments and treatment updates?',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'emergency_contact',
                    'title' => 'Emergency Contact',
                    'template_key' => 'emergency_contact',
                    'sort_order' => 30,
                    'fields' => [
                        [
                            'key' => 'emergency_contact',
                            'type' => 'emergency_contact',
                            'label' => 'Emergency contact',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'insurance_information',
                    'title' => 'Insurance Information',
                    'template_key' => 'insurance_information',
                    'sort_order' => 40,
                    'fields' => [
                        [
                            'key' => 'has_insurance',
                            'type' => 'yes_no',
                            'label' => 'Do you have dental insurance?',
                            'required' => true,
                        ],
                        [
                            'key' => 'insurance_information',
                            'type' => 'insurance',
                            'label' => 'Insurance details',
                            'visible_if' => ['field' => 'has_insurance', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'medical_history',
                    'title' => 'Medical History',
                    'template_key' => 'medical_history',
                    'sort_order' => 50,
                    'fields' => [
                        [
                            'key' => 'medical_conditions',
                            'type' => 'multi_select',
                            'label' => 'Do you currently have any of these medical conditions?',
                            'options' => [
                                'heart_condition' => 'Heart condition',
                                'high_blood_pressure' => 'High blood pressure',
                                'diabetes' => 'Diabetes',
                                'autoimmune' => 'Autoimmune condition',
                                'bleeding_disorder' => 'Bleeding disorder',
                                'none' => 'None of the above',
                            ],
                        ],
                        ['key' => 'medications', 'type' => 'medication_list', 'label' => 'Current medications'],
                        ['key' => 'allergies', 'type' => 'allergy_list', 'label' => 'Allergies'],
                        [
                            'key' => 'pregnant',
                            'type' => 'yes_no',
                            'label' => 'Are you currently pregnant or trying to become pregnant?',
                            'required' => true,
                        ],
                        [
                            'key' => 'pregnancy_follow_up',
                            'type' => 'textarea',
                            'label' => 'Please share any pregnancy-related information we should know.',
                            'visible_if' => ['field' => 'pregnant', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                        [
                            'key' => 'medical_notes',
                            'type' => 'textarea',
                            'label' => 'Anything else about your medical history we should know?',
                        ],
                    ],
                ],
                [
                    'section_key' => 'dental_history',
                    'title' => 'Dental History',
                    'template_key' => 'dental_history',
                    'sort_order' => 60,
                    'fields' => [
                        [
                            'key' => 'last_dental_visit',
                            'type' => 'date',
                            'label' => 'When was your last dental visit?',
                        ],
                        [
                            'key' => 'dental_anxiety',
                            'type' => 'radio',
                            'label' => 'How do you feel about dental visits?',
                            'required' => true,
                            'options' => [
                                'comfortable' => 'Comfortable',
                                'slightly_nervous' => 'Slightly nervous',
                                'very_nervous' => 'Very nervous',
                            ],
                        ],
                        [
                            'key' => 'past_dental_concerns',
                            'type' => 'textarea',
                            'label' => 'Have you had any past dental concerns, pain, or treatment complications?',
                        ],
                    ],
                ],
                [
                    'section_key' => 'treatment_goals',
                    'title' => 'Treatment Goals',
                    'template_key' => 'treatment_goals',
                    'sort_order' => 70,
                    'fields' => [
                        [
                            'key' => 'interested_services',
                            'type' => 'multi_select',
                            'label' => 'Which services are you interested in?',
                            'options' => [
                                'veneers' => 'Veneers',
                                'implants' => 'Implants',
                                'invisalign' => 'Invisalign',
                                'whitening' => 'Whitening',
                                'general_dentistry' => 'General dentistry',
                                'smile_makeover' => 'Smile makeover',
                            ],
                        ],
                        ['key' => 'smile_goals', 'type' => 'textarea', 'label' => 'Tell us about your smile goals.', 'required' => true],
                        [
                            'key' => 'treatment_timeframe',
                            'type' => 'dropdown',
                            'label' => 'When would you ideally like to begin treatment?',
                            'required' => true,
                            'options' => [
                                'asap' => 'As soon as possible',
                                '1_3_months' => 'Within 1 to 3 months',
                                '3_6_months' => 'Within 3 to 6 months',
                                'just_exploring' => 'Just exploring for now',
                            ],
                        ],
                        [
                            'key' => 'financing_interest',
                            'type' => 'yes_no',
                            'label' => 'Would you like financing information?',
                            'required' => true,
                        ],
                        [
                            'key' => 'financing_notes',
                            'type' => 'textarea',
                            'label' => 'Share any financing preferences or questions.',
                            'visible_if' => ['field' => 'financing_interest', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'consent_to_proceed',
                    'title' => 'Consent to Proceed',
                    'template_key' => 'consent_to_proceed',
                    'sort_order' => 80,
                    'fields' => [
                        ['key' => 'consent_heading', 'type' => 'heading', 'label' => 'Consent to Proceed'],
                        [
                            'key' => 'consent_paragraph',
                            'type' => 'paragraph',
                            'body' => 'I understand that the information I provide helps Elite Smiles evaluate treatment options and plan care safely.',
                        ],
                        [
                            'key' => 'consent_to_proceed_ack',
                            'type' => 'acknowledgement_checkbox',
                            'label' => 'I consent to proceed with consultation, imaging, and treatment planning as recommended by the Elite Smiles team.',
                            'required' => true,
                        ],
                        ['key' => 'consent_initials', 'type' => 'digital_initials', 'label' => 'Patient initials', 'required' => true],
                    ],
                ],
                [
                    'section_key' => 'hipaa_privacy_acknowledgement',
                    'title' => 'HIPAA Privacy Acknowledgement',
                    'template_key' => 'hipaa_privacy_acknowledgement',
                    'sort_order' => 90,
                    'fields' => [
                        ['key' => 'hipaa_heading', 'type' => 'heading', 'label' => 'HIPAA Privacy Acknowledgement'],
                        [
                            'key' => 'hipaa_paragraph',
                            'type' => 'paragraph',
                            'body' => 'I acknowledge that I have been informed of Elite Smiles privacy practices and understand how my protected health information may be used for treatment, payment, and healthcare operations.',
                        ],
                        [
                            'key' => 'hipaa_acknowledged',
                            'type' => 'acknowledgement_checkbox',
                            'label' => 'I acknowledge the Elite Smiles privacy practices.',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'office_financial_policy',
                    'title' => 'Office & Financial Policy',
                    'template_key' => 'office_financial_policy',
                    'sort_order' => 100,
                    'fields' => [
                        ['key' => 'financial_heading', 'type' => 'heading', 'label' => 'Office & Financial Policy'],
                        [
                            'key' => 'financial_paragraph',
                            'type' => 'paragraph',
                            'body' => 'Payment expectations, scheduling, and financing responsibility remain the patient’s responsibility unless otherwise agreed to in writing.',
                        ],
                        [
                            'key' => 'financial_policy_ack',
                            'type' => 'acknowledgement_checkbox',
                            'label' => 'I understand the office and financial policy.',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'photography_image_consent',
                    'title' => 'Photography / Image Consent',
                    'template_key' => 'photography_image_consent',
                    'sort_order' => 110,
                    'fields' => [
                        [
                            'key' => 'clinical_photo_consent',
                            'type' => 'yes_no',
                            'label' => 'May we take clinical photos for diagnosis and treatment planning?',
                            'required' => true,
                        ],
                        [
                            'key' => 'marketing_photo_consent',
                            'type' => 'yes_no',
                            'label' => 'May we use your images for marketing?',
                            'visible_if' => ['field' => 'clinical_photo_consent', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                        [
                            'key' => 'social_media_consent',
                            'type' => 'yes_no',
                            'label' => 'May we use your images on social media?',
                            'visible_if' => ['field' => 'marketing_photo_consent', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                        [
                            'key' => 'website_consent',
                            'type' => 'yes_no',
                            'label' => 'May we use your images on our website?',
                            'visible_if' => ['field' => 'marketing_photo_consent', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                        [
                            'key' => 'educational_consent',
                            'type' => 'yes_no',
                            'label' => 'May we use your images for education or training?',
                            'visible_if' => ['field' => 'marketing_photo_consent', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                        [
                            'key' => 'printed_marketing_consent',
                            'type' => 'yes_no',
                            'label' => 'May we use your images in printed marketing materials?',
                            'visible_if' => ['field' => 'marketing_photo_consent', 'operator' => 'equals', 'value' => 'yes'],
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'no_recording_policy',
                    'title' => 'No Recording Policy',
                    'template_key' => 'no_recording_no_filming',
                    'sort_order' => 120,
                    'fields' => [
                        ['key' => 'recording_heading', 'type' => 'heading', 'label' => 'No Recording Policy'],
                        [
                            'key' => 'recording_paragraph',
                            'type' => 'paragraph',
                            'body' => 'Audio recording, video recording, livestreaming, and unauthorized photography are not permitted inside Elite Smiles unless Dr. Meden has personally approved it in advance.',
                        ],
                        [
                            'key' => 'no_recording_acknowledged',
                            'type' => 'acknowledgement_checkbox',
                            'label' => 'I understand and agree to this policy.',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'final_review',
                    'title' => 'Final Review',
                    'template_key' => 'final_review',
                    'sort_order' => 130,
                    'fields' => [
                        ['key' => 'review_heading', 'type' => 'heading', 'label' => 'Final Review'],
                        [
                            'key' => 'review_paragraph',
                            'type' => 'paragraph',
                            'body' => 'Review your packet before signing. If anything needs to be corrected, please ask the front desk for help before submitting.',
                        ],
                        [
                            'key' => 'final_review_ack',
                            'type' => 'acknowledgement_checkbox',
                            'label' => 'I reviewed my packet and confirm the information is accurate to the best of my knowledge.',
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'section_key' => 'final_signature',
                    'title' => 'Final Signature',
                    'template_key' => 'final_signature',
                    'sort_order' => 140,
                    'fields' => [
                        ['key' => 'signature_heading', 'type' => 'heading', 'label' => 'Final Signature'],
                        [
                            'key' => 'patient_signature',
                            'type' => 'digital_signature',
                            'label' => 'Patient signature',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ];
    }
}
