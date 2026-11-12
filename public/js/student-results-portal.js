(() => {
  "use strict";

  const form = document.querySelector('.preview-form[data-role="student-lookup"]');
  const experience = document.getElementById('studentPortalExperience');
  if (!form || !experience) return;

  const message = form.querySelector('.role-message');
  const submit = form.querySelector('.role-submit');
  const admission = form.querySelector('#studentId');
  const classField = form.querySelector('#studentClass');
  const termField = form.querySelector('#academicTerm');
  const sessionField = form.querySelector('#academicSession');
  let resultData = null;
  let lockoutTimer = null;

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
    card.setAttribute('role', 'status');
    card.setAttribute('aria-live', 'polite');
    const mark = element('span', 'portal-welcome-mark', '✓');
    mark.setAttribute('aria-hidden', 'true');
    const eyebrow = element('p', 'portal-eyebrow', 'MARKAZ STUDENT RESULTS');
    const heading = element('h2', 'portal-welcome-title', 'Congratulations, ' + resultData.student.name + '!');
    const intro = element('p', 'portal-welcome-copy',
      'Your examination result for ' + resultData.academic_period.term_name + ' · ' +
      resultData.academic_period.session_name + ' is ready.');
    const note = element('p', 'portal-welcome-note', 'May Allah bless your efforts and grant you continued knowledge and success.');
    card.append(mark, eyebrow, heading, intro, note,
      actionButton('Check Results  →', 'portal-primary-action', openOfficialReport));
    experience.appendChild(card);
    document.body.classList.add('student-success-mode');
    experience.hidden = false;
    form.hidden = true;
    message.classList.remove('visible');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const openOfficialReport = () => {
    if (!resultData?.student?.id || !resultData?.student?.class_id) return;
    const query = new URLSearchParams({
      student_id: String(resultData.student.id),
      class_id: String(resultData.student.class_id),
      class_type: 'conventional',
      student_portal: '1',
      print: '1',
      display_version: '20261106-targeted-student-display-fix'
    });
    window.location.assign('student-result-print.html?' + query.toString());
  };

  const resetPortal = () => {
    resultData = null;
    document.body.classList.remove('student-success-mode');
    experience.replaceChildren();
    experience.hidden = true;
    form.hidden = false;
    form.reset();
    message.classList.remove('visible');
    delete message.dataset.kind;
    admission.focus();
  };

  const showLockoutCountdown = lockout => {
    if (lockoutTimer) clearInterval(lockoutTimer);
    let remaining = Math.max(0, Math.floor(Number(lockout.remaining_seconds) || 0));
    submit.disabled = true;
    const render = () => {
      const days = Math.floor(remaining / 86400), hours = Math.floor((remaining % 86400) / 3600);
      const minutes = Math.floor((remaining % 3600) / 60), seconds = remaining % 60;
      showMessage(`Student access is locked. Time remaining: ${days}d ${hours}h ${minutes}m ${seconds}s.`);
      if (remaining <= 0) { clearInterval(lockoutTimer); lockoutTimer = null; submit.disabled = false; showMessage('The lockout period has ended. The server will verify your next lookup.', 'success'); return; }
      remaining -= 1;
    };
    render(); if (remaining > 0) lockoutTimer = setInterval(render, 1000);
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
      if (!response.ok || !data.success) { const error = new Error(data.message || 'No matching released result was found.'); error.loginData = data; throw error; }
      if (!data.student?.name || !Array.isArray(data.subjects) || !data.academic_period) {
        throw new Error('The result record is incomplete. Please contact the school office.');
      }
      resultData = data;
      showWelcome();
    } catch (error) {
      if (error.loginData?.locked && Number(error.loginData.remaining_seconds) > 0) showLockoutCountdown(error.loginData);
      else showMessage(error.message || 'Unable to check results right now. Please try again.');
    } finally {
      if (!lockoutTimer) submit.disabled = false;
      submit.textContent = originalLabel;
    }
  });
})();
