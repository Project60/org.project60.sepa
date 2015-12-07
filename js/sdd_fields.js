CRM.$(function($) {
  'use strict';
  var iban_field = $('label[for=bank_account_number]');
  var bic_field = $('label[for=bank_identification_number]');
  if (iban_field.length) {
    iban_field.text(ts('IBAN'));
  }
  if (bic_field.length) {
    bic_field.text(ts('BIC'));
  }
});
