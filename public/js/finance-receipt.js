(() => {
  'use strict';
  const params = new URLSearchParams(location.search);
  const paymentId = params.get('payment_id');
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const money = value => '₦' + Number(value || 0).toLocaleString('en-NG', {minimumFractionDigits:2, maximumFractionDigits:2});
  const prettyDate = value => {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('en-NG', {dateStyle:'medium', timeStyle:'short'});
  };
  const detail = (label, value, extra = '') => '<div class="detail ' + extra + '"><label>' + esc(label) + '</label><strong>' + esc(value || '—') + '</strong></div>';

  document.getElementById('receiptBackButton')?.addEventListener('click', () => {
    if (window.history.length > 1) window.history.back();
    else window.location.href = './finance.html#payments';
  });

  async function loadReceipt() {
    const host = document.getElementById('receiptDetails');
    if (!paymentId || !/^\d+$/.test(paymentId)) {
      host.textContent = 'A valid payment record is required.';
      return;
    }
    try {
      const url = new URL('./api/finance/index.php', location.href);
      url.searchParams.set('action', 'receipt');
      url.searchParams.set('payment_id', paymentId);
      const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load receipt.');
      const payment = data.payment || {};
      const methodLabels = {cash:'Cash', bank_transfer:'Bank transfer', pos:'POS', online:'Online payment', other:'Other'};
      const method = methodLabels[payment.method] || 'Other';
      const status = String(payment.status || 'pending');
      document.title = 'Receipt ' + payment.receipt_number + ' | MARKAZ';
      const statusNode = document.getElementById('receiptStatus');
      statusNode.textContent = status === 'confirmed' ? 'Confirmed payment' : status === 'rejected' ? 'Rejected payment' : 'Awaiting verification';
      statusNode.classList.toggle('pending', status === 'pending');
      statusNode.classList.toggle('rejected', status === 'rejected');

      const bankDetail = payment.method === 'bank_transfer'
        ? detail('Bank', payment.bank_name || 'Not recorded')
        : '';
      const verifier = payment.verified_by_name || (status === 'confirmed' ? 'Confirmation recorded' : 'Awaiting confirmation');
      const verifiedAt = payment.verified_at ? prettyDate(payment.verified_at) : (status === 'confirmed' ? 'Time not recorded' : 'Awaiting confirmation');
      host.innerHTML = [
        detail('Receipt number', payment.receipt_number),
        detail('Payment date', prettyDate(payment.paid_at)),
        detail('Student name', payment.student_name, 'full'),
        detail('Student ID / admission number', payment.admission_number),
        detail('Payer name', payment.payer_name || 'Not specified'),
        detail('Payment method', method),
        bankDetail,
        detail('Transaction reference', payment.reference || '—'),
        detail('Received / recorded by', payment.recorded_by_name || 'MARKAZ Finance Office'),
        detail('Confirmed by', verifier),
        detail('Confirmation date', verifiedAt),
        detail('Payment status', status),
        detail('Amount received', money(payment.amount), 'amount')
      ].join('');

      const allocations = Array.isArray(data.allocations) ? data.allocations : [];
      document.getElementById('receiptAllocations').innerHTML = allocations.map(row =>
        '<tr><td>' + esc(row.invoice_number) + '</td><td>' + esc(row.billing_period || 'Student fee') + '</td><td>' + money(row.amount) + '</td></tr>'
      ).join('') || '<tr><td colspan="3">No invoice allocation is recorded for this payment.</td></tr>';
    } catch (error) {
      host.textContent = error.message || 'The receipt could not be loaded.';
    }
  }
  loadReceipt();
})();
