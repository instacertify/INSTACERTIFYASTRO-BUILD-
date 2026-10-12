import { hasConsent, onConsent, prefStorage, trackLead } from './consent';

const API_URL = (import.meta.env.PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');
const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid'] as const;

declare global {
  interface Window {
    _paq?: unknown[][];
    dataLayer?: Record<string, unknown>[];
  }
}

function rememberAttribution() {
  if (!hasConsent('analytics')) return;
  const params = new URLSearchParams(location.search);
  for (const key of UTM_KEYS) {
    const value = params.get(key);
    if (value) sessionStorage.setItem(key, value);
  }
  if (!sessionStorage.getItem('first_referrer') && document.referrer && !document.referrer.startsWith(location.origin)) {
    sessionStorage.setItem('first_referrer', document.referrer);
  }
}

export function track(action: string, label?: string) {
  window._paq?.push(['trackEvent', 'Lead', action, label]);
  window.dataLayer?.push({ event: `lead_${action}`, label });
}

function setErrors(form: HTMLFormElement, errors: Record<string, string[]>) {
  form.querySelectorAll('[data-error-for]').forEach((el) => (el.textContent = ''));
  form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));

  let firstInvalid: HTMLElement | null = null;
  for (const [field, messages] of Object.entries(errors)) {
    const input = form.elements.namedItem(field) as HTMLElement | null;
    const slot = form.querySelector(`[data-error-for="${field}"]`);
    if (input) {
      input.setAttribute('aria-invalid', 'true');
      firstInvalid ??= input;
    }
    if (slot) slot.textContent = messages[0];
    else form.querySelector('[data-form-error]')!.textContent = messages[0];
  }

  const step = firstInvalid?.closest<HTMLElement>('[data-step]');
  if (step) showStep(form, Number(step.dataset.step));
  firstInvalid?.focus();
}

function showStep(form: HTMLFormElement, index: number) {
  form.querySelectorAll<HTMLElement>('[data-step]').forEach((step) => {
    step.hidden = Number(step.dataset.step) !== index;
  });
  form.querySelectorAll<HTMLElement>('[data-step-indicator]').forEach((dot) => {
    dot.dataset.active = String(Number(dot.dataset.stepIndicator) <= index);
  });
}

function initForm(form: HTMLFormElement) {
  const preselect = new URLSearchParams(location.search).get('service');
  const known: Record<string, string> = JSON.parse(form.dataset.serviceRequirements ?? '{}');
  if (preselect && known[preselect]) {
    const service = form.querySelector<HTMLInputElement>('input[name="service_path"]');
    const requirement = form.querySelector<HTMLSelectElement>('select[name="requirement"]');
    if (service) service.value = preselect;
    if (requirement) requirement.value = known[preselect];
  }

  let started = false;
  form.addEventListener('focusin', () => {
    if (!started) track('form_start', form.dataset.leadForm);
    started = true;
  });

  form.querySelectorAll<HTMLButtonElement>('[data-next]').forEach((btn) =>
    btn.addEventListener('click', () => {
      const current = btn.closest<HTMLElement>('[data-step]')!;
      const fields = current.querySelectorAll<HTMLInputElement>('input, select, textarea');
      for (const f of fields) if (!f.reportValidity()) return;
      showStep(form, Number(current.dataset.step) + 1);
      track('form_step', `${form.dataset.leadForm}:${Number(current.dataset.step) + 1}`);
      (form.querySelector(`[data-step="${Number(current.dataset.step) + 1}"] input`) as HTMLInputElement | null)?.focus();
    }),
  );
  form.querySelectorAll<HTMLButtonElement>('[data-back]').forEach((btn) =>
    btn.addEventListener('click', () => {
      const current = btn.closest<HTMLElement>('[data-step]')!;
      showStep(form, Number(current.dataset.step) - 1);
    }),
  );

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]')!;
    const errorBox = form.querySelector<HTMLElement>('[data-form-error]')!;
    errorBox.textContent = '';
    delete errorBox.dataset.success;
    setErrors(form, {});

    const data: Record<string, unknown> = Object.fromEntries(new FormData(form));
    const consent = form.querySelector<HTMLInputElement>('[name="consent"]');
    data.consent = consent?.type === 'checkbox' ? consent.checked : consent?.value === '1';
    const marketing = form.querySelector<HTMLInputElement>('[name="marketing_opt_in"]');
    if (marketing) data.marketing_opt_in = marketing.checked;
    data.source_page = location.pathname;
    data.referrer = sessionStorage.getItem('first_referrer') ?? undefined;
    for (const key of UTM_KEYS) data[key] = sessionStorage.getItem(key) ?? undefined;
    for (const key of Object.keys(data)) if (data[key] === '') delete data[key];

    submit.disabled = true;
    const originalLabel = submit.innerHTML;
    submit.textContent = 'Sending…';
    let leaving = false;

    try {
      const res = await fetch(`${API_URL}/api/v1/${form.dataset.endpoint ?? 'leads'}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(data),
      });
      const body = await res.json().catch(() => ({}));

      if (res.status === 422) {
        setErrors(form, body.errors ?? {});
        return;
      }
      if (res.status === 429) {
        errorBox.textContent = 'Too many attempts. Please call or WhatsApp us instead.';
        return;
      }
      if (!res.ok) throw new Error(String(res.status));

      track('form_submit', form.dataset.leadForm);
      trackLead(form.dataset.leadForm);
      prefStorage().setItem('ic_lead_sent', String(Date.now()));
      form.dispatchEvent(new CustomEvent('lead:sent'));
      if (form.dataset.redirect) {
        const next = new URL(form.dataset.redirect, location.origin);
        next.searchParams.set('from', location.pathname);
        // Gives the conversion tags a moment to send before the page unloads.
        setTimeout(() => location.assign(next), 400);
        leaving = true;
        return;
      }
      const success = form.parentElement?.querySelector<HTMLElement>('[data-form-success]');
      if (success) {
        form.hidden = true;
        success.hidden = false;
        success.focus();
      } else {
        form.reset();
        errorBox.dataset.success = 'true';
        errorBox.textContent = body.data?.message ?? 'Thank you!';
      }
    } catch {
      errorBox.textContent = 'Something went wrong. Please call or WhatsApp us — we respond within minutes.';
    } finally {
      if (!leaving) {
        submit.disabled = false;
        submit.innerHTML = originalLabel;
      }
    }
  });
}

onConsent(() => rememberAttribution());
document.querySelectorAll<HTMLFormElement>('form[data-lead-form]').forEach(initForm);
document.querySelectorAll<HTMLAnchorElement>('[data-track]').forEach((a) =>
  a.addEventListener('click', () => track(`click_${a.dataset.track}`, location.pathname)),
);
