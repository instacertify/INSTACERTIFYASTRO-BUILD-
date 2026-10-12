/** "Icon position" from the admin: where a section's icons, and the text with them, sit in each card. */
export type IconAlign = 'left' | 'center' | 'right';

export const iconAlign = (value: unknown, fallback: IconAlign = 'left'): IconAlign =>
  value === 'left' || value === 'center' || value === 'right' ? value : fallback;

export const textClass: Record<IconAlign, string> = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
};

/** One child of a stretched flex column, e.g. a "View details" link. */
export const selfClass: Record<IconAlign, string> = {
  left: 'self-start',
  center: 'self-center',
  right: 'self-end',
};

/** A flex column (icon above the text). */
export const stackClass: Record<IconAlign, string> = {
  left: 'items-start text-left',
  center: 'items-center text-center',
  right: 'items-end text-right',
};

/** An icon box in normal flow above the text. */
export const boxClass: Record<IconAlign, string> = {
  left: 'mr-auto',
  center: 'mx-auto',
  right: 'ml-auto',
};

/** Icon beside the text: before it, above it (centred), or after it. */
export const rowClass: Record<IconAlign, string> = {
  left: 'flex-row text-left',
  center: 'flex-col items-center text-center',
  right: 'flex-row-reverse text-right',
};
