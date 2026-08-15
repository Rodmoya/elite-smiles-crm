const { chromium } = require('playwright');

const baseUrl = process.env.PATIENT_EXPERIENCE_TEST_URL || 'http://127.0.0.1:8765/patient-experience/kiosk.php?direct=1';
const outputDir = process.env.PATIENT_EXPERIENCE_ARTIFACT_DIR || 'tests/artifacts';

const intakeFields = [
  ['patient_first_name', 'text', 'First name'], ['patient_middle_initial', 'text', 'Middle initial'],
  ['patient_last_name', 'text', 'Last name'], ['patient_dob', 'dob', 'Date of birth'],
  ['patient_ssn', 'ssn', 'Social Security number'],
  ['patient_phone', 'phone', 'Phone'], ['patient_alt_phone', 'phone', 'Alternate phone'],
  ['patient_email', 'email', 'Email'], ['patient_address', 'text', 'Street address'],
  ['patient_city', 'text', 'City'], ['patient_state', 'text', 'State'], ['patient_zip', 'text', 'ZIP code'],
].map(([key, type, label]) => ({ key, type, label, required: !key.includes('middle') && !key.includes('alt') && !key.includes('ssn') }));
intakeFields.push({ key: 'patient_sex', type: 'radio', label: 'Sex', options: ['Female', 'Male', 'Other', 'Prefer not to answer'] });

const consentFields = [
  { key: 'proceed_heading', type: 'heading', label: 'Consent to Proceed' },
  { key: 'proceed_authorization', type: 'paragraph', label: 'I authorize Dr. Walter Meden and designated associates or assistants to perform procedures deemed necessary or advisable to maintain my dental health. This includes preventive, restorative, therapeutic, and surgical dental care.' },
  { key: 'proceed_risks', type: 'paragraph', label: 'I understand that dental treatment, local anesthetic, medications, and procedures may involve expected or unexpected risks. I have had the opportunity to ask questions and voluntarily consent to the proposed care.' },
  { key: 'proceed_patient_name', type: 'text', label: 'Patient name', required: true },
  { key: 'proceed_relationship', type: 'text', label: 'Signer relationship to patient', required: true },
  { key: 'proceed_initials', type: 'digital_initials', label: 'Initials: I have reviewed this consent', required: true },
  { key: 'proceed_ack', type: 'acknowledgement_checkbox', label: 'I have read and consent to the treatment terms above.', required: true },
  { key: 'proceed_signature', type: 'digital_signature', label: 'Signature of patient or legal guardian', required: true },
];

function response(category, fields) {
  return {
    ok: true,
    kiosk_token: 'layout-test',
    session: { id: 1, display_name: 'Stacie Peterson', status: 'in_progress', percent_complete: category === 'consent' ? 55 : 18 },
    form: {
      current_step: category === 'consent' ? 'consent_to_proceed' : 'patient_information',
      steps: ['patient_information', 'medical_history', 'consent_to_proceed', 'final_review'],
      step: { title: category === 'consent' ? 'Consent to Proceed' : 'Patient Information', category, fields },
      answers: {}, signatures: [], review: null,
    },
  };
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
  const cases = [
    { name: 'intake-desktop', width: 1440, height: 1000, data: response('intake', intakeFields) },
    { name: 'consent-desktop', width: 1440, height: 1100, data: response('consent', consentFields) },
    { name: 'intake-mobile', width: 390, height: 844, data: response('intake', intakeFields) },
    { name: 'consent-mobile', width: 390, height: 844, data: response('consent', consentFields) },
  ];

  for (const testCase of cases) {
    const page = await browser.newPage({ viewport: { width: testCase.width, height: testCase.height } });
    const consoleErrors = [];
    page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    await page.route('**/app/api/patient_experience_kiosk.php**', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(testCase.data) }));
    await page.goto(baseUrl, { waitUntil: 'networkidle' });
    await page.locator('#kiosk-form').waitFor();
    const metrics = await page.evaluate(() => ({
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
      h1Count: document.querySelectorAll('h1').length,
      consentLetterheads: document.querySelectorAll('.consent-letterhead').length,
      requiredInitials: document.querySelectorAll('input[aria-label="Initials"][required]').length,
      requiredSignatures: document.querySelectorAll('input[data-signature-input="1"][required]').length,
    }));
    if (metrics.scrollWidth > metrics.clientWidth) throw new Error(`${testCase.name}: horizontal overflow`);
    if (metrics.h1Count !== 1) throw new Error(`${testCase.name}: expected one H1`);
    if (testCase.data.form.step.category === 'consent' && (metrics.consentLetterheads !== 1 || metrics.requiredInitials !== 1 || metrics.requiredSignatures !== 1)) {
      throw new Error(`${testCase.name}: consent contract controls are incomplete`);
    }
    if (consoleErrors.length) throw new Error(`${testCase.name}: ${consoleErrors.join('; ')}`);
    await page.screenshot({ path: `${outputDir}/patient-${testCase.name}.png`, fullPage: true });
    await page.close();
  }
  await browser.close();
  process.stdout.write('Patient Experience browser layout tests passed.\n');
})().catch(error => { console.error(error); process.exit(1); });
