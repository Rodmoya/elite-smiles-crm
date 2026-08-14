<?php
declare(strict_types=1);

/**
 * Doctor-reviewable organic content shared by every city page.
 * City context is applied separately so treatment facts and local facts do not drift.
 */
return [
    'veneers' => [
        'label' => 'Porcelain Veneers',
        'short_label' => 'Veneers',
        'hero_title' => 'Natural-Looking Porcelain Veneers {location_phrase}',
        'hero_body' => 'Explore a personalized veneer plan designed around facial harmony, proportion, and a smile that still looks like you. Elite Smiles welcomes patients from {city_label} to our Draper practice for a complimentary consultation with Dr. Walter Meden.',
        'hero_image' => 'assets/img/landings/veneers-draper-hero-v1-meta.jpg',
        'image_alt' => 'Natural-looking porcelain veneer smile at Elite Smiles',
        'consultation_title' => 'What your complimentary veneer consultation includes',
        'consultation_items' => [
            'A private review of your smile goals and concerns',
            'A candidacy discussion based on your teeth, gums, and bite',
            'A personalized conversation about shape, shade, and proportion',
            'A clear review of treatment timing and available payment options',
        ],
        'sections' => [
            ['eyebrow' => 'VENEERS OVERVIEW', 'title' => 'What porcelain veneers can—and cannot—change', 'body' => 'Porcelain veneers are thin restorations placed over selected front surfaces of teeth. They may be considered for concerns such as shape, proportion, spacing, wear, or discoloration that has not responded as desired to whitening. Veneers are not the right answer for every smile; gum health, enamel, bite forces, and existing dental work all influence the recommendation.'],
            ['eyebrow' => 'NATURAL DESIGN', 'title' => 'A believable result begins with restraint', 'body' => 'A refined veneer plan considers more than brightness. Tooth length, edge shape, texture, symmetry, facial proportions, and how the smile moves all matter. The goal is not to give every patient the same smile, but to create an improvement that belongs to the individual.'],
            ['eyebrow' => 'PROCESS', 'title' => 'Planning comes before treatment', 'body' => 'Your consultation is used to understand your goals, examine your current smile, and discuss realistic options. If veneers are appropriate, the number of teeth, preparation approach, provisional stage, laboratory process, and final placement are planned for the individual case.'],
            ['eyebrow' => 'LONG-TERM CARE', 'title' => 'Protecting the result matters', 'body' => 'Veneers still require consistent brushing, flossing, professional care, and attention to bite or grinding habits. Longevity varies by patient and depends on oral health, material selection, preparation, bite forces, and ongoing maintenance.'],
        ],
        'faq' => [
            ['question' => 'How do I know whether veneers are right for me?', 'answer' => 'A clinical evaluation is necessary. Dr. Meden will consider your goals, enamel, gum health, bite, existing restorations, and whether a more conservative option could address the concern.'],
            ['question' => 'Will veneers look too white or artificial?', 'answer' => 'Shade is only one part of veneer design. Proportion, translucency, surface texture, edge shape, and harmony with the face help determine whether the result looks believable.'],
            ['question' => 'How many veneers would I need?', 'answer' => 'There is no standard number. The visible smile, symmetry, existing dental work, and the transition between treated and untreated teeth all influence the plan.'],
            ['question' => 'Are veneers reversible?', 'answer' => 'Some veneer plans require enamel preparation and should be considered a long-term dental commitment. The appropriate preparation level can only be determined after an examination.'],
            ['question' => 'Can financing be discussed?', 'answer' => 'Yes. The team can review available payment and financing options after the recommended treatment scope is understood. Approval and terms depend on the financing provider.'],
        ],
    ],
    'implants' => [
        'label' => 'Dental Implants',
        'short_label' => 'Dental Implants',
        'hero_title' => 'Dental Implant Consultations {location_phrase}',
        'hero_body' => 'Dental implants may replace a missing tooth root and support a restoration designed for appearance, stability, and daily function. Patients from {city_label} can visit Elite Smiles in Draper for a doctor-led evaluation of candidacy and treatment options.',
        'hero_image' => 'assets/img/landings/implants-hero-organic.webp',
        'image_alt' => 'Dental implant consultation at Elite Smiles in Draper',
        'consultation_title' => 'What your implant consultation includes',
        'consultation_items' => [
            'A review of missing teeth, function, and smile goals',
            'Evaluation of oral health and potential implant candidacy',
            'Discussion of diagnostic records that may be appropriate',
            'A review of treatment sequence, timing, and financial options',
        ],
        'sections' => [
            ['eyebrow' => 'IMPLANT OVERVIEW', 'title' => 'Replacing a tooth involves more than filling the space', 'body' => 'A dental implant is placed in the jaw to support a replacement tooth or restoration. Planning considers bone support, gum health, bite forces, the position of neighboring teeth, restorative space, medical history, and the final appearance.'],
            ['eyebrow' => 'CANDIDACY', 'title' => 'The right recommendation begins with evaluation', 'body' => 'Not every patient is immediately ready for implant placement. Imaging, periodontal health, bone volume, healing considerations, and personal health factors may affect the treatment sequence or whether another option should be considered.'],
            ['eyebrow' => 'PROCESS', 'title' => 'Implant treatment is completed in planned stages', 'body' => 'Depending on the case, treatment may include removal of a failing tooth, site preparation, implant placement, healing, and restoration. Timing differs from one patient to another and should be discussed after the necessary examination and records.'],
            ['eyebrow' => 'MAINTENANCE', 'title' => 'Implants still require ongoing care', 'body' => 'Implants cannot develop cavities, but the surrounding tissues can develop inflammation or bone loss. Home care, professional maintenance, and management of bite forces remain important for long-term stability.'],
        ],
        'faq' => [
            ['question' => 'Am I a candidate for a dental implant?', 'answer' => 'Candidacy depends on oral health, available bone, medical considerations, bite forces, and the condition of the surrounding teeth and tissues. An examination and appropriate imaging are needed.'],
            ['question' => 'How long does implant treatment take?', 'answer' => 'Timing varies according to healing, whether grafting or extraction is needed, implant stability, and the restorative plan. Your consultation will outline the likely sequence for your case.'],
            ['question' => 'Can one implant replace one missing tooth?', 'answer' => 'A single implant may support one replacement tooth, but the best option depends on the location, available space, bone, bite, and neighboring teeth.'],
            ['question' => 'Do implants require special maintenance?', 'answer' => 'They require careful brushing, cleaning between teeth, professional maintenance, and monitoring of the surrounding tissue and bite.'],
            ['question' => 'Can implant financing be discussed?', 'answer' => 'Yes. Once the recommended scope is known, the team can review available payment and financing options. Approval and terms depend on the financing provider.'],
        ],
    ],
    'all_on_x' => [
        'label' => 'All-on-X Full-Arch Implants',
        'short_label' => 'All-on-X',
        'hero_title' => 'All-on-X Full-Arch Implant Consultations {location_phrase}',
        'hero_body' => 'For patients dealing with extensive tooth loss, failing teeth, or difficulty with removable dentures, a full-arch implant-supported option may be worth exploring. Elite Smiles provides individualized consultations at our Draper practice.',
        'hero_image' => 'assets/img/landings/all-on-x-hero-organic.webp',
        'image_alt' => 'All-on-X full-arch implant consultation at Elite Smiles',
        'consultation_title' => 'What your full-arch consultation includes',
        'consultation_items' => [
            'A review of current teeth, dentures, comfort, and function',
            'Discussion of full-arch treatment goals and candidacy',
            'Review of imaging or records that may be clinically appropriate',
            'A personalized discussion of treatment stages and financial options',
        ],
        'sections' => [
            ['eyebrow' => 'FULL-ARCH OVERVIEW', 'title' => 'A fixed full-arch solution requires comprehensive planning', 'body' => 'All-on-X describes a treatment concept in which multiple implants support a full-arch restoration. The number and position of implants, available bone, bite, restorative space, smile line, health history, and long-term maintenance needs must be evaluated for each patient.'],
            ['eyebrow' => 'CANDIDACY', 'title' => 'The condition of the entire mouth matters', 'body' => 'A full-arch recommendation should follow careful review of remaining teeth, periodontal condition, bone, jaw relationships, medical factors, expectations, and alternatives. Preserving appropriate natural teeth may still be preferable in some situations.'],
            ['eyebrow' => 'TREATMENT JOURNEY', 'title' => 'Understand the stages before making a decision', 'body' => 'Full-arch treatment may involve records, extractions, implant placement, a provisional restoration, healing, and delivery of a definitive restoration. The exact sequence and whether immediate provisional teeth are appropriate depend on clinical findings.'],
            ['eyebrow' => 'LIFE AFTER TREATMENT', 'title' => 'Daily care and professional maintenance remain essential', 'body' => 'Full-arch restorations require cleaning underneath and around the prosthesis, professional maintenance, and monitoring of implants, tissues, components, and bite. Your team should explain these responsibilities before treatment begins.'],
        ],
        'faq' => [
            ['question' => 'Is All-on-X the same as dentures?', 'answer' => 'It is an implant-supported full-arch treatment concept rather than a traditional removable denture. Design, materials, removability by the doctor, and maintenance depend on the individual plan.'],
            ['question' => 'Can teeth be placed the same day?', 'answer' => 'A provisional restoration may be possible in selected cases, but it depends on implant stability, bone, bite, and other clinical factors. It cannot be promised before evaluation.'],
            ['question' => 'How many implants are used?', 'answer' => 'The appropriate number and position are determined by anatomy, forces, restorative design, and the clinician’s plan. The name of the treatment should not be treated as a guaranteed implant count.'],
            ['question' => 'What records are normally needed?', 'answer' => 'Clinical photographs, examination, and three-dimensional imaging may be appropriate. The team will explain which records are needed for your case.'],
            ['question' => 'Are payment options available?', 'answer' => 'The team can discuss payment and financing options after the treatment scope is determined. Approval and terms depend on the financing provider.'],
        ],
    ],
    'smile_makeover' => [
        'label' => 'Smile Makeover',
        'short_label' => 'Smile Makeover',
        'hero_title' => 'Personalized Smile Makeovers {location_phrase}',
        'hero_body' => 'A smile makeover combines the treatments that are appropriate for your goals, oral health, and facial proportions rather than forcing every concern into one procedure. Patients from {city_label} can explore their options with Dr. Walter Meden in Draper.',
        'hero_image' => 'assets/img/landings/smile-makeover-hero-organic.webp',
        'image_alt' => 'Natural smile makeover consultation at Elite Smiles',
        'consultation_title' => 'What your smile makeover consultation includes',
        'consultation_items' => [
            'A private discussion of what you would like to change',
            'A review of oral health, smile proportions, and treatment priorities',
            'Discussion of conservative and comprehensive options',
            'A staged plan that considers timing and financial preferences',
        ],
        'sections' => [
            ['eyebrow' => 'PERSONALIZED PLANNING', 'title' => 'A smile makeover is a plan, not a single procedure', 'body' => 'Depending on the patient, a smile makeover may involve whitening, bonding, veneers, crowns, gum contouring, orthodontic movement, replacement of missing teeth, or treatment of active dental problems. The appropriate combination is determined only after evaluation.'],
            ['eyebrow' => 'PRIORITIES', 'title' => 'Health, function, and appearance must work together', 'body' => 'Cosmetic changes should be planned with the gums, bite, existing restorations, tooth structure, and long-term maintenance in mind. Sometimes the best plan is completed in stages rather than all at once.'],
            ['eyebrow' => 'DESIGN', 'title' => 'The goal is harmony—not a standardized smile', 'body' => 'Tooth shape, visible tooth display, midline, smile curve, shade, texture, and facial features influence design decisions. A thoughtful plan should explain why each recommended treatment contributes to the overall result.'],
            ['eyebrow' => 'DECISION MAKING', 'title' => 'Clear options make treatment easier to evaluate', 'body' => 'Your consultation should distinguish what is necessary for health, what is recommended for function, and what is elective for appearance. Understanding alternatives, limitations, sequence, and maintenance helps you make a more informed decision.'],
        ],
        'faq' => [
            ['question' => 'Which treatments can be part of a smile makeover?', 'answer' => 'The plan may include one or several cosmetic, restorative, periodontal, orthodontic, or implant treatments. Recommendations depend on your examination and goals.'],
            ['question' => 'Do all smile makeovers require veneers?', 'answer' => 'No. Whitening, bonding, orthodontic movement, gum treatment, or restorative care may be more appropriate for some patients.'],
            ['question' => 'Can treatment be completed in phases?', 'answer' => 'Often, yes. A staged plan may help address health and function first while organizing cosmetic priorities around timing and budget.'],
            ['question' => 'How is the final appearance planned?', 'answer' => 'Planning may use photographs, measurements, records, discussion of preferences, and previews or provisionals when appropriate to the case.'],
            ['question' => 'Can I discuss budget during the consultation?', 'answer' => 'Yes. Once appropriate options are identified, the team can explain scope, sequencing, and available payment or financing options.'],
        ],
    ],
    'lip_repositioning' => [
        'label' => 'Lip Repositioning',
        'short_label' => 'Lip Repositioning',
        'hero_title' => 'Lip Repositioning Consultations {location_phrase}',
        'hero_body' => 'If excessive gum display affects how you feel about your smile, lip repositioning may be one option to evaluate. Elite Smiles welcomes patients from {city_label} for a personalized assessment of the cause and available treatment approaches.',
        'hero_image' => 'assets/img/landings/veneers-gallery-2-meta.jpg',
        'image_alt' => 'Balanced natural smile consultation at Elite Smiles',
        'consultation_title' => 'What your gummy-smile consultation includes',
        'consultation_items' => [
            'A review of your smile at rest and during natural movement',
            'Evaluation of factors that may contribute to gum display',
            'Discussion of lip repositioning and possible alternatives',
            'A personalized review of timing, recovery, and financial options',
        ],
        'sections' => [
            ['eyebrow' => 'UNDERSTANDING GUM DISPLAY', 'title' => 'A gummy smile can have more than one cause', 'body' => 'Visible gum tissue may relate to lip movement, tooth proportions, gum position, tooth eruption, or jaw relationships. Identifying the contributing factors is important because lip repositioning is not the correct treatment for every type of excessive gum display.'],
            ['eyebrow' => 'PROCEDURE OVERVIEW', 'title' => 'Lip repositioning changes how far the upper lip elevates', 'body' => 'Lip repositioning is a soft-tissue procedure intended to limit excessive upward movement of the upper lip in selected patients. The examination should evaluate smile dynamics, tissue anatomy, expectations, and whether another or combined approach is more appropriate.'],
            ['eyebrow' => 'CANDIDACY', 'title' => 'The diagnosis determines whether the procedure fits', 'body' => 'Candidates should receive an individualized evaluation of gum display, lip mobility, periodontal health, tooth proportions, previous treatment, and medical considerations. Results and stability vary, and limitations should be discussed before treatment.'],
            ['eyebrow' => 'RECOVERY', 'title' => 'Plan for healing and temporary activity adjustments', 'body' => 'Post-treatment instructions may address swelling, diet, oral hygiene, facial movement, medication, and follow-up. The expected recovery and restrictions should be reviewed for your specific procedure before scheduling.'],
        ],
        'faq' => [
            ['question' => 'What causes a gummy smile?', 'answer' => 'Possible contributors include lip movement, gum position, tooth proportions, eruption patterns, and jaw relationships. An examination is needed to identify the relevant factors.'],
            ['question' => 'Is lip repositioning right for every gummy smile?', 'answer' => 'No. Treatment depends on the cause, anatomy, periodontal health, smile dynamics, and goals. Other treatments or combined approaches may be more appropriate.'],
            ['question' => 'Is lip repositioning permanent?', 'answer' => 'Stability varies by technique and patient factors, and some recurrence may occur. Your consultation should include a realistic discussion of expectations and limitations.'],
            ['question' => 'What is recovery like?', 'answer' => 'Recovery instructions and timing vary. Patients may be asked to modify diet, hygiene, and facial movement during early healing and attend follow-up visits.'],
            ['question' => 'Can lip repositioning be combined with other cosmetic treatment?', 'answer' => 'In selected cases it may be coordinated with gum contouring, restorative care, or other treatment, but the sequence must be planned for the individual patient.'],
        ],
    ],
];
