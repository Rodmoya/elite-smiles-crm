<?php
declare(strict_types=1);

if (!function_exists('doc_library_templates')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function doc_library_templates(): array
    {
        return [
            [
                'key' => 'welcome_patient_information',
                'title' => 'Welcome Patient Information',
                'subtitle' => 'Medical / Patient Intake Form',
                'sections' => [
                    [
                        'type' => 'info',
                        'title' => 'WELCOME PATIENT INFORMATION',
                        'subtitle' => '(Medical / Patient Intake Form)',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => "Instructions:\nPlease complete as much as possible. If you have questions, ask the office staff."],
                        ],
                    ],
                    [
                        'title' => 'PATIENT INFORMATION',
                        'rows' => [
                            [['type' => 'date', 'label' => 'Date', 'name' => 'welcome_date']],
                            [['type' => 'text', 'label' => 'Name', 'name' => 'welcome_name', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Last Name', 'name' => 'welcome_last_name'], ['type' => 'text', 'label' => 'First Name', 'name' => 'welcome_first_name'], ['type' => 'text', 'label' => 'Middle Initial', 'name' => 'welcome_middle_initial', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'Social Security #', 'name' => 'welcome_ssn']],
                            [['type' => 'text', 'label' => 'Address', 'name' => 'welcome_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'City', 'name' => 'welcome_city'], ['type' => 'text', 'label' => 'State', 'name' => 'welcome_state', 'size' => 'short'], ['type' => 'text', 'label' => 'Zip', 'name' => 'welcome_zip', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'Day Phone', 'name' => 'welcome_day_phone'], ['type' => 'text', 'label' => 'Alt Phone', 'name' => 'welcome_alt_phone']],
                            [['type' => 'email', 'label' => 'E-mail', 'name' => 'welcome_email', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Age', 'name' => 'welcome_age', 'size' => 'short'], ['type' => 'date', 'label' => 'Birthdate', 'name' => 'welcome_birthdate']],
                            [['type' => 'checkbox_row', 'label' => 'Sex', 'name' => 'welcome_sex', 'options' => ['Male', 'Female'] , 'inline' => true]],
                            [['type' => 'checkbox_row', 'label' => 'Marital Status', 'name' => 'welcome_marital_status', 'options' => ['Married', 'Widowed', 'Single', 'Separated', 'Divorced', 'Partnered'], 'inline' => false]],
                            [['type' => 'text', 'label' => 'Employer/School', 'name' => 'welcome_employer_school', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Occupation', 'name' => 'welcome_occupation']],
                            [['type' => 'text', 'label' => 'Employer/School Address', 'name' => 'welcome_employer_school_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Employer/School Phone', 'name' => 'welcome_employer_school_phone', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Referred by', 'name' => 'welcome_referred_by', 'class' => 'doc-field-full']],
                        ],
                    ],
                    [
                        'title' => 'EMERGENCY CONTACT',
                        'rows' => [
                            [['type' => 'text', 'label' => 'In case of emergency, notify', 'name' => 'welcome_emergency_name', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Phone', 'name' => 'welcome_emergency_phone']],
                        ],
                    ],
                    [
                        'title' => 'PATIENT/ACCOUNT RESPONSIBLE PERSON',
                        'page_break_before' => true,
                        'rows' => [
                            [['type' => 'text', 'label' => 'Last Name', 'name' => 'welcome_responsible_last_name'], ['type' => 'text', 'label' => 'First Name', 'name' => 'welcome_responsible_first_name']],
                            [['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'welcome_responsible_relationship']],
                            [['type' => 'text', 'label' => 'Social Security #', 'name' => 'welcome_responsible_ssn']],
                            [['type' => 'text', 'label' => 'Address (if different)', 'name' => 'welcome_responsible_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'City', 'name' => 'welcome_responsible_city'], ['type' => 'text', 'label' => 'State', 'name' => 'welcome_responsible_state', 'size' => 'short'], ['type' => 'text', 'label' => 'Zip', 'name' => 'welcome_responsible_zip', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'Phone', 'name' => 'welcome_responsible_phone']],
                            [['type' => 'text', 'label' => 'Occupation', 'name' => 'welcome_responsible_occupation']],
                            [['type' => 'text', 'label' => 'Employed by', 'name' => 'welcome_responsible_employed_by']],
                            [['type' => 'text', 'label' => 'Business Address', 'name' => 'welcome_responsible_business_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Business Phone', 'name' => 'welcome_responsible_business_phone']],
                        ],
                    ],
                    [
                        'title' => 'INSURANCE INFORMATION',
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'Primary Insurance:']],
                            [['type' => 'text', 'label' => 'Insurance Company', 'name' => 'welcome_primary_insurance_company', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'ID/Contract #', 'name' => 'welcome_primary_id']],
                            [['type' => 'text', 'label' => 'Group #', 'name' => 'welcome_primary_group']],
                            [['type' => 'text', 'label' => 'Subscriber Name', 'name' => 'welcome_primary_subscriber_name', 'class' => 'doc-field-full']],
                            [['type' => 'date', 'label' => 'Subscriber Birthdate', 'name' => 'welcome_primary_subscriber_birthdate']],
                            [['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'welcome_primary_subscriber_relationship']],
                            [['type' => 'text', 'label' => 'Subscriber Address', 'name' => 'welcome_primary_subscriber_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Subscriber City/State/Zip', 'name' => 'welcome_primary_subscriber_city_state_zip', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Subscriber Phone', 'name' => 'welcome_primary_subscriber_phone']],
                            [['type' => 'text', 'label' => 'Subscriber employed by business', 'name' => 'welcome_primary_subscriber_employed_by']],
                            [['type' => 'text', 'label' => 'Additional Dependents on Plan', 'name' => 'welcome_primary_dependents', 'class' => 'doc-field-full']],
                            [['type' => 'checkbox_row', 'label' => 'Additional Insurance?', 'name' => 'welcome_has_additional_insurance', 'options' => ['Yes', 'No'], 'inline' => true]],
                            [['type' => 'row_title', 'text' => 'If Yes, complete below:']],
                            [['type' => 'text', 'label' => 'Insurance Company', 'name' => 'welcome_additional_insurance_company', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Subscriber Name', 'name' => 'welcome_additional_subscriber_name', 'class' => 'doc-field-full']],
                            [['type' => 'date', 'label' => 'Birthdate', 'name' => 'welcome_additional_subscriber_birthdate']],
                            [['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'welcome_additional_subscriber_relationship']],
                            [['type' => 'text', 'label' => 'Subscriber Address', 'name' => 'welcome_additional_subscriber_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Subscriber City/State/Zip', 'name' => 'welcome_additional_subscriber_city_state_zip', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Subscriber Phone', 'name' => 'welcome_additional_subscriber_phone']],
                            [['type' => 'text', 'label' => 'Employed by', 'name' => 'welcome_additional_subscriber_employed_by']],
                            [['type' => 'text', 'label' => 'Business Phone', 'name' => 'welcome_additional_subscriber_business_phone']],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'rows' => [
                            [
                                ['type' => 'signature_double', 'left_label' => 'Patient Signature', 'left_name' => 'welcome_patient_signature', 'right_label' => 'Date', 'right_name' => 'welcome_patient_signature_date'],
                            ],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Parent/Guardian Signature (if minor)', 'left_name' => 'welcome_parent_signature', 'right_label' => 'Date', 'right_name' => 'welcome_parent_signature_date'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'consent_to_proceed',
                'title' => 'Consent to Proceed',
                'subtitle' => 'Dental Treatment Consent Form',
                'sections' => [
                    [
                        'type' => 'info',
                        'title' => 'CONSENT TO PROCEED',
                        'subtitle' => '(Dental Treatment Consent Form)',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => 'I authorize Dr. [NAME] and designated associates or assistants to perform such procedures as may be necessary or advisable for the treatment of my dental health or the dental health of my minor child.'],
                            ['type' => 'paragraph', 'text' => 'I authorize, if needed, the use and administration of sedatives (including nitrous oxide), analgesics, and other medications related to restorative, therapeutic, diagnostic, and/or surgical treatment.'],
                            ['type' => 'paragraph', 'text' => "I understand and accept that complications may occur:\n- Local anesthetic side effects (pain/swelling/hematoma etc.)\n- Sensitivity, soreness, or discomfort during and after treatment\n- Allergic reactions to materials or medications\n- Tissue trauma (including tongue/cheek abrasion or laceration)\n- Need for additional treatment or sutures\n- Rare aspiration/swallowing of small instruments/components requiring further care\n- Need for additional x-rays in rare circumstances"],
                            ['type' => 'paragraph', 'text' => 'I understand that certain medications can affect treatment (for example osteoporosis medications such as Fosamax, Boniva, Actonel, etc.) and I must disclose all current prescriptions and conditions.'],
                            ['type' => 'paragraph', 'text' => 'I voluntarily assume all risks, including serious harm, and acknowledge that results may not be guaranteed.'],
                            ['type' => 'paragraph', 'text' => 'I confirm that the nature and purpose of proposed procedures have been explained and I had the opportunity to ask questions.'],
                        ],
                    ],
                    [
                        'title' => 'Signatures',
                        'rows' => [
                            [['type' => 'text', 'label' => 'Patient Name', 'name' => 'ctb_patient_name', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Patient or legal guardian signature', 'name' => 'ctb_patient_guardian_signature', 'class' => 'doc-field-full']],
                            [['type' => 'date', 'label' => 'Date', 'name' => 'ctb_signature_date']],
                            [['type' => 'text', 'label' => 'Witness', 'name' => 'ctb_witness', 'class' => 'doc-field-full']],
                            [['type' => 'date', 'label' => 'Date', 'name' => 'ctb_witness_date']],
                            [['type' => 'row_title', 'text' => "WALTER MEDEN D.D.S., P.C.\n11762 STATE STREET, SUITE 300\nDRAPER, UT 84020\n(801) 572-6262"]],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'consent_for_photo_image_use',
                'title' => 'Consent for Photo Image Use',
                'subtitle' => '',
                'sections' => [
                    [
                        'type' => 'info',
                        'title' => 'CONSENT FOR PHOTOGRAPHIC IMAGE USE',
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'The undersigned authorizes the office of Dr. [NAME] to use the following images:']],
                            [['type' => 'checkbox_list', 'name' => 'photo_image_authorization', 'options' => ['Before and after pictures of my teeth', 'Before and after pictures of my full face', 'Before and after pictures of my minor child’s teeth and/or full face']]],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'By signing this authorization, I waive any claims of breach of privacy related to release/use of photographic or digital images as checked above.']],
                            [['type' => 'row_title', 'text' => 'I acknowledge that I have received a copy of the office privacy policies.']],
                        ],
                    ],
                    [
                        'rows' => [
                            [['type' => 'signature_single', 'label' => 'Patient or Parent Signature', 'name' => 'photo_patient_signature']],
                            [['type' => 'signature_single', 'label' => 'Date', 'name' => 'photo_patient_signature_date']],
                            [['type' => 'signature_single', 'label' => 'Witness Signature (office staff)', 'name' => 'photo_witness_signature']],
                            [['type' => 'signature_single', 'label' => 'Date', 'name' => 'photo_witness_signature_date']],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'rows' => [
                            [['type' => 'row_title', 'text' => '(Rev. 11/07)']],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'notice_of_privacy_practices_acknowledgment',
                'title' => 'Notice of Privacy Practices Acknowledgment',
                'subtitle' => '(HIPAA Rights Acknowledgment)',
                'sections' => [
                    [
                        'type' => 'info',
                        'title' => 'NOTICE OF PRIVACY PRACTICES ACKNOWLEDGMENT',
                        'subtitle' => '(HIPAA Rights Acknowledgment)',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => 'I acknowledge that I have received and read the Notice of Privacy Practices.'],
                            ['type' => 'paragraph', 'text' => "I understand my protected health information may be used and disclosed for:\n- Treatment\n- Payment\n- Healthcare operations (quality assessments, physician certifications, and related operations)"],
                            ['type' => 'paragraph', 'text' => 'I understand the organization may update this Notice and that I can request a current copy.'],
                            ['type' => 'paragraph', 'text' => 'I understand I may request restrictions on disclosures in writing. If restrictions are agreed to, I will follow those limits.'],
                            ['type' => 'checkbox', 'label' => 'I understand and agree to this acknowledgment.', 'name' => 'hipaa_acknowledgment'],
                            ['type' => 'paragraph', 'text' => 'Additional Release of Information'],
                            ['type' => 'paragraph', 'text' => 'I authorize release of information including diagnosis, examination records, and claims as indicated:'],
                            ['type' => 'text', 'label' => 'Name', 'name' => 'hipaa_release_name', 'class' => 'doc-field-full'],
                            ['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'hipaa_release_relationship', 'class' => 'doc-field-full'],
                        ],
                    ],
                    [
                        'title' => 'Release details',
                        'rows' => [
                            [['type' => 'checkbox_row', 'label' => 'Information is NOT to be released to:', 'name' => 'hipaa_release_destination', 'options' => ['Communication barriers', 'Other'], 'inline' => true]],
                            [['type' => 'text', 'label' => 'Other', 'name' => 'hipaa_release_destination_other', 'class' => 'doc-field-full']],
                        ],
                    ],
                    [
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'This release remains in effect until terminated in writing.']],
                        ],
                    ],
                    [
                        'title' => 'Signatures',
                        'rows' => [
                            [['type' => 'text', 'label' => 'Patient Name', 'name' => 'hipaa_patient_name', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'hipaa_patient_relationship', 'class' => 'doc-field-full']],
                            [['type' => 'signature_single', 'label' => 'Signature', 'name' => 'hipaa_signature']],
                            [['type' => 'signature_single', 'label' => 'Date', 'name' => 'hipaa_signature_date']],
                        ],
                    ],
                    [
                        'title' => 'Office use only (if acknowledgment not obtained):',
                        'rows' => [
                            [['type' => 'checkbox', 'label' => 'Patient refused to sign due to: Communication barriers', 'name' => 'hipaa_refusal_communication_barriers']],
                            [['type' => 'text', 'label' => 'Other', 'name' => 'hipaa_refusal_other']],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'office_and_insurance_policy',
                'title' => 'Office and Insurance Policy',
                'subtitle' => '',
                'sections' => [
                    [
                        'type' => 'info',
                        'title' => 'OFFICE AND INSURANCE POLICY',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => 'Insurance and Billing Responsibilities'],
                            ['type' => 'paragraph', 'text' => 'I understand the office assists with filing and processing claims, but I remain responsible for balances not covered by insurance.'],
                            ['type' => 'paragraph', 'text' => 'I will review my policy for deductibles, frequencies, limitations, and annual maximums, and provide insurance updates as needed.'],
                            ['type' => 'paragraph', 'text' => "I understand:\n- Insurance may not pay for all services.\n- I am responsible for charges for services not paid by insurance.\n- If a claim is denied or not paid timely, I may need to follow up."],
                            ['type' => 'paragraph', 'text' => 'Financial Policy'],
                            ['type' => 'paragraph', 'text' => "Payment is due at time of service unless prior arrangements are approved.\n- If payment is past due:\n  - Interest may apply on unpaid balances.\n  - Accounts may be sent for collection if unresolved."],
                            ['type' => 'checkbox', 'label' => 'Cash or check at date of service', 'name' => 'policy_payment_cash_check'],
                            ['type' => 'checkbox', 'label' => 'Debit/Credit card', 'name' => 'policy_payment_card'],
                            ['type' => 'checkbox', 'label' => 'Extended payment plan (on approval)', 'name' => 'policy_payment_plan'],
                            ['type' => 'text', 'label' => 'Other prior arrangement', 'name' => 'policy_payment_other_arrangement', 'class' => 'doc-field-full'],
                            ['type' => 'paragraph', 'text' => 'Appointment Policy'],
                            ['type' => 'paragraph', 'text' => 'I agree to respect reserved appointment times.'],
                            ['type' => 'paragraph', 'text' => 'I will provide at least 48 hours notice for cancellations.'],
                            ['type' => 'paragraph', 'text' => 'No-show/late cancelation may result in a late/no-show fee.'],
                            ['type' => 'paragraph', 'text' => 'I agree to follow the treatment recommendations and schedule and I will continue with the treatment plan unless changed by the office or myself.'],
                            ['type' => 'signature_single', 'label' => 'Patient/Parent/Guardian Signature', 'name' => 'office_policy_signature'],
                            ['type' => 'signature_single', 'label' => 'Date', 'name' => 'office_policy_signature_date'],
                        ],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('doc_library_template_by_key')) {
    function doc_library_template_by_key(string $key): ?array
    {
        foreach (doc_library_templates() as $template) {
            if ((string)($template['key'] ?? '') === $key) {
                return $template;
            }
        }

        return null;
    }
}

if (!function_exists('doc_library_template_options')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function doc_library_template_options(): array
    {
        $templates = doc_library_templates();

        return array_map(
            static fn(array $template): array => [
                'key' => (string)($template['key'] ?? ''),
                'label' => (string)($template['title'] ?? ''),
            ],
            $templates
        );
    }
}
