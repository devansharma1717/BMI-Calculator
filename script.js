/* =========================================================
   VitalCheck — BMI Calculator Frontend Logic
========================================================= */

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('year').textContent = new Date().getFullYear();

  initThemeToggle();
  initBmiForm();
  initContactForm();
  loadHistory();

  document.getElementById('refreshHistory').addEventListener('click', loadHistory);
});

/* ---------------------------------------------------------
   Dark / Light Mode Toggle
--------------------------------------------------------- */
function initThemeToggle() {
  const toggleBtn = document.getElementById('themeToggle');
  const icon = toggleBtn.querySelector('i');
  const savedTheme = localStorage.getItem('vitalcheck-theme');

  if (savedTheme === 'dark') {
    document.body.classList.add('dark-mode');
    icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
  }

  toggleBtn.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('vitalcheck-theme', isDark ? 'dark' : 'light');
    icon.classList.toggle('bi-moon-stars-fill', !isDark);
    icon.classList.toggle('bi-sun-fill', isDark);

    if (bmiChartInstance) updateChartTheme();
  });
}

/* ---------------------------------------------------------
   BMI Form Submission
--------------------------------------------------------- */
function initBmiForm() {
  const form = document.getElementById('bmiForm');
  const errorBox = document.getElementById('formError');
  const calcBtn = document.getElementById('calcBtn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const formData = new FormData(form);
    formData.append('action', 'calculate');

    calcBtn.disabled = true;
    calcBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Calculating...';

    try {
      const res = await fetch('bmi.php?action=calculate', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (!data.success) {
        errorBox.textContent = data.message || 'Something went wrong. Please try again.';
        errorBox.classList.remove('d-none');
      } else {
        displayResult(data);
        loadHistory(); // refresh history + chart after saving
      }
    } catch (err) {
      errorBox.textContent = 'Could not reach the server. Please check your connection.';
      errorBox.classList.remove('d-none');
    } finally {
      calcBtn.disabled = false;
      calcBtn.innerHTML = '<i class="bi bi-calculator-fill"></i> Calculate BMI';
    }
  });
}

function categoryClass(category) {
  const map = {
    'Underweight': 'cat-underweight',
    'Normal': 'cat-normal',
    'Overweight': 'cat-overweight',
    'Obese': 'cat-obese'
  };
  return map[category] || '';
}

function displayResult(data) {
  document.getElementById('resultPlaceholder').classList.add('d-none');
  const resultContent = document.getElementById('resultContent');
  resultContent.classList.remove('d-none');

  document.getElementById('bmiValue').textContent = data.bmi;
  const catEl = document.getElementById('bmiCategory');
  catEl.textContent = data.category;
  catEl.className = 'mt-3 fw-bold ' + categoryClass(data.category);

  document.getElementById('bmiQuote').innerHTML =
    `<i class="bi bi-quote"></i> ${data.quote}`;

  const tipsList = document.getElementById('bmiTips');
  tipsList.innerHTML = '';
  data.tips.forEach(tip => {
    const li = document.createElement('li');
    li.innerHTML = `<i class="bi bi-check2-circle text-success"></i> ${tip}`;
    tipsList.appendChild(li);
  });

  resultContent.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ---------------------------------------------------------
   History Table + Chart
--------------------------------------------------------- */
let bmiChartInstance = null;

async function loadHistory() {
  const tbody = document.getElementById('historyBody');
  tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading history...</td></tr>';

  try {
    const res = await fetch('bmi.php?action=history&limit=20');
    const data = await res.json();

    if (!data.success || data.records.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No records yet. Calculate your first BMI above!</td></tr>';
      renderChart([]);
      return;
    }

    tbody.innerHTML = '';
    data.records.forEach((rec, index) => {
      const tr = document.createElement('tr');
      const badgeClass = 'badge-' + rec.category.toLowerCase();
      tr.innerHTML = `
        <td>${index + 1}</td>
        <td>${formatDate(rec.created_at)}</td>
        <td>${rec.height}</td>
        <td>${rec.weight}</td>
        <td>${rec.age}</td>
        <td class="text-capitalize">${rec.gender}</td>
        <td><strong>${rec.bmi_value}</strong></td>
        <td><span class="badge-category ${badgeClass}">${rec.category}</span></td>
        <td>
          <button class="btn btn-sm btn-outline-danger delete-record" data-id="${rec.id}" title="Delete">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    document.querySelectorAll('.delete-record').forEach(btn => {
      btn.addEventListener('click', () => deleteRecord(btn.dataset.id));
    });

    // Chart expects chronological order (oldest -> newest)
    try {
  renderChart([...data.records].reverse());
} catch (chartError) {
  console.error("BMI chart error:", chartError);
}
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load history.</td></tr>';
  }
}

async function deleteRecord(id) {
  if (!confirm('Delete this BMI record?')) return;

  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', id);

  try {
    const res = await fetch('bmi.php?action=delete', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) loadHistory();
  } catch (err) {
    alert('Could not delete the record. Please try again.');
  }
}

function formatDate(dateStr) {
  const d = new Date(dateStr.replace(' ', 'T'));
  if (isNaN(d)) return dateStr;
  return d.toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

function renderChart(records) {
  const ctx = document.getElementById('bmiChart').getContext('2d');
  const labels = records.map(r => formatDate(r.created_at));
  const values = records.map(r => r.bmi_value);

  if (bmiChartInstance) {
    bmiChartInstance.data.labels = labels;
    bmiChartInstance.data.datasets[0].data = values;
    bmiChartInstance.update();
    return;
  }

  bmiChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'BMI over time',
        data: values,
        fill: true,
        tension: 0.35,
        borderColor: '#1fab6b',
        backgroundColor: 'rgba(31,171,107,0.15)',
        pointBackgroundColor: '#2f7fd6',
        pointRadius: 4,
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { mode: 'index', intersect: false }
      },
      scales: {
        y: { beginAtZero: false, title: { display: true, text: 'BMI' } },
        x: { title: { display: true, text: 'Date' } }
      }
    }
  });
}

function updateChartTheme() {
  if (!bmiChartInstance) return;
  const isDark = document.body.classList.contains('dark-mode');
  const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
  const textColor = isDark ? '#cfe8dd' : '#333';

  bmiChartInstance.options.scales.y.ticks = { color: textColor };
  bmiChartInstance.options.scales.x.ticks = { color: textColor };
  bmiChartInstance.options.scales.y.grid = { color: gridColor };
  bmiChartInstance.options.scales.x.grid = { color: gridColor };
  bmiChartInstance.update();
}

/* ---------------------------------------------------------
   Contact Form (front-end demo only)
--------------------------------------------------------- */
function initContactForm() {
  const form = document.getElementById('contactForm');
  const successBox = document.getElementById('contactSuccess');

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    successBox.classList.remove('d-none');
    form.reset();
    setTimeout(() => successBox.classList.add('d-none'), 4000);
  });
}
