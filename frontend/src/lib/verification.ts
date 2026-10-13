import { getSite } from './content';

/** Site settings → Tracking & verification → Verification files, e.g. /google1234abcd.html or /BingSiteAuth.xml. */
const NAME = /^[A-Za-z0-9_-][A-Za-z0-9_.-]{0,95}\.(html|txt|xml)$/;
const INDEXNOW_KEY = /^[A-Za-z0-9-]{8,128}$/;

export async function getVerificationFiles(): Promise<{ name: string; content: string }[]> {
  const integrations = (await getSite()).integrations;
  const files = Object.values(integrations?.verificationFiles ?? {})
    .map((file) => ({ name: file?.name?.trim() ?? '', content: file?.content ?? '' }))
    .filter((file) => NAME.test(file.name) && file.content);
  // IndexNow engines check that the key is published at /{key}.txt before accepting submitted pages.
  const key = integrations?.indexNowKey?.trim();
  if (key && INDEXNOW_KEY.test(key) && !files.some((f) => f.name === `${key}.txt`)) files.push({ name: `${key}.txt`, content: key });
  return files;
}
