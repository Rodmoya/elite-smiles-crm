<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/doc_library/doc_library_service.php
 *
 * Print-only patient form templates. The legal, medical, and financial
 * wording in this file is transcribed verbatim from the office's current
 * signed paper forms (Dr. Walter Meden, Draper, UT) and must not be
 * paraphrased, shortened, or reworded without office sign-off - only the
 * print layout / spacing may be changed freely.
 */

if (!function_exists('doc_library_templates')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function doc_library_templates(): array
    {
        return [
            [
                'key' => 'welcome_patient_information',
                'title' => 'Welcome',
                'subtitle' => '',
                'sections' => [
                    [
                        'type' => 'info',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => "We are pleased to welcome you to our practice. Please take a few minutes to fill out this form as completely as you can. If you have questions we'll be glad to help you. We look forward to working with you in maintaining your dental health."],
                        ],
                    ],
                    [
                        'title' => 'PATIENT INFORMATION',
                        'rows' => [
                            [['type' => 'date', 'label' => 'Date', 'name' => 'welcome_date'], ['type' => 'text', 'label' => 'Phone', 'name' => 'welcome_day_phone'], ['type' => 'text', 'label' => 'Alt. Phone', 'name' => 'welcome_alt_phone']],
                            [['type' => 'text', 'label' => 'Last Name', 'name' => 'welcome_last_name'], ['type' => 'text', 'label' => 'First Name', 'name' => 'welcome_first_name'], ['type' => 'text', 'label' => 'Middle Initial', 'name' => 'welcome_middle_initial', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'SSN #', 'name' => 'welcome_ssn']],
                            [['type' => 'text', 'label' => 'Address', 'name' => 'welcome_address', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'City', 'name' => 'welcome_city'], ['type' => 'text', 'label' => 'State', 'name' => 'welcome_state', 'size' => 'short'], ['type' => 'text', 'label' => 'Zip', 'name' => 'welcome_zip', 'size' => 'short'], ['type' => 'email', 'label' => 'E-mail', 'name' => 'welcome_email']],
                            [['type' => 'checkbox_row', 'label' => 'Sex', 'name' => 'welcome_sex', 'options' => ['M', 'F'], 'inline' => true, 'size' => 'short'], ['type' => 'text', 'label' => 'Age', 'name' => 'welcome_age', 'size' => 'short'], ['type' => 'date', 'label' => 'Birthdate', 'name' => 'welcome_birthdate']],
                            [['type' => 'checkbox_row', 'label' => 'Marital Status', 'name' => 'welcome_marital_status', 'options' => ['Married', 'Widowed', 'Single', 'Minor', 'Separated', 'Divorced', 'Partnered'], 'inline' => true, 'class' => 'doc-field-full'], ['type' => 'text', 'label' => 'Years (if Partnered)', 'name' => 'welcome_partnered_years', 'size' => 'wide']],
                            [['type' => 'text', 'label' => 'Patient Employer/School', 'name' => 'welcome_employer_school'], ['type' => 'text', 'label' => 'Occupation', 'name' => 'welcome_occupation']],
                            [['type' => 'text', 'label' => 'Employer/School Address', 'name' => 'welcome_employer_school_address'], ['type' => 'text', 'label' => 'Employer/School Phone', 'name' => 'welcome_employer_school_phone']],
                            [['type' => 'text', 'label' => 'Whom may we thank for referring you?', 'name' => 'welcome_referred_by', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'In case of emergency who should be notified?', 'name' => 'welcome_emergency_name'], ['type' => 'text', 'label' => 'Phone', 'name' => 'welcome_emergency_phone']],
                        ],
                    ],
                    [
                        'title' => 'PRIMARY INSURANCE',
                        'rows' => [
                            [['type' => 'text', 'label' => 'Person Responsible for Account - Last Name', 'name' => 'welcome_primary_responsible_last_name'], ['type' => 'text', 'label' => 'First Name', 'name' => 'welcome_primary_responsible_first_name'], ['type' => 'text', 'label' => 'Middle Initial', 'name' => 'welcome_primary_responsible_middle_initial', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'Relation to Patient', 'name' => 'welcome_primary_relation'], ['type' => 'date', 'label' => 'Birthdate', 'name' => 'welcome_primary_responsible_birthdate'], ['type' => 'text', 'label' => 'Soc. Sec. #', 'name' => 'welcome_primary_responsible_ssn']],
                            [['type' => 'text', 'label' => "Address (if different from patient's)", 'name' => 'welcome_primary_responsible_address'], ['type' => 'text', 'label' => 'Phone', 'name' => 'welcome_primary_responsible_phone']],
                            [['type' => 'text', 'label' => 'City', 'name' => 'welcome_primary_responsible_city'], ['type' => 'text', 'label' => 'State', 'name' => 'welcome_primary_responsible_state', 'size' => 'short'], ['type' => 'text', 'label' => 'Zip', 'name' => 'welcome_primary_responsible_zip', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'Person Responsible Employed by', 'name' => 'welcome_primary_responsible_employed_by'], ['type' => 'text', 'label' => 'Occupation', 'name' => 'welcome_primary_responsible_occupation']],
                            [['type' => 'text', 'label' => 'Business Address', 'name' => 'welcome_primary_business_address'], ['type' => 'text', 'label' => 'Business Phone', 'name' => 'welcome_primary_business_phone']],
                            [['type' => 'text', 'label' => 'Insurance Company', 'name' => 'welcome_primary_insurance_company', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Contract #', 'name' => 'welcome_primary_contract'], ['type' => 'text', 'label' => 'Group #', 'name' => 'welcome_primary_group'], ['type' => 'text', 'label' => 'Subscriber #', 'name' => 'welcome_primary_subscriber_number']],
                            [['type' => 'text', 'label' => 'Names of other dependents covered under this plan', 'name' => 'welcome_primary_dependents', 'class' => 'doc-field-full']],
                        ],
                    ],
                    [
                        'title' => 'ADDITIONAL INSURANCE',
                        'rows' => [
                            [['type' => 'checkbox_row', 'label' => 'Is patient covered by additional insurance?', 'name' => 'welcome_has_additional_insurance', 'options' => ['Yes', 'No'], 'inline' => true, 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Subscriber Name', 'name' => 'welcome_additional_subscriber_name'], ['type' => 'date', 'label' => 'Birthdate', 'name' => 'welcome_additional_subscriber_birthdate'], ['type' => 'text', 'label' => 'Relation to Patient', 'name' => 'welcome_additional_subscriber_relation']],
                            [['type' => 'text', 'label' => "Address (if different from patient's)", 'name' => 'welcome_additional_subscriber_address'], ['type' => 'text', 'label' => 'Phone', 'name' => 'welcome_additional_subscriber_phone']],
                            [['type' => 'text', 'label' => 'City', 'name' => 'welcome_additional_subscriber_city'], ['type' => 'text', 'label' => 'State', 'name' => 'welcome_additional_subscriber_state', 'size' => 'short'], ['type' => 'text', 'label' => 'Zip', 'name' => 'welcome_additional_subscriber_zip', 'size' => 'short']],
                            [['type' => 'text', 'label' => 'Subscriber Employed by', 'name' => 'welcome_additional_subscriber_employed_by'], ['type' => 'text', 'label' => 'Business Phone', 'name' => 'welcome_additional_business_phone']],
                            [['type' => 'text', 'label' => 'Insurance Company', 'name' => 'welcome_additional_insurance_company'], ['type' => 'text', 'label' => 'Soc. Sec. #', 'name' => 'welcome_additional_subscriber_ssn']],
                            [['type' => 'text', 'label' => 'Contract #', 'name' => 'welcome_additional_contract'], ['type' => 'text', 'label' => 'Group #', 'name' => 'welcome_additional_group'], ['type' => 'text', 'label' => 'Subscriber #', 'name' => 'welcome_additional_subscriber_number']],
                            [['type' => 'text', 'label' => 'Names of other dependents covered under this plan', 'name' => 'welcome_additional_dependents', 'class' => 'doc-field-full']],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'page_break_before' => true,
                        'title' => 'DENTAL HISTORY',
                        'rows' => [
                            [['type' => 'text', 'label' => "Reason for Today's Visit", 'name' => 'dental_visit_reason'], ['type' => 'date', 'label' => 'Date of last dental care', 'name' => 'dental_last_care_date']],
                            [['type' => 'text', 'label' => 'Former Dentist', 'name' => 'dental_former_dentist'], ['type' => 'date', 'label' => 'Date of last dental X-rays', 'name' => 'dental_last_xray_date']],
                            [['type' => 'text', 'label' => 'Address', 'name' => 'dental_former_dentist_address', 'class' => 'doc-field-full']],
                            [['type' => 'row_title', 'text' => 'Check if you have had problems with any of the following:']],
                            [[
                                'type' => 'checkbox_grid',
                                'name' => 'dental_history_problems',
                                'columns' => 3,
                                'options' => [
                                    'Bad breath', 'Bleeding gums', 'Clicking or popping jaw', 'Food collection between teeth',
                                    'Grinding teeth', 'Loose teeth or broken fillings', 'Periodontal treatment', 'Sensitivity to cold',
                                    'Sensitivity to hot', 'Sensitivity to sweets', 'Sensitivity when biting', 'Sores or growths in your mouth',
                                ],
                            ]],
                            [['type' => 'text', 'label' => 'How often do you floss?', 'name' => 'dental_floss_frequency'], ['type' => 'text', 'label' => 'How often do you brush?', 'name' => 'dental_brush_frequency']],
                        ],
                    ],
                    [
                        'title' => 'MEDICAL HISTORY',
                        'rows' => [
                            [['type' => 'text', 'label' => "Physician's Name", 'name' => 'medical_physician_name'], ['type' => 'date', 'label' => 'Date of Last Visit', 'name' => 'medical_physician_last_visit']],
                            [['type' => 'checkbox_row', 'label' => 'Have you ever used a bisphosphonate medication? Common brand names are Fosamax, Actonel, Atelvia, Didronel, Boniva.', 'name' => 'medical_bisphosphonate', 'options' => ['Yes', 'No'], 'inline' => true, 'class' => 'doc-field-full']],
                            [['type' => 'checkbox_row', 'label' => 'Have you ever taken any of the group of drugs collectively referred to as "fen-phen?" These include combinations of Ionimin, Adipex, Fastin (brand names of phentermine), Pondimin (fenfluramine) and Redux (dexfenfluramine).', 'name' => 'medical_fen_phen', 'options' => ['Yes', 'No'], 'inline' => true, 'class' => 'doc-field-full']],
                            [['type' => 'checkbox_row', 'label' => 'Have you had any serious illnesses or operations?', 'name' => 'medical_serious_illness', 'options' => ['Yes', 'No'], 'inline' => true], ['type' => 'text', 'label' => 'If yes, describe', 'name' => 'medical_serious_illness_detail']],
                            [['type' => 'checkbox_row', 'label' => 'Have you ever had a blood transfusion?', 'name' => 'medical_blood_transfusion', 'options' => ['Yes', 'No'], 'inline' => true], ['type' => 'text', 'label' => 'If yes, give approximate dates', 'name' => 'medical_blood_transfusion_dates']],
                            [['type' => 'checkbox_row', 'label' => '(Women) Are you pregnant?', 'name' => 'medical_pregnant', 'options' => ['Yes', 'No'], 'inline' => true], ['type' => 'checkbox_row', 'label' => 'Nursing?', 'name' => 'medical_nursing', 'options' => ['Yes', 'No'], 'inline' => true], ['type' => 'checkbox_row', 'label' => 'Taking birth control pills?', 'name' => 'medical_birth_control', 'options' => ['Yes', 'No'], 'inline' => true]],
                            [['type' => 'row_title', 'text' => 'Check if you have or have had any of the following:']],
                            [[
                                'type' => 'checkbox_grid',
                                'name' => 'medical_history_conditions',
                                'columns' => 4,
                                'options' => [
                                    'Anemia', 'Arthritis, Rheumatism', 'Artificial Heart Valves', 'Artificial Joints', 'Asthma', 'Back Problems', 'Blood Disease', 'Cancer', 'Chemical Dependency', 'Chemotherapy', 'Circulatory Problems',
                                    'Cortisone Treatments', 'Cough, Persistent', 'Cough up Blood', 'Diabetes', 'Epilepsy', 'Fainting', 'Glaucoma', 'Headaches', 'Heart Murmur', 'Heart Problems', 'Hemophilia',
                                    'Hepatitis', 'High Blood Pressure', 'HIV/AIDS', 'Jaw Pain', 'Kidney Disease', 'Liver Disease', 'Mitral Valve Prolapse', 'Pacemaker', 'Radiation Treatment', 'Respiratory Disease', 'Rheumatic Fever',
                                    'Scarlet Fever', 'Shortness of Breath', 'Skin Rash', 'Stroke', 'Swelling of Feet or Ankles', 'Thyroid Problems', 'Tobacco Habit', 'Tonsillitis', 'Tuberculosis', 'Ulcer', 'Venereal Disease',
                                ],
                            ]],
                            [[
                                'type' => 'write_lines',
                                'label' => 'MEDICATIONS - List medications you are currently taking:',
                                'name' => 'medical_medications',
                                'count' => 2,
                            ], [
                                'type' => 'write_lines',
                                'label' => 'ALLERGIES',
                                'name' => 'medical_allergies',
                                'count' => 2,
                            ]],
                        ],
                    ],
                    [
                        'title' => 'AUTHORIZATION',
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'I certify that I, and/or my dependent(s), have insurance coverage with the company(ies) named below and assign directly to Dr. Walter Meden all insurance benefits, if any, otherwise payable to me for services rendered. I understand that I am financially responsible for all charges whether or not paid by insurance. I authorize the use of my signature on all insurance submissions.']],
                            [['type' => 'text', 'label' => 'Name of Insurance Company(ies)', 'name' => 'authorization_insurance_company', 'class' => 'doc-field-full']],
                            [['type' => 'row_title', 'text' => 'The above-named dentist may use my health care information and may disclose such information to the above-named Insurance Company(ies) and their agents for the purpose of obtaining payment for services and determining insurance benefits or the benefits payable for related services. This consent will end when my current treatment plan is completed or one year from the date signed below.']],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Signature of Patient, Parent, Guardian or Personal Representative', 'left_name' => 'authorization_signature', 'right_label' => 'Date', 'right_name' => 'authorization_signature_date'],
                            ],
                            [['type' => 'text', 'label' => 'Please print name of Patient, Parent, Guardian or Personal Representative', 'name' => 'authorization_printed_name'], ['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'authorization_relationship']],
                            [['type' => 'row_title', 'text' => 'Payment is due in full at time of treatment unless prior arrangements have been approved.']],
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
                        'fields' => [
                            ['type' => 'paragraph', 'text' => 'I authorize Dr. Walter Meden and/or such associates or assistants as he may designate to perform those procedures as may be deemed necessary or advisable to maintain my dental health or the dental health of any minor or other individual for which I have responsibility, including arrangement and/or administration of any sedative (including nitrous oxide), analgesic, therapeutic, and/or other pharmaceutical agent(s), including those related to restorative, therapeutic or surgical treatments.'],
                            ['type' => 'paragraph', 'text' => 'I understand that the administration of local anesthetic may cause an untoward reaction or side effects, which may include, but are not limited to bruising, hematoma, cardiac stimulation, muscle soreness, and temporary or rarely, permanent numbness. I understand that occasionally needles break and may require surgical retrieval. Occasionally drops of local anesthetic may contact the eyes and facial tissues and cause temporary irritation.'],
                            ['type' => 'paragraph', 'text' => 'I understand that as part of the dental treatment, including preventive procedures such as cleanings and basic dentistry, including fillings of all types, teeth may remain sensitive or even possibly quite painful both during and after completion of treatment. Dental materials and medications may trigger allergic or sensitivity reactions.'],
                            ['type' => 'paragraph', 'text' => "After lengthy appointment, jaw muscles may also be sore or tender. Holding one's mouth open can, in a predisposed patient, precipitate a TMJ disorder. Gums and surrounding tissues may also be sensitive or painful during and/or after treatment. Although rare, it is also possible for the tongue, cheek or other oral tissues to be inadvertently abraded or lacerated (cut) during routine dental procedures. In some cases, sutures or additional treatment may be required."],
                            ['type' => 'paragraph', 'text' => 'I understand that as part of dental treatment items including, but not limited to crowns, small dental instruments, drill components, etc. may be aspirated (inhaled into the respiratory system) or swallowed. This unusual situation may require a series of x-rays to be taken by a physician or hospital and may, in rare cases, require bronchoscopy or other procedures to ensure safe removal.'],
                            ['type' => 'paragraph', 'text' => 'I understand the need to disclose to the dentist any prescription drugs that are currently being taken or that have been taken in the past, such as Phen-Fen. I understand that taking the class of drugs prescribed for the prevention of osteoporosis, such as Fosamax, Boniva or Actonel, may result in complications of non-healing of the jaw bones following oral surgery or tooth extractions.'],
                            ['type' => 'paragraph', 'text' => 'I do voluntarily assume any and all possible risks, including the risk of substantial and serious harm, if any, which may be associated with general preventive and operative treatment procedures in hopes of obtaining the potential desired results, which may or may not be achieved, for my benefit or the benefit of my minor child or ward. I acknowledge that the nature and purpose of the foregoing procedures have been explained to me if necessary and I have been given the opportunity to ask questions.'],
                        ],
                    ],
                    [
                        'title' => 'Signatures',
                        'rows' => [
                            [['type' => 'text', 'label' => 'Patient Name', 'name' => 'ctp_patient_name', 'class' => 'doc-field-full']],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Signature of Patient or legal guardian', 'left_name' => 'ctp_patient_guardian_signature', 'right_label' => 'Date', 'right_name' => 'ctp_signature_date'],
                            ],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Witness', 'left_name' => 'ctp_witness', 'right_label' => 'Date', 'right_name' => 'ctp_witness_date'],
                            ],
                            [['type' => 'row_title', 'text' => "WALTER MEDEN D.D.S., P.C.\n11762 STATE STREET, SUITE 300\nDRAPER, UT 84020\n(801) 572-6262"]],
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
                        'fields' => [
                            ['type' => 'paragraph', 'text' => 'I understand that, under the Health Insurance Portability & Accountability Act of 1996 ("HIPAA"), I have certain rights to privacy regarding my protected health information. I understand that this information can and will be used to:'],
                            ['type' => 'paragraph', 'text' => "- Conduct, plan, and direct my treatment and follow-up among the multiple healthcare providers who may be involved in that treatment directly and indirectly.\n- Obtain payment from third-party payers.\n- Conduct normal healthcare operations such as quality assessments and physician certifications."],
                            ['type' => 'paragraph', 'text' => "I have received, read and understand your Notice of Privacy Practices containing a more complete description of the uses and disclosures of my health information. I understand that this organization has the right to change its Notice of Privacy Practices from time to time and that I may contact this organization at any time at the address above to obtain a current copy of the Notice of Privacy Practices."],
                            ['type' => 'paragraph', 'text' => 'I understand that I may request in writing that you restrict how my private information is used or disclosed to carry out treatment, payment or health care operations. I also understand you are not required to agree to my requested restrictions, but if you do agree then you are bound to abide by such restrictions.'],
                        ],
                    ],
                    [
                        'title' => 'Release of Information',
                        'rows' => [
                            [['type' => 'checkbox', 'label' => 'I authorize the release of information including the diagnosis, records, examination rendered to me and claims information. This information may be released to:', 'name' => 'hipaa_authorize_release']],
                            [['type' => 'text', 'label' => 'Name', 'name' => 'hipaa_release_name'], ['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'hipaa_release_relationship']],
                            [['type' => 'checkbox', 'label' => 'Information is not to be released to anyone.', 'name' => 'hipaa_no_release']],
                            [['type' => 'row_title', 'text' => 'This Release of Information will remain in effect until terminated by me in writing.']],
                        ],
                    ],
                    [
                        'title' => 'Signatures',
                        'rows' => [
                            [['type' => 'text', 'label' => 'Patient Name', 'name' => 'hipaa_patient_name', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'hipaa_patient_relationship', 'class' => 'doc-field-full']],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Signature', 'left_name' => 'hipaa_signature', 'right_label' => 'Date', 'right_name' => 'hipaa_signature_date'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Office Use Only',
                        'rows' => [
                            [['type' => 'row_title', 'text' => "We are unable to obtain patient's written acknowledgment of Notice of Privacy Practices due to the following reasons:"]],
                            [['type' => 'checkbox_row', 'name' => 'hipaa_refusal_reason', 'options' => ['Patient refused to sign', 'Communication Barriers', 'Other'], 'inline' => true, 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'If Other, describe', 'name' => 'hipaa_refusal_other_detail', 'class' => 'doc-field-full']],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'office_and_insurance_policy',
                'title' => 'Office and Insurance Policy',
                'subtitle' => 'Importance of patient awareness regarding insurance benefits',
                'sections' => [
                    [
                        'type' => 'info',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => "Dr. Meden realizes how important insurances are. We ask that you carefully review your policy and/or contact your insurance carrier so you are aware of benefits, frequencies, limitations, and/or restrictions. Please be informed that dental insurance is a contract between you and your insurance company. Our role is to assist you with filing your claims. Your dentist is providing the highest quality of care for you and your family regardless of insurance frequencies, limitations and/or restrictions. Please be aware that your insurance may have a yearly allowance (maximum) and anything over the amount will be your responsibility. Your insurance mails a copy of the explanation of benefits (EOB) to you. Please pay attention to these statements. Check your policy to see if you have a dental deductible, and if your insurance pays at a booklet (if available) at your first visit or at the time of dental coverage changes. It is your responsibility to provide us with any future changes in your insurance. If any dental services have been provided with any other provider within the existing benefit year, please advise us."],
                            ['type' => 'initial_line', 'label' => 'I understand the above information'],
                            ['type' => 'heading', 'text' => 'Financial policy'],
                            ['type' => 'paragraph', 'text' => "In order to provide you with the highest quality dental care on a sound business basis, we provide our patients with estimates of fees. Patient, parents and/or guardian are responsible for the patient portion on the date of service. This is not your insurance company's responsibility. However, there may be times that your insurance will not pay for certain procedures but necessary for your dental health. You are responsible for any balance your insurance doesn't pay. We will file all necessary claims to your insurance as a courtesy to you. It is your responsibility to call your insurance company if they have not paid your claim within 45 days from the date of service. Any balance beyond 45 days is your responsibility, and interest will be applied to your account at a rate of 3-5% per month. Accounts over 90 days will be turned over to a collection agency and there will be a collection fee of 4.0% plus any legal fees added to your balance."],
                            ['type' => 'heading', 'text' => 'Financial options that we provide at this time:'],
                            ['type' => 'paragraph', 'text' => "- First time patients must pay by cash or credit card only\n- Cash or check on date of service (established patients only)\n- Major debit or credit card (American Express, Discover, MasterCard, Visa)\n- Extended payment plan (based on credit approval)\n- Other financial arrangements must be done prior to treatment"],
                            ['type' => 'paragraph', 'text' => 'It is your responsibility to complete treatment and follow recommended maintenance schedule. If the treatment and maintenance plans are not followed and/or appointments are missed, adverse results could affect your dental health. If you do not proceed with your treatment plan in a timely manner, further treatment for the involved teeth, supporting tissues, adjacent and opposing teeth, muscles or joints can be affected. No prosthetic case will be seated or delivered unless your balance is paid in full.'],
                            ['type' => 'initial_line', 'label' => 'I understand the above information'],
                            ['type' => 'heading', 'text' => 'Appointment Commitment:'],
                            ['type' => 'paragraph', 'text' => 'We appreciate you choosing us to meet your dental needs. We take this responsibility seriously and have qualified team members to accommodate you during your reserved appointment time. Please review the following: If circumstances occur and it is necessary to change your scheduled appointment, we request that you give us at least 48 hours notice. A broken appointment, one in which a patient does not call or show up is not acceptable. There will be a fee of $75.00 per missed appointment or late cancelation, per provider, per hour.'],
                            ['type' => 'initial_line', 'label' => 'I understand the above information'],
                            ['type' => 'paragraph', 'text' => 'I understand and agree to the information provided, and commit to pay any/all remaining balance on my account.'],
                        ],
                    ],
                    [
                        'rows' => [
                            [
                                ['type' => 'signature_double', 'left_label' => 'Signature of patient, parent or guardian', 'left_name' => 'office_policy_signature', 'right_label' => 'Date', 'right_name' => 'office_policy_signature_date'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'consent_for_photo_image_use',
                'title' => 'Consent for Photo/Image Use',
                'subtitle' => '',
                'sections' => [
                    [
                        'type' => 'info',
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'I, the undersigned, hereby authorize the office of Dr. Walter Meden to use the following images to be placed in a book of case samples, or for marketing or advertising purposes:']],
                            [['type' => 'checkbox_list', 'name' => 'photo_image_authorization', 'options' => ['Before and after pictures of my teeth', 'Before and after pictures of my full face', 'Before and after pictures of the teeth and/or full face of my minor child']]],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'rows' => [
                            [['type' => 'row_title', 'text' => 'By signing this authorization I waive any claims of breach of privacy pertaining to the release of any photographic or digital images as checked above.']],
                            [['type' => 'row_title', 'text' => 'I acknowledge that I have received a copy of the privacy policies of this office.']],
                        ],
                    ],
                    [
                        'rows' => [
                            [
                                ['type' => 'signature_double', 'left_label' => 'Signature of Patient or Parent', 'left_name' => 'photo_patient_signature', 'right_label' => 'Date', 'right_name' => 'photo_patient_signature_date'],
                            ],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Witness Signature (member of office staff)', 'left_name' => 'photo_witness_signature', 'right_label' => 'Date', 'right_name' => 'photo_witness_signature_date'],
                            ],
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
                // New policy drafted for the office - not transcribed from an existing
                // signed form. Recommend office/attorney review before first use.
                'key' => 'no_recording_no_video_policy',
                'title' => 'No Recording / No Video Policy',
                'subtitle' => 'Audio, Video & Photography Consent Acknowledgment',
                'sections' => [
                    [
                        'type' => 'info',
                        'fields' => [
                            ['type' => 'paragraph', 'text' => 'To protect the privacy of our patients and staff and the confidentiality of protected health information, and to maintain a professional treatment environment, audio recording, video recording, live-streaming, and photography of any kind are not permitted anywhere inside the Elite Smiles office - including treatment rooms, consultation areas, hallways, and waiting areas - at any point during a patient visit or any other process conducted on the premises, unless express consent has been given in advance by Dr. Walter Meden for that specific instance.'],
                            ['type' => 'paragraph', 'text' => 'This policy applies to patients, parents and guardians, family members, and any other person accompanying a patient, using any device capable of capturing audio, video, or images, including but not limited to smartphones, tablets, cameras, laptops, smartwatches, and any other recording device.'],
                            ['type' => 'paragraph', 'text' => "This policy does not apply to clinical photographs or video taken by Elite Smiles staff for treatment planning, documentation, or (with separate written authorization) marketing purposes. Those images are governed by the office's separate Consent for Photo/Image Use."],
                            ['type' => 'paragraph', 'text' => 'I understand that Elite Smiles staff may ask any individual who is recording or photographing without consent to stop, and may ask that person to delete the content and/or leave the premises.'],
                            ['type' => 'paragraph', 'text' => 'I understand this policy exists to protect the privacy of all patients and staff and to comply with applicable health information privacy requirements.'],
                            ['type' => 'checkbox', 'label' => 'I have read, understand, and agree to comply with this No Recording / No Video Policy for the duration of my visit(s) to Elite Smiles.', 'name' => 'recording_policy_acknowledgment'],
                        ],
                    ],
                    [
                        'title' => 'Signatures',
                        'rows' => [
                            [['type' => 'text', 'label' => 'Patient Name', 'name' => 'recording_policy_patient_name', 'class' => 'doc-field-full']],
                            [['type' => 'text', 'label' => 'Printed Name of Person Signing (if not patient)', 'name' => 'recording_policy_signer_name'], ['type' => 'text', 'label' => 'Relationship to Patient', 'name' => 'recording_policy_relationship']],
                            [
                                ['type' => 'signature_double', 'left_label' => 'Signature of Patient, Parent, or Guardian', 'left_name' => 'recording_policy_signature', 'right_label' => 'Date', 'right_name' => 'recording_policy_signature_date'],
                            ],
                            [['type' => 'row_title', 'text' => "WALTER MEDEN D.D.S., P.C.\n11762 STATE STREET, SUITE 300\nDRAPER, UT 84020\n(801) 572-6262"]],
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
