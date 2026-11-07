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
      actionButton('View My Result  →', 'portal-primary-action', showReport),
      actionButton('Use another student record', 'portal-text-action', resetPortal));
    experience.appendChild(card);
    experience.hidden = false;
    form.hidden = true;
    message.classList.remove('visible');
    experience.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const showReport = () => {
    if (!resultData) return;
    experience.replaceChildren();
    const sheet = element('section', 'portal-report-sheet');
    const header = element('header', 'portal-report-header');
    const logo = document.createElement('img');
    logo.src = 'assets/images/markaz-logo.jpg';
    logo.alt = 'MARKAZ school logo';
    logo.className = 'portal-report-logo';
    const brand = element('div', 'portal-report-brand');
    brand.append(element('p', 'portal-report-kicker', 'CENTRE FOR LEARNING AND MEMORIZATION OF THE GLORIOUS QUR’AN'),
      element('h2', '', 'MARKAZ'),
      element('p', 'portal-report-address', 'COMPENSATION LAYOUT, OLD KUTUNKU, GWAGWALADA, ABUJA'));
    header.append(logo, brand);
    sheet.append(header);

    const title = element('div', 'portal-report-title');
    title.append(element('span', 'portal-eyebrow', 'OFFICIAL ACADEMIC RECORD'),
      element('h1', '', 'STUDENT RESULT'),
      element('p', '', resultData.academic_period.term_name + ' · ' + resultData.academic_period.session_name));
    sheet.append(title);

    const info = element('div', 'portal-report-student-info');
    [
      ['Student Name', resultData.student.name],
      ['Admission Number', resultData.student.admission_number],
      ['Class', resultData.student.class_name],
      ['Academic Term', resultData.academic_period.term_name],
      ['Academic Session', resultData.academic_period.session_name]
    ].forEach(([label, value]) => {
      const item = element('div', 'portal-report-info-item');
      item.append(element('span', '', label), element('strong', '', value));
      info.appendChild(item);
    });
    sheet.appendChild(info);

    const tableWrap = element('div', 'portal-report-table-wrap');
    const table = element('table', 'portal-report-table');
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    ['Subject', 'CA 1', 'CA 2', 'Exam', 'Total', 'Remark'].forEach(label => headerRow.appendChild(element('th', '', label)));
    thead.appendChild(headerRow);
    table.appendChild(thead);
    const tbody = document.createElement('tbody');
    resultData.subjects.forEach(subject => {
      const row = document.createElement('tr');
      [subject.name, subject.ca1, subject.ca2, subject.exam, subject.total,
        subject.result_remark || subject.teacher_remark || '—'].forEach(value => {
        row.appendChild(element('td', '', value === null || value === '' ? '—' : value));
      });
      tbody.appendChild(row);
    });
    table.appendChild(tbody);
    tableWrap.appendChild(table);
    sheet.appendChild(tableWrap);

    const summary = element('div', 'portal-report-summary');
    [
      ['Total Score', resultData.performance.total_score],
      ['Subjects Scored', resultData.performance.subjects_scored],
      ['Average', resultData.performance.average ?? '—']
    ].forEach(([label, value]) => {
      const item = element('div', 'portal-report-summary-item');
      item.append(element('span', '', label), element('strong', '', value));
      summary.appendChild(item);
    });
    sheet.appendChild(summary);

    if (resultData.academic_period.examination_date) {
      sheet.appendChild(element('p', 'portal-report-date', 'Examination Date: ' + resultData.academic_period.examination_date));
    }
    sheet.appendChild(element('p', 'portal-report-blessing', 'Mashallah. Barakallahu fi. May Allah increase you in beneficial knowledge.'));
    sheet.appendChild(element('p', 'portal-report-footer', '© MARKAZ · Centre for Learning and Memorization of the Glorious Qur’an'));

    const actions = element('div', 'portal-report-actions no-print');
    actions.append(actionButton('Print / Save as PDF', 'portal-primary-action', () => window.print()),
      actionButton('Back to welcome', 'portal-secondary-action', showWelcome));
    experience.append(sheet, actions);
    experience.hidden = false;
    experience.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
