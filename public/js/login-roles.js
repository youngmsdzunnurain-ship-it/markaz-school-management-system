document.querySelectorAll(".role-password-toggle").forEach((button) => {
  button.addEventListener("click", () => {
    const input = document.getElementById(button.dataset.password);
    if (!input) return;

    const show = input.type === "password";
    input.type = show ? "text" : "password";
    button.innerHTML = show
      ? '<i class="fa-solid fa-eye-slash"></i>'
      : '<i class="fa-solid fa-eye"></i>';
    button.setAttribute("aria-label", show ? "Hide password" : "Show password");
  });
});

document.querySelectorAll('.preview-form[data-role="exam-officer"]').forEach((form) => {
  let countdownTimer = null;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const message = form.querySelector(".role-message");
    const submitButton = form.querySelector(".role-submit");
    const email = form.querySelector('[name="email"]');
    const password = form.querySelector('[name="password"]');

    if (!message || !email || !password || !submitButton) return;
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }

    const originalButtonHtml = submitButton.innerHTML;
    message.textContent = "";
    message.classList.remove("visible");
    submitButton.disabled = true;
    submitButton.textContent = "Signing in...";

    try {
      const response = await fetch("api/auth/login.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: email.value.trim(),
          password: password.value,
          expected_role: 'exam_officer'
        })
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.success) {
        const error = new Error(data.message || "Login failed. Please check your details.");
        error.loginData = data;
        throw error;
      }

      if (data.admin?.role !== "exam_officer") {
        try {
          await fetch("api/auth/logout.php", {
            method: "POST",
            credentials: "same-origin"
          });
        } catch (_) {}

        throw new Error(
          "This account is not authorized for the Exam Officer portal. Please use the correct login page."
        );
      }

      window.location.href = "dashboard.html";
    } catch (error) {
      const lockout = error.loginData;
      submitButton.innerHTML = originalButtonHtml;

      if (lockout?.locked && Number(lockout.remaining_seconds) > 0) {
        let remaining = Math.floor(Number(lockout.remaining_seconds));
        submitButton.disabled = true;

        const showCountdown = () => {
          const days = Math.floor(remaining / 86400);
          const hours = Math.floor((remaining % 86400) / 3600);
          const minutes = Math.floor((remaining % 3600) / 60);
          const seconds = remaining % 60;

          const timeLeft = days > 0
            ? `${days}d ${hours}h ${minutes}m ${seconds}s`
            : hours > 0
              ? `${hours}h ${minutes}m ${seconds}s`
              : `${minutes}m ${seconds}s`;

          message.textContent = `Account temporarily locked. Time remaining: ${timeLeft}. The server will check access when the lock expires.`;
          message.dataset.kind = "error";
      message.classList.add("visible");

          if (remaining <= 0) {
            clearInterval(countdownTimer);
            countdownTimer = null;
            message.textContent = "The displayed lockout period has ended. You can try signing in again.";
            submitButton.disabled = false;
            return;
          }

          remaining -= 1;
        };

        showCountdown();
        countdownTimer = setInterval(showCountdown, 1000);
      } else {
        const attemptsRemaining = Number(lockout?.attempts_remaining);

        message.textContent = Number.isInteger(attemptsRemaining)
          ? `Invalid email or password. ${attemptsRemaining} attempt(s) remaining before a 7-day lockout.`
          : error.message || "Unable to sign in. Please try again.";

        message.dataset.kind = "error";
      message.classList.add("visible");
        submitButton.disabled = false;
      }
    }
  });
});


document.querySelectorAll('.preview-form[data-role="teacher"]').forEach((form) => {
  let countdownTimer = null;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const message = form.querySelector(".role-message");
    const submitButton = form.querySelector(".role-submit");
    const username = form.querySelector('[name="username"]');
    const password = form.querySelector('[name="password"]');

    if (!message || !submitButton || !username || !password) return;
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }

    const originalButtonHtml = submitButton.innerHTML;
    message.textContent = "";
    message.classList.remove("visible");
    submitButton.disabled = true;
    submitButton.textContent = "Signing in...";

    try {
      const response = await fetch("api/auth/login.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: username.value.trim(),
          password: password.value,
          expected_role: 'teacher'
        })
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.success) {
        const error = new Error(data.message || "Login failed. Please check your details.");
        error.loginData = data;
        throw error;
      }

      if (data.admin?.role !== "teacher") {
        try {
          await fetch("api/auth/logout.php", {
            method: "POST",
            credentials: "same-origin"
          });
        } catch (_) {}

        throw new Error("This account is not authorized for the Teacher portal.");
      }

      window.location.href = "dashboard.html";
    } catch (error) {
      submitButton.innerHTML = originalButtonHtml;
      const lockout = error.loginData;

      if (lockout?.locked && Number(lockout.remaining_seconds) > 0) {
        let remaining = Math.floor(Number(lockout.remaining_seconds));
        submitButton.disabled = true;
        const showCountdown = () => {
          const days = Math.floor(remaining / 86400);
          const hours = Math.floor((remaining % 86400) / 3600);
          const minutes = Math.floor((remaining % 3600) / 60);
          const seconds = remaining % 60;
          message.textContent = `Account locked. Time remaining: ${days}d ${hours}h ${minutes}m ${seconds}s.`;
          message.dataset.kind = "error";
          message.classList.add("visible");
          if (remaining <= 0) {
            clearInterval(countdownTimer);
            countdownTimer = null;
            message.textContent = "The displayed lockout period has ended. You can try signing in again.";
            submitButton.disabled = false;
            return;
          }
          remaining -= 1;
        };
        showCountdown();
        countdownTimer = setInterval(showCountdown, 1000);
      } else {
        const remaining = Number(lockout?.attempts_remaining);
        message.textContent = Number.isInteger(remaining)
          ? `Invalid username or password. ${remaining} attempt(s) remaining before a 7-day lockout.`
          : (error.message || "Unable to sign in. Please try again.");
      }

      message.dataset.kind = "error";
      message.classList.add("visible");
      submitButton.disabled = false;
    }
  });
});


document.querySelectorAll('.preview-form[data-role="management"]').forEach((form) => {
  let countdownTimer = null;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const message = form.querySelector(".role-message");
    const submitButton = form.querySelector(".role-submit");
    const email = form.querySelector('[name="email"]');
    const password = form.querySelector('[name="password"]');

    if (!message || !submitButton || !email || !password) return;
    if (countdownTimer) {
      clearInterval(countdownTimer);
      countdownTimer = null;
    }

    const originalButtonHtml = submitButton.innerHTML;
    message.textContent = "";
    message.classList.remove("visible");
    submitButton.disabled = true;
    submitButton.textContent = "Signing in...";

    try {
      const response = await fetch("api/auth/login.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: email.value.trim(),
          password: password.value,
          expected_role: 'management'
        })
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || !data.success) {
        const error = new Error(data.message || "Login failed. Please check your details.");
        error.loginData = data;
        throw error;
      }

      if (data.admin?.role !== "management") {
        try {
          await fetch("api/auth/logout.php", {
            method: "POST",
            credentials: "same-origin"
          });
        } catch (_) {}

        throw new Error(
          "This account is not authorized for the Management portal. Please use the correct login page."
        );
      }

      window.location.href = "dashboard.html";
    } catch (error) {
      submitButton.innerHTML = originalButtonHtml;
      const lockout = error.loginData;

      if (lockout?.locked && Number(lockout.remaining_seconds) > 0) {
        let remaining = Math.floor(Number(lockout.remaining_seconds));

        const showCountdown = () => {
          const days = Math.floor(remaining / 86400);
          const hours = Math.floor((remaining % 86400) / 3600);
          const minutes = Math.floor((remaining % 3600) / 60);
          const seconds = remaining % 60;

          message.textContent = `Account locked. Time remaining: ${days}d ${hours}h ${minutes}m ${seconds}s.`;
          message.dataset.kind = "error";
      message.classList.add("visible");

          if (remaining <= 0) {
            clearInterval(countdownTimer);
            countdownTimer = null;
            message.textContent = "The displayed lockout period has ended. You can try signing in again.";
            submitButton.disabled = false;
            return;
          }

          remaining -= 1;
        };

        showCountdown();
        countdownTimer = setInterval(showCountdown, 1000);
      } else {
        const attemptsRemaining = Number(lockout?.attempts_remaining);
        message.textContent = Number.isInteger(attemptsRemaining)
          ? `Invalid email or password. ${attemptsRemaining} attempt(s) remaining before a 7-day lockout.`
          : (error.message || "Unable to sign in. Please try again.");
        message.dataset.kind = "error";
      message.classList.add("visible");
        submitButton.disabled = false;
      }
    }
  });
});


/* MARKAZ student portal results lookup */
document.querySelectorAll('.preview-form[data-role="student"]').forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const message = form.querySelector('.role-message');
    const submit = form.querySelector('.role-submit');
    const admission = form.querySelector('#admissionNumber');
    const classField = form.querySelector('#studentClass');
    const termField = form.querySelector('#academicTerm');
    const sessionField = form.querySelector('#academicSession');
    const panel = document.getElementById('studentResultsPanel');

    const showMessage = (text, kind = 'error') => {
      message.textContent = text;
      message.dataset.kind = 'error';
      message.classList.add('visible');
      message.dataset.kind = kind;
    };
    const addText = (parent, tag, value, className = '') => {
      const node = document.createElement(tag);
      node.textContent = value == null || value === '' ? '—' : String(value);
      if (className) node.className = className;
      parent.appendChild(node);
      return node;
    };

    panel.replaceChildren();
    panel.hidden = true;
    message.textContent = '';
    message.classList.remove('visible');
    delete message.dataset.kind;

    if (!admission.value.trim() || !classField.value ||
        !termField.value || !sessionField.value) {
      showMessage('Please complete all four fields before checking results.');
      return;
    }

    const originalButton = submit.innerHTML;
    submit.disabled = true;
    submit.textContent = 'Checking results…';

    try {
      const response = await fetch('api/results/student-portal.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          admission_number: admission.value.trim(),
          class_id: Number(classField.value),
          term_id: Number(termField.value),
          session_id: Number(sessionField.value)
        })
      });

      let data;
      try {
        data = await response.json();
      } catch {
        throw new Error('The server returned an invalid response. Please try again.');
      }
      if (!response.ok || !data.success) {
        throw new Error(data.message || data.error || 'No matching results were found.');
      }

      addText(panel, 'h2', 'Your Results', 'student-results-title');
      addText(
        panel, 'p',
        `${data.student.name} · Admission No: ${data.student.admission_number} · ` +
        `${data.student.class_name} · ${data.academic_period.term_name} · ` +
        `${data.academic_period.session_name}`,
        'student-results-details'
      );

      const wrap = document.createElement('div');
      wrap.className = 'student-results-table-wrap';
      const table = document.createElement('table');
      table.className = 'student-results-table';
      const thead = document.createElement('thead');
      const header = document.createElement('tr');
      ['Subject', 'CA1', 'CA2', 'Exam', 'Total', 'Remark'].forEach(v => addText(header, 'th', v));
      thead.appendChild(header);
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      data.subjects.forEach(subject => {
        const row = document.createElement('tr');
        addText(row, 'td', subject.name);
        addText(row, 'td', subject.ca1);
        addText(row, 'td', subject.ca2);
        addText(row, 'td', subject.exam);
        addText(row, 'td', subject.total);
        addText(row, 'td', subject.result_remark || subject.teacher_remark);
        tbody.appendChild(row);
      });
      table.appendChild(tbody);
      wrap.appendChild(table);
      panel.appendChild(wrap);

      const performance = document.createElement('div');
      performance.className = 'student-results-performance';
      addText(performance, 'p', `Total Score: ${data.performance.total_score}`);
      addText(performance, 'p', `Subjects Scored: ${data.performance.subjects_scored}`);
      addText(performance, 'p', `Average: ${data.performance.average ?? '—'}`);
      panel.appendChild(performance);

      if (data.academic_period.examination_date) {
        addText(panel, 'p', `Examination Date: ${data.academic_period.examination_date}`, 'student-results-date');
      }

      panel.hidden = false;
      showMessage('Results loaded successfully.', 'success');
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      showMessage(error.message || 'Unable to check results right now. Please try again.');
    } finally {
      submit.disabled = false;
      submit.innerHTML = originalButton;
    }
  });
});
