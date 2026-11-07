(() => {
  "use strict";

  const form = document.querySelector('.preview-form[data-role="student-lookup"]');
  const experience = document.getElementById('studentPortalExperience');
  if (!form || !experience) return;

  const message = form.querySelector('.role-message');
  const submit = form.querySelector('.role-submit');
  const admission = form.querySelector('#admissionNumber');
  const classField = form.querySelector('#studentClass');
  const termField = form.querySelector('#academicTerm');
  const sessionField = form.querySelector('#academicSession');
  let resultData = null;

  const element = (tag, className, value) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (value !== undefined && value !== null) node.textContent = String(value);
    return node;
  };

  const actionButton = (label, className, handler) => {
    const button = element('button', className, label);
    button.type = 'button';
    button.addEventListener('click', handler);
    return button;
  };

  const showMessage = (text, kind = 'error') => {
    message.textContent = text;
    message.dataset.kind = kind;
    message.classList.add('visible');
  };

  const showWelcome = () => {
    experience.replaceChildren();
    const card = element('section', 'portal-welcome-card');
    const mark = element('span', 'portal-welcome-mark', '✦');
    const eyebrow = element('p', 'portal-eyebrow', 'MARKAZ STUDENT RESULTS');
    const heading = element('h2', 'portal-welcome-title', 'Congratulations, ' + resultData.student.name + '!');
    const intro = element('p', 'portal-welcome-copy',
      'Your examination result for ' + resultData.academic_period.term_name + ' · ' +
      resultData.academic_period.session_name + ' is ready. We are pleased to share your academic progress with you.');
    const note = element('p', 'portal-welcome-note', 'May your learning continue to bring you knowledge, confidence and success.');
    card.append(mark, eyebrow, heading, intro, note,
      actionButton('View My Result  →', 'portal-primary-action', openOfficialReport),
      actionButton('Use another student record', 'portal-text-action', resetPortal));
    experience.appendChild(card);
    experience.hidden = false;
    form.hidden = true;
    message.classList.remove('visible');
    experience.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const openOfficialReport = () => {
    if (!resultData?.student?.id || !resultData?.student?.class_id) return;
    const query = new URLSearchParams({
      student_id: String(resultData.student.id),
      class_id: String(resultData.student.class_id),
      class_type: 'conventional',
      student_portal: '1',
      print: '1'
    });
    window.location.assign('student-result-print.html?' + query.toString());
  };

  const resetPortal = () => {
    resultData = null;
    experience.replaceChildren();
    experience.hidden = true;
    form.hidden = false;
    form.reset();
    message.classList.remove('visible');
    delete message.dataset.kind;
    admission.focus();
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    experience.hidden = true;
    experience.replaceChildren();
    message.classList.remove('visible');
    if (!admission.value.trim() || !classField.value || !termField.value || !sessionField.value) {
      showMessage('Please complete all four fields before checking results.');
      return;
    }

    const originalLabel = submit.textContent;
    submit.disabled = true;
    submit.textContent = 'Checking your result…';
    try {
      const response = await fetch('api/results/student-portal.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          admission_number: admission.value.trim(),
          class_id: Number(classField.value),
          term_id: Number(termField.value),
          session_id: Number(sessionField.value)
        })
      });
      let data;
      try { data = await response.json(); }
      catch { throw new Error('The server returned an invalid response. Please try again.'); }
      if (!response.ok || !data.success) throw new Error(data.message || 'No matching released result was found.');
      if (!data.student?.name || !Array.isArray(data.subjects) || !data.academic_period) {
        throw new Error('The result record is incomplete. Please contact the school office.');
      }
      resultData = data;
      showWelcome();
    } catch (error) {
      showMessage(error.message || 'Unable to check results right now. Please try again.');
    } finally {
      submit.disabled = false;
      submit.textContent = originalLabel;
    }
  });
})();
