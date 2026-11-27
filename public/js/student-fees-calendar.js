(() => {
  'use strict';

  const host = document.getElementById('tab-fees');
  if (!host || document.getElementById('studentFeesCalendar')) return;

  const css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = './css/student-fees-calendar.css?v=20261127-calendar2';
  document.head.appendChild(css);

  const periodPanel = document.getElementById('studentFeesAcademicPeriod');
  if (!periodPanel) return;

  const layout = document.createElement('div');
  layout.className = 'student-fees-top-grid';
  layout.setAttribute('aria-label', 'Academic period and calendar');
  periodPanel.parentNode.insertBefore(layout, periodPanel);
  layout.appendChild(periodPanel);

  const calendar = document.createElement('aside');
  calendar.className = 'panel student-fees-calendar';
  calendar.id = 'studentFeesCalendar';
  calendar.setAttribute('aria-label', 'MARKAZ academic calendar');
  calendar.classList.add('is-collapsed');
  calendar.innerHTML = `
    <div class="student-calendar-heading">
      <button type="button" class="student-calendar-toggle" id="studentCalendarToggle" aria-expanded="false" aria-controls="studentCalendarContent">
        <span class="student-calendar-heading-copy"><span class="eyebrow">SCHOOL PLANNER</span><strong><i class="fa-regular fa-calendar-days" aria-hidden="true"></i> Academic calendar</strong><span id="studentCalendarMonthSummary" class="student-calendar-month-summary">Loading calendar…</span></span>
        <i class="fa-solid fa-chevron-down student-calendar-chevron" aria-hidden="true"></i>
      </button>
      <span class="student-calendar-live"><i aria-hidden="true"></i> Live</span>
    </div>
    <div class="student-calendar-content" id="studentCalendarContent" hidden>
    <div class="student-calendar-toolbar">
      <button type="button" class="student-calendar-nav" data-calendar-shift="-1" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
      <h3 id="studentCalendarMonth" aria-live="polite">Loading calendar…</h3>
      <button type="button" class="student-calendar-nav" data-calendar-shift="1" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <div class="student-calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
    <div class="student-calendar-days" id="studentCalendarDays" role="grid" aria-label="Calendar dates"></div>
    <div class="student-calendar-legend">
      <span><i class="term"></i> Term</span><span><i class="exam"></i> Exam</span><span><i class="break"></i> Vacation</span><span><i class="resume"></i> Resumption</span>
    </div>
    <div class="student-calendar-events" id="studentCalendarEvents" aria-live="polite"><p>Loading school dates…</p></div>
    </div>
  `;
  layout.appendChild(calendar);

  const monthLabel = document.getElementById('studentCalendarMonth');
  const monthSummary = document.getElementById('studentCalendarMonthSummary');
  const daysHost = document.getElementById('studentCalendarDays');
  const eventsHost = document.getElementById('studentCalendarEvents');
  const today = new Date();
  const todayKey = toKey(today);
  let month = new Date(today.getFullYear(), today.getMonth(), 1);
  let selected = todayKey;
  let events = [];
  let eventsByDate = new Map();

  function toKey(date) {
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
  }
  function fromKey(key) {
    return new Date(key + 'T12:00:00');
  }
  function safe(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  }
  function prettyDate(key) {
    return fromKey(key).toLocaleDateString('en-NG', {weekday:'long',day:'numeric',month:'long',year:'numeric'});
  }
  function renderEvents() {
    const selectedEvents = eventsByDate.get(selected) || [];
    eventsHost.innerHTML = '<div class="student-calendar-selected-date">' + safe(prettyDate(selected)) + '</div>' +
      (selectedEvents.length ? selectedEvents.map(event =>
        '<article class="student-calendar-event ' + safe(event.type) + '">' +
          '<span class="student-calendar-event-dot"></span><div><strong>' + safe(event.label) + '</strong>' +
          '<small>' + safe(event.term) + ' · ' + safe(event.session) + (event.is_current ? ' · Current term' : '') + '</small></div></article>'
      ).join('') : '<p class="student-calendar-no-event">No academic event is scheduled for this date.</p>');
  }
  function render() {
    const year = month.getFullYear();
    const monthIndex = month.getMonth();
    const formattedMonth = month.toLocaleDateString('en-NG', {month:'long',year:'numeric'});
    monthLabel.textContent = formattedMonth;
    monthSummary.textContent = formattedMonth;
    const firstDay = new Date(year, monthIndex, 1).getDay();
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const cells = [];
    for (let i = 0; i < firstDay; i += 1) cells.push('<span class="student-calendar-empty" aria-hidden="true"></span>');
    for (let day = 1; day <= daysInMonth; day += 1) {
      const key = toKey(new Date(year, monthIndex, day));
      const dayEvents = eventsByDate.get(key) || [];
      const types = [...new Set(dayEvents.map(event => event.type))];
      const classes = ['student-calendar-day'];
      if (key === todayKey) classes.push('is-today');
      if (key === selected) classes.push('is-selected');
      if (dayEvents.length) classes.push('has-events');
      cells.push('<button type="button" role="gridcell" class="' + classes.join(' ') + '" data-calendar-date="' + key + '" aria-label="' + safe(prettyDate(key)) + (dayEvents.length ? ', ' + dayEvents.length + ' school events' : '') + '" aria-pressed="' + (key === selected) + '">' +
        '<span>' + day + '</span>' + (types.length ? '<i class="student-calendar-markers">' + types.map(type => '<b class="' + safe(type) + '"></b>').join('') + '</i>' : '') + '</button>');
    }
    daysHost.innerHTML = cells.join('');
    renderEvents();
  }
  async function loadEvents() {
    try {
      const response = await fetch('./api/finance/academic-calendar.php', {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Calendar could not be loaded.');
      events = Array.isArray(data.events) ? data.events : [];
      eventsByDate = new Map();
      events.forEach(event => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(event.date)) return;
        if (!eventsByDate.has(event.date)) eventsByDate.set(event.date, []);
        eventsByDate.get(event.date).push(event);
      });
      render();
    } catch (error) {
      monthLabel.textContent = 'Calendar unavailable';
      monthSummary.textContent = 'Calendar unavailable';
      daysHost.innerHTML = '<p class="student-calendar-error">' + safe(error.message) + '</p>';
      eventsHost.innerHTML = '<p class="student-calendar-no-event">Set term dates, examination dates, vacation dates and resumption dates in School Settings. The calendar follows those saved dates automatically.</p>';
    }
  }

  calendar.addEventListener('click', event => {
    const toggle = event.target.closest('#studentCalendarToggle');
    if (toggle) {
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!expanded));
      document.getElementById('studentCalendarContent').hidden = expanded;
      calendar.classList.toggle('is-expanded', !expanded);
      calendar.classList.toggle('is-collapsed', expanded);
      return;
    }
    const shift = event.target.closest('[data-calendar-shift]');
    if (shift) {
      month = new Date(month.getFullYear(), month.getMonth() + Number(shift.dataset.calendarShift), 1);
      render();
      return;
    }
    const day = event.target.closest('[data-calendar-date]');
    if (day) {
      selected = day.dataset.calendarDate;
      render();
    }
  });

  loadEvents();
})();
