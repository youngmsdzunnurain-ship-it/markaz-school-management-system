const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const css = fs.readFileSync(path.join(__dirname, '../public/css/finance.css'), 'utf8');
const js = fs.readFileSync(path.join(__dirname, '../public/js/finance.js'), 'utf8');

assert.match(css, /#tab-payments\s+\.two-col\s*>\s*\.payment-history-panel\s*\{[^}]*grid-column:\s*1\s*\/\s*-1/s,
  'Recent Payments & Receipts must span the full payments layout width.');
assert.match(css, /#paymentHistory\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s,
  'Payment history items must use one full-width column.');
assert.match(css, /\.payment-history-item\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s,
  'Each payment item must reserve flexible room for the student details.');
assert.match(js, /payment-history-details/, 'Payment rows must use the structured detail layout.');
assert.match(js, /Student ID/, 'Payment rows must identify the student ID.');
assert.match(js, /Payment method/, 'Payment rows must identify the payment method.');
assert.match(js, /Receipt/, 'Payment rows must retain the receipt number.');

console.log('Finance payment history layout regression checks passed.');
