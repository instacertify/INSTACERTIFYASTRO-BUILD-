import { track } from './leads';

const API_URL = (import.meta.env.PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');
const MAX_BYTES = 5 * 1024 * 1024;
const ALLOWED = /\.(pdf|docx?)$/i;

function showErrors(form: HTMLFormElement, errors: Record<string, string[]>) {
  form.querySelectorAll('[data-error-for]').forEach((el) => (el.textContent = ''));
  form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));

  let first: HTMLInputElement | null = null;
  for (const [field, messages] of Object.entries(errors)) {
    const input = form.elements.namedItem(field) as HTMLInputElement | null;
    input?.setAttribute('aria-invalid', 'true');
    first ??= input;
    const slot = form.querySelector(`[data-error-for="${field}"]`) ?? form.querySelector('[data-form-error]');
    if (slot) slot.textContent = messages[0];
  }
  first?.focus({ preventScroll: true });
  (first?.type === 'file' ? form.querySelector('[data-cv-drop]') : first)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function checkFile(file: File | undefined): string | null {
  if (!file) return 'Please attach your CV.';
  if (!ALLOWED.test(file.name)) return 'Please upload your CV as a PDF or Word document.';
  if (file.size > MAX_BYTES) return 'Your CV must be smaller than 5 MB.';
  return null;
}

function initForm(form: HTMLFormElement) {
  const input = form.querySelector<HTMLInputElement>('input[type="file"][name="cv"]')!;
  const drop = form.querySelector<HTMLElement>('[data-cv-drop]')!;
  const text = drop.querySelector<HTMLElement>('[data-cv-text]')!;
  const emptyText = text.innerHTML;

  const showFile = () => {
    const file = input.files?.[0];
    const error = file ? checkFile(file) : null;
    form.querySelector('[data-error-for="cv"]')!.textContent = error ?? '';
    drop.dataset.filled = String(!!file && !error);
    const size = file && (file.size < 1024 * 1024 ? `${Math.max(1, Math.round(file.size / 1024))} KB` : `${(file.size / 1024 / 1024).toFixed(1)} MB`);
    if (file) text.textContent = `${file.name} (${size}) — click to change`;
    else text.innerHTML = emptyText;
  };
  input.addEventListener('change', showFile);

  ['dragenter', 'dragover'].forEach((type) =>
    drop.addEventListener(type, (e) => {
      e.preventDefault();
      drop.dataset.dragging = 'true';
    }),
  );
  ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => (drop.dataset.dragging = 'false')));
  drop.addEventListener('drop', (e) => {
    e.preventDefault();
    if (!e.dataTransfer?.files.length) return;
    input.files = e.dataTransfer.files;
    showFile();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const errorBox = form.querySelector<HTMLElement>('[data-form-error]')!;
    errorBox.textContent = '';

    const fileError = checkFile(input.files?.[0]);
    if (fileError) return showErrors(form, { cv: [fileError] });
    showErrors(form, {});

    const data = new FormData(form);
    data.set('source_page', location.pathname);
    const utm = sessionStorage.getItem('utm_source');
    if (utm) data.set('utm_source', utm);

    const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]')!;
    const label = submit.innerHTML;
    submit.disabled = true;
    submit.textContent = 'Uploading…';

    try {
      const res = await fetch(`${API_URL}/api/v1/applications`, { method: 'POST', headers: { Accept: 'application/json' }, body: data });
      const body = await res.json().catch(() => ({}));
      if (res.status === 422) return showErrors(form, body.errors ?? {});
      if (res.status === 413) return showErrors(form, { cv: ['Your CV must be smaller than 5 MB.'] });
      if (res.status === 429) {
        errorBox.textContent = 'Too many attempts. Please try again in a few minutes.';
        return;
      }
      if (!res.ok) throw new Error(String(res.status));

      track('application_submit', form.querySelector<HTMLInputElement>('[name="vacancy"]')?.value ?? 'general');
      const success = form.parentElement!.querySelector<HTMLElement>('[data-form-success]')!;
      form.hidden = true;
      success.hidden = false;
      success.focus();
    } catch {
      errorBox.textContent = 'Something went wrong while uploading. Please try again, or email your CV to us.';
    } finally {
      submit.disabled = false;
      submit.innerHTML = label;
    }
  });
}

document.querySelectorAll<HTMLFormElement>('form[data-application-form]').forEach(initForm);
