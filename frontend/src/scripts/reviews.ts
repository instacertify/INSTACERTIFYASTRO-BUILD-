import { initials, loopTrack, orderReviews, reviewsKey, scrollDuration, type Review } from '../lib/reviews';

/*
 * Review blocks are built into the page, then refreshed from the admin's live list, so a review added or edited under
 * Website → Testimonials shows on every page without waiting for the next site build.
 */
const API_URL = (import.meta.env.PUBLIC_API_URL ?? 'http://localhost:8000').replace(/\/$/, '');

function isReview(t: unknown): t is Review {
  const r = t as Review;
  return !!r && typeof r.name === 'string' && typeof r.quote === 'string' && r.quote.trim() !== '' && typeof r.rating === 'number';
}

function fill(card: Element, t: Review) {
  const stars = card.querySelector('[data-stars]');
  const star = stars?.firstElementChild;
  if (stars && star) {
    const rating = Math.max(0, Math.min(5, Math.round(t.rating)));
    stars.replaceChildren(...Array.from({ length: rating }, () => star.cloneNode(true)));
    stars.setAttribute('aria-label', `${t.rating} out of 5 stars`);
  }
  const text: [string, string][] = [
    ['[data-quote]', `“${t.quote}”`],
    ['[data-initials]', initials(t.name)],
    ['[data-name]', t.name],
    ['[data-context]', t.context ?? ''],
  ];
  for (const [selector, value] of text) {
    const el = card.querySelector(selector);
    if (el) el.textContent = value;
  }
}

function render(root: HTMLElement, all: Review[]) {
  const shown = orderReviews(all, (root.dataset.terms ?? '').split(' ').filter(Boolean)).slice(0, Number(root.dataset.limit) || 3);
  const key = reviewsKey(shown);
  const template = root.querySelector<HTMLTemplateElement>('template[data-review-card]');
  const item = template?.content.firstElementChild;
  if (!shown.length || key === root.dataset.key || !item) return;

  const scroll = root.hasAttribute('data-scroll');
  const cards = scroll ? loopTrack(shown) : shown;
  root.querySelectorAll('[data-reviews-list]').forEach((list, copy) => {
    list.replaceChildren(
      ...cards.map((t, i) => {
        const node = item.cloneNode(true) as HTMLElement;
        fill(node, t);
        if (scroll && copy === 0 && i >= shown.length) node.setAttribute('aria-hidden', 'true');
        return node;
      }),
    );
  });
  if (scroll) root.style.setProperty('--reviews-duration', scrollDuration(cards.length));
  root.dataset.key = key;
}

async function refresh() {
  const roots = document.querySelectorAll<HTMLElement>('[data-reviews]');
  if (!roots.length) return;
  try {
    const response = await fetch(`${API_URL}/api/v1/testimonials`, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const list = ((await response.json())?.data ?? []) as unknown[];
    const reviews = Array.isArray(list) ? list.filter(isReview) : [];
    if (reviews.length) roots.forEach((root) => render(root, reviews));
  } catch {
    /* Offline or the API is down: the reviews built into the page stay. */
  }
}

if ('requestIdleCallback' in window) requestIdleCallback(() => void refresh(), { timeout: 3000 });
else setTimeout(() => void refresh(), 1500);
