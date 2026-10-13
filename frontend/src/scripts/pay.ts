const API_URL = (import.meta.env.PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');
const REFERENCE = /^IC[A-Z0-9]{14}$/;
const BRAND = '#225175';

interface Payment {
  reference: string;
  status: 'created' | 'paid' | 'failed';
  amountFormatted: string;
  invoiceNumber: string | null;
}

type Checkout =
  | { type: 'razorpay'; key: string; orderId: string; amount: number; currency: string; name: string; description: string; prefill: Record<string, string> }
  | { type: 'stripe'; url: string };

interface RazorpayCheckout {
  open(): void;
  on(event: 'payment.failed', handler: (response: { error?: { description?: string } }) => void): void;
}

declare global {
  interface Window {
    Razorpay?: new (options: Record<string, unknown>) => RazorpayCheckout;
  }
}

type Outcome = 'paid' | 'pending' | 'failed';

/** An error whose text is written for the payer. */
class PayerMessage extends Error {}

const COPY: Record<Outcome, { title: string; text: string }> = {
  paid: {
    title: 'Payment received. Thank you!',
    text: 'We have recorded your payment and told our accounts team. Keep the reference below for your records.',
  },
  pending: {
    title: 'Confirming your payment…',
    text: 'Your bank has not confirmed the payment yet. If money has left your account, it is recorded automatically within a few minutes. Please keep the reference below.',
  },
  failed: {
    title: 'Payment not completed',
    text: 'Nothing was charged. Please try again, or contact us for bank transfer details.',
  },
};

async function api(path: string, body: unknown): Promise<{ status: number; body: any }> {
  const res = await fetch(`${API_URL}/api/v1/${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  });

  return { status: res.status, body: await res.json().catch(() => ({})) };
}

async function confirm(reference: string, input: Record<string, unknown> = {}): Promise<Payment | null> {
  const { status, body } = await api(`payments/${reference}/confirm`, input).catch(() => ({ status: 0, body: {} }));

  return status === 200 ? (body.data as Payment) : null;
}

function loadRazorpay(): Promise<void> {
  if (window.Razorpay) return Promise.resolve();

  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('Razorpay checkout did not load'));
    document.head.append(script);
  });
}

const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

function init(root: HTMLElement) {
  const form = root.querySelector<HTMLFormElement>('[data-pay-form]');
  const result = root.querySelector<HTMLElement>('[data-pay-result]')!;

  function show(outcome: Outcome, payment: Payment | null, text?: string) {
    if (form) form.hidden = true;
    root.querySelector('[data-cannot-pay]')?.setAttribute('hidden', '');
    result.hidden = false;
    result.querySelectorAll<HTMLElement>('[data-pay-icon]').forEach((icon) => (icon.hidden = icon.dataset.payIcon !== outcome));
    result.querySelector('[data-pay-title]')!.textContent = COPY[outcome].title;
    result.querySelector('[data-pay-text]')!.textContent = text ?? COPY[outcome].text;
    const details = result.querySelector<HTMLElement>('[data-pay-details]')!;
    details.hidden = !payment;
    if (payment) {
      details.querySelector('[data-pay-amount]')!.textContent = payment.amountFormatted;
      details.querySelector('[data-pay-reference]')!.textContent = payment.reference;
      details.querySelector('[data-pay-invoice]')!.textContent = payment.invoiceNumber ?? '';
      details.querySelector<HTMLElement>('[data-pay-invoice-row]')!.hidden = !payment.invoiceNumber;
    }
    result.querySelector<HTMLElement>('[data-pay-again]')!.hidden = outcome === 'pending';
    result.focus();
  }

  const showPayment = (payment: Payment | null) =>
    show(payment?.status === 'paid' ? 'paid' : payment?.status === 'failed' ? 'failed' : 'pending', payment);

  /** Back from Stripe's page: the webhook may land a few seconds after the payer does. */
  async function returned(reference: string) {
    show('pending', null, 'Checking with the payment gateway…');
    let payment: Payment | null = null;
    for (let attempt = 0; attempt < 5; attempt++) {
      payment = await confirm(reference);
      if (payment && payment.status !== 'created') break;
      await wait(3000);
    }
    showPayment(payment);
  }

  const params = new URLSearchParams(location.search);
  const reference = params.get('ref');
  if (reference && REFERENCE.test(reference)) {
    history.replaceState(null, '', location.pathname);
    if (params.has('cancelled')) {
      const error = form?.querySelector<HTMLElement>('[data-form-error]');
      if (error) error.textContent = 'Payment cancelled. Nothing was charged; you can try again.';
    } else {
      void returned(reference);
    }
  }

  if (!form) return;
  const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]')!;
  const label = submit.querySelector<HTMLElement>('[data-pay-label]')!;
  const errorBox = form.querySelector<HTMLElement>('[data-form-error]')!;
  const originalLabel = label.textContent;

  const busy = (on: boolean, text = 'Opening secure checkout…') => {
    submit.disabled = on;
    label.textContent = on ? text : originalLabel;
  };

  function setErrors(errors: Record<string, string[]>) {
    form!.querySelectorAll('[data-error-for]').forEach((el) => (el.textContent = ''));
    form!.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    let first: HTMLElement | null = null;
    for (const [field, messages] of Object.entries(errors)) {
      const input = form!.elements.namedItem(field) as HTMLElement | null;
      input?.setAttribute('aria-invalid', 'true');
      first ??= input;
      const slot = form!.querySelector(`[data-error-for="${field}"]`);
      if (slot) slot.textContent = messages[0];
      else errorBox.textContent = messages[0];
    }
    first?.focus();
  }

  function openRazorpay(checkout: Extract<Checkout, { type: 'razorpay' }>, reference: string) {
    let settled = false;
    const razorpay = new window.Razorpay!({
      key: checkout.key,
      order_id: checkout.orderId,
      amount: checkout.amount,
      currency: checkout.currency,
      name: checkout.name,
      description: checkout.description,
      prefill: checkout.prefill,
      notes: { reference },
      theme: { color: BRAND },
      handler: async (response: Record<string, string>) => {
        settled = true;
        show('pending', null, 'Recording your payment…');
        showPayment(await confirm(reference, response));
      },
      modal: {
        ondismiss: () => {
          if (settled) return;
          busy(false);
          errorBox.textContent = errorBox.textContent || 'Payment window closed. Nothing was charged; you can try again.';
        },
      },
    });
    razorpay.on('payment.failed', (response) => {
      errorBox.textContent = response.error?.description
        ? `${response.error.description} You can try again with another method.`
        : 'The payment did not go through. You can try again with another method.';
    });
    razorpay.open();
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.textContent = '';
    setErrors({});
    const data: Record<string, unknown> = Object.fromEntries(new FormData(form));
    for (const key of Object.keys(data)) if (data[key] === '') delete data[key];
    busy(true);

    try {
      const { status, body } = await api('payments', data);
      if (status === 422) {
        setErrors(body.errors ?? {});
        busy(false);
        return;
      }
      if (status === 429) throw new PayerMessage('Too many attempts. Please wait a minute, or contact us.');
      if (status !== 201) throw new PayerMessage(body.message ?? 'We could not open the payment page. Please try again in a minute, or contact us.');

      const checkout = body.data.checkout as Checkout;
      if (checkout.type === 'stripe') {
        busy(true, 'Taking you to Stripe…');
        location.assign(checkout.url);
        return;
      }
      await loadRazorpay();
      openRazorpay(checkout, body.data.reference);
    } catch (error) {
      errorBox.textContent = error instanceof PayerMessage
        ? error.message
        : 'Something went wrong. Please try again, or contact us for bank transfer details.';
      busy(false);
    }
  });
}

const root = document.querySelector<HTMLElement>('[data-pay]');
if (root) init(root);
