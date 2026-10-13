import { getSite } from './content';

/** Site settings → Tracking & verification → Verification files, e.g. /google1234abcd.html or /BingSiteAuth.xml. */
const NAME = /^[A-Za-z0-9_-][A-Za-z0-9_.-]{0,95}\.(html|txt|xml)$/;

export async function getVerificationFiles(): Promise<{ name: string; content: string }[]> {
  const files = Object.values((await getSite()).integrations?.verificationFiles ?? {});
  return files
    .map((file) => ({ name: file?.name?.trim() ?? '', content: file?.content ?? '' }))
    .filter((file) => NAME.test(file.name) && file.content);
}
