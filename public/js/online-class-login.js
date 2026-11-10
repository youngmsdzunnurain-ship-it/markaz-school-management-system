(() => {
'use strict';
const $ = id => document.getElementById(id);
let portalOptions = { levels: [], sections: [] };

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
  }[char]));
}

function populateSessions() {
  const select = $('onlineSession');
  const currentYear = new Date().getFullYear();
  const firstYear = 2021;
  const lastYear = 2030;
  const currentSessionStart = Math.min(2030, Math.max(2021, new Date().getMonth() >= 8 ? currentYear : currentYear - 1));
  const sessions = [];
  for (let year = firstYear; year <= lastYear; year += 1) {
    sessions.push(year + '/' + (year + 1));
  }
  select.innerHTML = '<option value="">Choose academic session</option>' +
    sessions.map(session => '<option value="' + session + '">' + session + '</option>').join('');
  const preferred = currentSessionStart + '/' + (currentSessionStart + 1);
  select.value = sessions.includes(preferred) ? preferred : '';
}

function updateSections() {
  const levelId = Number($('onlineLevel').value);
  const sections = [...new Set(
    portalOptions.sections
      .filter(item => Number(item.level_id) === levelId)
      .map(item => String(item.section).trim())
      .filter(Boolean)
  )].sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

  const sectionSelect = $('onlineSection');
  sectionSelect.innerHTML = '<option value="">Choose your section</option>' +
    sections.map(section => '<option value="' + escapeHtml(section) + '">' + escapeHtml(section) + '</option>').join('');
  sectionSelect.disabled = !levelId || sections.length === 0;

  if (levelId && sections.length === 0) {
    sectionSelect.innerHTML = '<option value="">No registered section available</option>';
  }
}

async function loadOptions() {
  const response = await fetch('api/online-class/portal-options.php', {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' }
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) {
    throw new Error(data.message || 'Unable to load Online Class levels and sections.');
  }
  if (!Array.isArray(data.levels) || !Array.isArray(data.sections)) {
    throw new Error('Online Class setup is incomplete. Please contact the school administrator.');
  }

  portalOptions = data;
  const levelSelect = $('onlineLevel');
  levelSelect.innerHTML = '<option value="">Choose your level</option>' +
    data.levels.map(level => '<option value="' + Number(level.id) + '">' + escapeHtml(level.name) + '</option>').join('');
  levelSelect.disabled = data.levels.length === 0;
  if (!data.levels.length) {
    levelSelect.innerHTML = '<option value="">No active levels available</option>';
  }
  updateSections();
}

function showMessage(message, kind) {
  const node = $('onlineLoginMessage');
  node.textContent = message;
  node.dataset.kind = kind || 'error';
  node.classList.add('visible');
}

populateSessions();
loadOptions().catch(error => {
  showMessage(error.message, 'error');
});

$('onlineLevel').addEventListener('change', updateSections);
$('onlineLoginForm').addEventListener('submit', async event => {
  event.preventDefault();
  const button = $('onlineLoginButton');
  button.disabled = true;
  button.setAttribute('aria-busy', 'true');
  $('onlineLoginMessage').classList.remove('visible');
  try {
    const response = await fetch('api/online-class/student-login.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        admission_number: $('onlineAdmission').value.trim(),
        level_id: $('onlineLevel').value,
        section: $('onlineSection').value,
        academic_session: $('onlineSession').value,
        term: $('onlineTerm').value
      })
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Unable to check your results. Confirm your details and try again.');
    }
    window.location.replace('online-class-portal.html');
  } catch (error) {
    showMessage(error.message, 'error');
    button.disabled = false;
    button.removeAttribute('aria-busy');
  }
});
})();
