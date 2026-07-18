<?php declare(strict_types=1); ?>

<script>
(function () {
    const translations = {
        'START MY FREE VENEERS CONSULTATION': 'COMENZAR MI CONSULTA GRATIS DE CARILLAS',
        'Start My Free Veneers Consultation': 'Comenzar Mi Consulta Gratis De Carillas',
        'START MY FREE CONSULTATION': 'COMENZAR MI CONSULTA GRATIS',
        'Start My Free Consultation': 'Comenzar Mi Consulta Gratis',
        'ANSWER A FEW SMILE QUESTIONS INSTEAD': 'RESPONDER UNAS PREGUNTAS SOBRE MI SONRISA',
        'Answer A Few Smile Questions Instead': 'Responder Unas Preguntas Sobre Mi Sonrisa',
        'Request My Free Consultation': 'Solicitar Mi Consulta Gratis',
        'Reserve Your Free Veneers Consultation': 'Reserva Tu Consulta Gratis De Carillas',
        'Elite Smiles': 'Elite Smiles',
        'FREE VENEERS CONSULTATION IN DRAPER': 'CONSULTA GRATIS DE CARILLAS EN DRAPER',
        'Natural-Looking Porcelain Veneers in Draper': 'Carillas De Porcelana De Aspecto Natural En Draper',
        'Natural-Looking Veneers in Draper': 'Carillas De Aspecto Natural En Draper',
        'Compare your options, see what is possible for your smile, and get private guidance from Dr. Walter Meden DDS. Your consultation is complimentary, and 0% financing may be available for qualified patients.': 'Compara tus opciones, descubre lo que es posible para tu sonrisa y recibe orientación privada del Dr. Walter Meden DDS. Tu consulta es gratis, y puede haber financiamiento al 0% para pacientes calificados.',
        'Private consultation, clear options, and a smile plan designed to look believable.': 'Consulta privada, opciones claras y un plan de sonrisa diseñado para verse natural.',
        'Free private consultation with Dr. Walter Meden DDS. 0% financing may be available for qualified patients.': 'Consulta privada gratis con el Dr. Walter Meden DDS. Puede haber financiamiento al 0% para pacientes calificados.',
        'FREE VENEERS CONSULTATION REQUEST': 'SOLICITUD DE CONSULTA GRATIS DE CARILLAS',
        'First name': 'Nombre',
        'First Name': 'Nombre',
        'Last name': 'Apellido',
        'Last Name': 'Apellido',
        '(optional)': '(opcional)',
        'Phone': 'Telefono',
        'Email': 'Email',
        'Preferred contact': 'Contacto preferido',
        'Preferred method of contact': 'Metodo de contacto preferido',
        'Select one': 'Selecciona uno',
        'Text me': 'Texto',
        'Call me': 'Llamada',
        'Call': 'Llamada',
        'Text': 'Texto',
        'Email me': 'Email',
        'Choosing text here does not enroll you in SMS. Use the checkbox below if you want text follow-up.': 'Elegir texto aqui no te inscribe en SMS. Usa la casilla de abajo si quieres seguimiento por texto.',
        'I agree to receive text messages from Elite Smiles about my consultation request, scheduling, reminders, and responses to my questions. Consent is optional and is not required to submit this form. Message frequency may vary. Message and data rates may apply. Reply STOP to opt out, HELP for help. See our': 'Acepto recibir mensajes de texto de Elite Smiles sobre mi solicitud de consulta, programacion, recordatorios y respuestas a mis preguntas. El consentimiento es opcional y no es obligatorio para enviar este formulario. La frecuencia de mensajes puede variar. Pueden aplicar cargos de mensajes y datos. Responde STOP para cancelar, HELP para ayuda. Consulta nuestros',
        'SMS Terms': 'Terminos SMS',
        'SMS Privacy': 'Privacidad SMS',
        'Terms': 'Terminos',
        'Privacy Policy': 'Politica de Privacidad',
        'Takes less than 30 seconds. After you submit, you can review the consultation details while our team follows up using your selected contact method. If you leave the SMS box unchecked, we will use call or email instead of text.': 'Toma menos de 30 segundos. Despues de enviar, podras revisar los detalles de la consulta mientras nuestro equipo te contacta por el metodo que seleccionaste. Si no marcas la casilla de SMS, usaremos llamada o email en lugar de texto.',
        'Free private consult': 'Consulta privada gratis',
        'Dr. Meden review': 'Revision con Dr. Meden',
        '0% options may be available': 'Opciones al 0% pueden estar disponibles',
        'Takes less than 60 seconds. We will text or call to help schedule your free consultation.': 'Toma menos de 60 segundos. Te enviaremos texto o llamaremos para ayudarte a programar tu consulta gratis.',
        'START HERE': 'COMIENZA AQUI',
        'What matters most to you about veneers?': 'Que es lo mas importante para ti sobre las carillas?',
        'Choose the concern that best matches what you want from your consultation.': 'Elige la opcion que mejor describe lo que quieres de tu consulta.',
        'I want veneers that look natural': 'Quiero carillas que se vean naturales',
        'I want to avoid a fake-looking result': 'Quiero evitar un resultado que se vea falso',
        'I want the right cosmetic dentist': 'Quiero el dentista cosmetico correcto',
        'I want to understand if veneers are right for me': 'Quiero saber si las carillas son adecuadas para mi',
        'What kind of result are you hoping for?': 'Que tipo de resultado esperas?',
        'This helps us understand how conservative or refined your ideal outcome should feel.': 'Esto nos ayuda a entender que tan natural o refinado quieres que se sienta tu resultado ideal.',
        'Subtle and very natural-looking': 'Sutil y muy natural',
        'Brighter but still believable': 'Mas blanco pero aun natural',
        'More polished overall': 'Mas pulido en general',
        'I am not sure yet': 'Todavia no estoy seguro/a',
        'What is most important when choosing a cosmetic dentist?': 'Que es lo mas importante al elegir un dentista cosmetico?',
        'Select the factor you care about most right now.': 'Selecciona el factor que mas te importa ahora.',
        'Training and credentials': 'Entrenamiento y credenciales',
        'Natural-looking results': 'Resultados naturales',
        'A thoughtful consultation': 'Una consulta cuidadosa',
        'A doctor I can trust': 'Un doctor en quien puedo confiar',
        'How soon are you hoping to get started?': 'Que tan pronto quieres comenzar?',
        'This helps us understand your timing and the best next step.': 'Esto nos ayuda a entender tu tiempo ideal y el mejor siguiente paso.',
        'As soon as possible': 'Lo antes posible',
        'Within the next 1 to 3 months': 'Dentro de 1 a 3 meses',
        'Within the next 6 months': 'Dentro de los proximos 6 meses',
        'Just researching for now': 'Solo estoy investigando por ahora',
        'Last step: where should we send openings?': 'Ultimo paso: donde te enviamos horarios disponibles?',
        'We will text or call to help schedule your free private consultation with Dr. Meden.': 'Te enviaremos texto o llamaremos para ayudarte a programar tu consulta privada gratis con el Dr. Meden.',
        'No obligation. Our team will follow up with available consultation times.': 'Sin obligacion. Nuestro equipo te contactara con horarios disponibles para la consulta.',
        'You can submit this form without agreeing to SMS. If the checkbox stays unchecked, our team will follow up by call or email instead of text.': 'Puedes enviar este formulario sin aceptar SMS. Si no marcas la casilla, nuestro equipo te contactara por llamada o email en lugar de texto.',
        'What Your Free Veneers Consultation Includes': 'Que Incluye Tu Consulta Gratis De Carillas',
        'This page is built for patients actively comparing veneers in Draper. The short intake helps our team understand your goals, timing, pricing questions, and financing needs before we contact you.': 'Esta pagina es para pacientes que estan comparando carillas en Draper. El formulario corto ayuda a nuestro equipo a entender tus metas, tiempo ideal, preguntas de precio y necesidades de financiamiento antes de contactarte.',
        'Private consultation with Dr. Walter Meden DDS': 'Consulta privada con Dr. Walter Meden DDS',
        'Natural-looking veneers and smile design review': 'Revision de carillas naturales y diseno de sonrisa',
        'Case-by-case pricing conversation': 'Conversacion de precio caso por caso',
        '0% financing review for qualified patients': 'Revision de financiamiento al 0% para pacientes calificados',
        'Ready To See What Veneers Could Look Like For You?': 'Listo/a Para Ver Como Podrian Lucir Las Carillas En Ti?',
        'Start the short intake and our team will text or call to help schedule your free private veneers consultation.': 'Completa el formulario corto y nuestro equipo te enviara texto o llamara para ayudarte a programar tu consulta privada gratis de carillas.',
        'Request Received': 'Solicitud Recibida',
        'Thank you. Rod with Elite Smiles will follow up shortly to help schedule your free consultation.': 'Gracias. Rod de Elite Smiles te contactara pronto para ayudarte a programar tu consulta gratis.',
        'If you opted in to SMS, we may text you about scheduling. You can review what the consultation includes below while our team receives your information.': 'Si aceptaste SMS, podemos enviarte texto sobre la programacion. Puedes revisar abajo lo que incluye la consulta mientras nuestro equipo recibe tu informacion.',
        'Individual results may vary. A consultation is needed to determine the right treatment for your smile.': 'Los resultados individuales pueden variar. Se necesita una consulta para determinar el tratamiento correcto para tu sonrisa.',
        'Elite Smiles by Walter Meden DDS, Draper, Utah. Consultation availability and financing depend on individual qualification and clinical review.': 'Elite Smiles by Walter Meden DDS, Draper, Utah. La disponibilidad de consultas y el financiamiento dependen de la calificacion individual y la revision clinica.'
    };

    const reverseTranslations = Object.fromEntries(Object.entries(translations).map(([en, es]) => [es, en]));
    const languageParam = new URLSearchParams(window.location.search).get('lang');
    const storedLanguage = window.localStorage ? localStorage.getItem('elite_landing_language') : '';
    const browserLanguage = (navigator.language || '').toLowerCase();
    let currentLanguage = languageParam === 'es' || languageParam === 'en'
        ? languageParam
        : (storedLanguage === 'es' || storedLanguage === 'en' ? storedLanguage : (browserLanguage.startsWith('es') ? 'es' : 'en'));

    function translateValue(value, lang) {
        const trimmed = String(value || '').trim();
        if (trimmed === '') return value;
        const translated = lang === 'es' ? translations[trimmed] : reverseTranslations[trimmed];
        if (!translated) return value;
        return String(value).replace(trimmed, translated);
    }

    function walkAndTranslate(lang) {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                const parent = node.parentElement;
                if (!parent || ['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT'].includes(parent.tagName)) {
                    return NodeFilter.FILTER_REJECT;
                }
                return node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(node => {
            if (!node.__eliteOriginalText) node.__eliteOriginalText = node.nodeValue;
            const original = node.__eliteOriginalText;
            node.nodeValue = lang === 'es' ? translateValue(original, 'es') : original;
        });

        document.querySelectorAll('input[placeholder], textarea[placeholder], option').forEach(el => {
            if (!el.dataset.eliteOriginalText) {
                el.dataset.eliteOriginalText = el.tagName === 'OPTION' ? el.textContent : el.getAttribute('placeholder');
            }
            const original = el.dataset.eliteOriginalText || '';
            if (el.tagName === 'OPTION') {
                el.textContent = lang === 'es' ? translateValue(original, 'es') : original;
            } else {
                el.setAttribute('placeholder', lang === 'es' ? translateValue(original, 'es') : original);
            }
        });
    }

    function setPreferredLanguage(lang) {
        currentLanguage = lang === 'es' ? 'es' : 'en';
        if (window.localStorage) localStorage.setItem('elite_landing_language', currentLanguage);
        document.documentElement.lang = currentLanguage;
        document.querySelectorAll('[name="preferred_language"]').forEach(input => {
            input.value = currentLanguage;
        });
        document.querySelectorAll('[data-language-toggle]').forEach(btn => {
            const active = btn.getAttribute('data-language-toggle') === currentLanguage;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('bg-eliteInk', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('bg-white', !active);
            btn.classList.toggle('text-eliteInk', !active);
        });
        walkAndTranslate(currentLanguage);
        if (typeof trackEvent === 'function') {
            trackEvent('language_selected', { language: currentLanguage, landing_page: <?= json_encode((string) ($pageSlug ?? $slug ?? ''), JSON_UNESCAPED_SLASHES) ?> });
        }
    }

    document.addEventListener('click', event => {
        const target = event.target.closest('[data-language-toggle]');
        if (!target) return;
        event.preventDefault();
        setPreferredLanguage(target.getAttribute('data-language-toggle') || 'en');
    });

    window.EliteLandingLanguage = {
        get: () => currentLanguage,
        set: setPreferredLanguage
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setPreferredLanguage(currentLanguage));
    } else {
        setPreferredLanguage(currentLanguage);
    }
})();
</script>
