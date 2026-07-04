/* global mfsdRegCompletion */
/**
 * MFSD Home Widgets — Registration Completion widget
 * Step navigation, validation, and AJAX submission to the complete-profile
 * REST endpoint (mfsd-supabase-bridge). Only enqueued for prepurchaseparent
 * users — see includes/frontend.php mfsd_hw_frontend_assets().
 */
(function () {
  'use strict';

  if (!window.mfsdRegCompletion) return;

  const card = document.getElementById('mfsd-hw-regc');
  if (!card) return;

  let childCount = 1;

  const formData = {
    occupation: '',
    address: { line1: '', line2: '', city: '', county: '', postcode: '', country: 'GB' },
    children: [],
    second_carer: null,
  };

  function el(id) { return document.getElementById(id); }
  function val(id) { return (el(id) || {}).value || ''; }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function isValidUkPostcode(postcode) {
    return /^[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}$/i.test(postcode.trim());
  }

  // ── Navigation ──────────────────────────────────────────
  function goToStep(name) {
    card.querySelectorAll('.mfsd-hw-regc-step').forEach(function (s) {
      s.classList.remove('is-active');
    });
    const target = el('mfsd-hw-regc-step-' + name);
    if (target) target.classList.add('is-active');

    requestAnimationFrame(function () {
      const rect = card.getBoundingClientRect();
      const scrollY = window.pageYOffset || document.documentElement.scrollTop;
      window.scrollTo({ top: Math.max(0, rect.top + scrollY - 20), behavior: 'smooth' });
    });
  }

  function setFieldError(inputId, errorId, message) {
    const input = el(inputId);
    const errorEl = el(errorId);
    if (input) input.classList.add('has-error');
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.classList.add('is-visible');
    }
  }

  function clearAllErrors(stepName) {
    const step = el('mfsd-hw-regc-step-' + stepName);
    if (!step) return;
    step.querySelectorAll('.has-error').forEach(function (e) { e.classList.remove('has-error'); });
    step.querySelectorAll('.is-visible.mfsd-hw-regc-field-error').forEach(function (e) { e.classList.remove('is-visible'); });
    const alert = el('mfsd-hw-regc-alert-' + stepName);
    if (alert) alert.classList.remove('is-visible');
  }

  function showAlert(stepName, message) {
    const alert = el('mfsd-hw-regc-alert-' + stepName);
    if (alert) {
      alert.textContent = message;
      alert.classList.add('is-visible');
    }
  }

  // ── Step 1 — Occupation ──────────────────────────────────
  function validateStep1() {
    clearAllErrors(1);
    if (!val('mfsd-hw-regc-occupation')) {
      setFieldError('mfsd-hw-regc-occupation', 'mfsd-hw-regc-err-occupation', 'Please select your occupation.');
      return false;
    }
    return true;
  }

  function onStep1Next() {
    if (!validateStep1()) return;
    const occ = val('mfsd-hw-regc-occupation');
    formData.occupation = (occ === 'Other')
      ? (val('mfsd-hw-regc-occupation-other').trim() || 'Other')
      : occ;
    goToStep(2);
  }

  // ── Step 2 — Address ──────────────────────────────────────
  function validateStep2() {
    clearAllErrors(2);
    let ok = true;
    if (!val('mfsd-hw-regc-address-line1').trim()) {
      setFieldError('mfsd-hw-regc-address-line1', 'mfsd-hw-regc-err-address-line1', 'Please enter your address.');
      ok = false;
    }
    if (!val('mfsd-hw-regc-city').trim()) {
      setFieldError('mfsd-hw-regc-city', 'mfsd-hw-regc-err-city', 'Please enter your town or city.');
      ok = false;
    }
    const postcode = val('mfsd-hw-regc-postcode').trim();
    const country  = val('mfsd-hw-regc-country');
    if (!postcode) {
      setFieldError('mfsd-hw-regc-postcode', 'mfsd-hw-regc-err-postcode', 'Please enter your postcode.');
      ok = false;
    } else if (country === 'GB' && !isValidUkPostcode(postcode)) {
      setFieldError('mfsd-hw-regc-postcode', 'mfsd-hw-regc-err-postcode', 'Please enter a valid UK postcode (e.g. SW1A 1AA).');
      ok = false;
    }
    return ok;
  }

  function onStep2Next() {
    if (!validateStep2()) return;
    formData.address = {
      line1:    val('mfsd-hw-regc-address-line1').trim(),
      line2:    val('mfsd-hw-regc-address-line2').trim(),
      city:     val('mfsd-hw-regc-city').trim(),
      county:   val('mfsd-hw-regc-county').trim(),
      postcode: val('mfsd-hw-regc-postcode').trim().toUpperCase(),
      country:  val('mfsd-hw-regc-country'),
    };
    goToStep(3);
  }

  // ── Step 3 — Children ────────────────────────────────────
  function setChildCount(n) {
    childCount = Math.max(1, Math.min(4, n));
    el('mfsd-hw-regc-child-count-display').textContent = childCount;
    el('mfsd-hw-regc-btn-child-minus').disabled = childCount <= 1;
    el('mfsd-hw-regc-btn-child-plus').disabled  = childCount >= 4;
    for (let i = 1; i <= 4; i++) {
      const row = el('mfsd-hw-regc-child-row-' + i);
      if (row) row.classList.toggle('mfsd-hw-regc-child-row--hidden', i > childCount);
    }
  }

  function validateStep3() {
    clearAllErrors(3);
    let ok = true;
    for (let i = 1; i <= childCount; i++) {
      const nameVal = val('mfsd-hw-regc-child-' + i + '-name').trim();
      const ageVal  = val('mfsd-hw-regc-child-' + i + '-age');
      if (!nameVal) {
        setFieldError('mfsd-hw-regc-child-' + i + '-name', 'mfsd-hw-regc-err-child-' + i + '-name', 'Please enter a name.');
        ok = false;
      }
      if (!ageVal) {
        setFieldError('mfsd-hw-regc-child-' + i + '-age', 'mfsd-hw-regc-err-child-' + i + '-age', 'Please select an age.');
        ok = false;
      }
    }
    return ok;
  }

  function onStep3Next() {
    if (!validateStep3()) return;
    const children = [];
    for (let i = 1; i <= childCount; i++) {
      children.push({
        first_name: val('mfsd-hw-regc-child-' + i + '-name').trim(),
        age:        parseInt(val('mfsd-hw-regc-child-' + i + '-age'), 10),
      });
    }
    formData.children = children;
    goToStep(4);
    updateStep4ButtonStates();
  }

  // ── Step 4 — Second carer (optional) ─────────────────────
  function updateStep4ButtonStates() {
    const skipBtn     = el('mfsd-hw-regc-btn-skip');
    const completeBtn = el('mfsd-hw-regc-btn-complete');
    if (!skipBtn || !completeBtn) return;

    const anyFilled = val('mfsd-hw-regc-carer2-title') ||
                      val('mfsd-hw-regc-carer2-first-name').trim() ||
                      val('mfsd-hw-regc-carer2-surname').trim() ||
                      val('mfsd-hw-regc-carer2-email').trim();
    const emailValid = isValidEmail(val('mfsd-hw-regc-carer2-email').trim());

    if (!anyFilled) {
      skipBtn.disabled     = false;
      completeBtn.disabled = true;
    } else if (!emailValid) {
      skipBtn.disabled     = true;
      completeBtn.disabled = true;
    } else {
      skipBtn.disabled     = true;
      completeBtn.disabled = false;
    }
  }

  function validateStep4SecondCarer() {
    const email = val('mfsd-hw-regc-carer2-email').trim();
    if (email && !isValidEmail(email)) {
      setFieldError('mfsd-hw-regc-carer2-email', 'mfsd-hw-regc-err-carer2-email', 'Please enter a valid email address.');
      return false;
    }
    return true;
  }

  function onStep4Skip() {
    formData.second_carer = null;
    submitCompleteProfile();
  }

  function onStep4Complete() {
    clearAllErrors(4);
    if (!validateStep4SecondCarer()) return;
    const carer2Email = val('mfsd-hw-regc-carer2-email').trim();
    formData.second_carer = carer2Email ? {
      title:      val('mfsd-hw-regc-carer2-title'),
      first_name: val('mfsd-hw-regc-carer2-first-name').trim(),
      surname:    val('mfsd-hw-regc-carer2-surname').trim(),
      email:      carer2Email,
    } : null;
    submitCompleteProfile();
  }

  // ── Submission ────────────────────────────────────────────
  function submitCompleteProfile() {
    const skipBtn     = el('mfsd-hw-regc-btn-skip');
    const completeBtn = el('mfsd-hw-regc-btn-complete');
    if (skipBtn)     skipBtn.disabled = true;
    if (completeBtn) { completeBtn.disabled = true; completeBtn.textContent = 'Saving…'; }

    const payload = {
      occupation: formData.occupation,
      address:    formData.address,
      children:   formData.children,
    };
    if (formData.second_carer) payload.second_carer = formData.second_carer;

    fetch(mfsdRegCompletion.apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   mfsdRegCompletion.nonce,
      },
      body: JSON.stringify(payload),
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok) {
          goToStep('success');
          setTimeout(function () { window.location.reload(); }, 3000);
        } else {
          if (skipBtn)     skipBtn.disabled = false;
          if (completeBtn) { completeBtn.disabled = false; completeBtn.textContent = 'Complete Registration →'; }
          updateStep4ButtonStates();
          showAlert(4, 'Something went wrong. Please try again.');
        }
      })
      .catch(function () {
        if (skipBtn)     skipBtn.disabled = false;
        if (completeBtn) { completeBtn.disabled = false; completeBtn.textContent = 'Complete Registration →'; }
        updateStep4ButtonStates();
        showAlert(4, 'Connection error. Please check your internet connection and try again.');
      });
  }

  // ── Wire up event listeners ──────────────────────────────
  function init() {
    const s1next = el('mfsd-hw-regc-btn-next-1');
    if (s1next) s1next.addEventListener('click', onStep1Next);
    const occSelect = el('mfsd-hw-regc-occupation');
    if (occSelect) occSelect.addEventListener('change', function () {
      const wrap = el('mfsd-hw-regc-occupation-other-wrap');
      const input = el('mfsd-hw-regc-occupation-other');
      if (!wrap) return;
      if (this.value === 'Other') {
        wrap.style.display = '';
        if (input) input.focus();
      } else {
        wrap.style.display = 'none';
        if (input) input.value = '';
      }
    });

    const s2next = el('mfsd-hw-regc-btn-next-2');
    if (s2next) s2next.addEventListener('click', onStep2Next);
    const backTo1 = el('mfsd-hw-regc-btn-back-2');
    if (backTo1) backTo1.addEventListener('click', function () { goToStep(1); });

    const childMinus = el('mfsd-hw-regc-btn-child-minus');
    const childPlus  = el('mfsd-hw-regc-btn-child-plus');
    if (childMinus) childMinus.addEventListener('click', function () { setChildCount(childCount - 1); });
    if (childPlus)  childPlus.addEventListener('click',  function () { setChildCount(childCount + 1); });
    setChildCount(1);

    const s3next = el('mfsd-hw-regc-btn-next-3');
    if (s3next) s3next.addEventListener('click', onStep3Next);
    const backTo2 = el('mfsd-hw-regc-btn-back-3');
    if (backTo2) backTo2.addEventListener('click', function () { goToStep(2); });

    const s4skip     = el('mfsd-hw-regc-btn-skip');
    const s4complete = el('mfsd-hw-regc-btn-complete');
    if (s4skip)     s4skip.addEventListener('click', onStep4Skip);
    if (s4complete) s4complete.addEventListener('click', onStep4Complete);
    const backTo3 = el('mfsd-hw-regc-btn-back-4');
    if (backTo3) backTo3.addEventListener('click', function () { goToStep(3); });
    ['mfsd-hw-regc-carer2-title', 'mfsd-hw-regc-carer2-first-name', 'mfsd-hw-regc-carer2-surname', 'mfsd-hw-regc-carer2-email'].forEach(function (id) {
      const f = el(id);
      if (f) f.addEventListener('input', updateStep4ButtonStates);
      if (f) f.addEventListener('change', updateStep4ButtonStates);
    });
    updateStep4ButtonStates();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
