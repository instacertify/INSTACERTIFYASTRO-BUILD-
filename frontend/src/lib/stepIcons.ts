// Process steps entered as plain text get an icon from their wording; the first matching rule wins.
const RULES: [RegExp, string][] = [
  [/^route\b/, 'alt_route'],
  [/\bmark(ing)?\b|label|affix/, 'label'],
  [/factory|on-site|inspection/, 'factory'],
  [/sampl/, 'inventory_2'],
  [/test|\blab\b|laborator|examination/, 'science'],
  [/audit|pre-compliance/, 'fact_check'],
  [/calculat|quantif/, 'calculate'],
  [/quote|numbers/, 'request_quote'],
  [/\bfees?\b|payment/, 'payments'],
  [/certificate|issuance|issued|licen[cs]e|\bcoc\b|allocated|generated/, 'workspace_premium'],
  [/classif|categor|selection|route/, 'category'],
  [/approv|grant|decision/, 'verified'],
  [/review|scrutiny|evaluat|assessment|query|non-conform/, 'fact_check'],
  [/gap|analysis|requirement|identify|legislation|scope|determine|preliminary|consult|check/, 'manage_search'],
  [/standard/, 'rule'],
  [/document|dossier|technical file|\bfile\b|tcf|pif|declaration|compil|prep|\bwrite\b|\bsds\b/, 'description'],
  [/upload/, 'upload_file'],
  [/^(tell us|share|send|call)\b/, 'chat'],
  [/applica|submi|filing/, 'send'],
  [/regist|portal|membership|notif|establishment|sugam/, 'app_registration'],
  [/renew|surveillance|ongoing|post-|follow|support|closing|update|annual/, 'autorenew'],
  [/train/, 'school'],
  [/implement|infrastructure|upgrad|system/, 'construction'],
  [/report|disclos|data|materiality|impact/, 'bar_chart'],
  [/schedul|appoint/, 'event'],
  [/rating|star/, 'star'],
  [/ship|deliver|customs/, 'local_shipping'],
];

export function stepIcon(title: string): string {
  const text = title.toLowerCase();
  return RULES.find(([re]) => re.test(text))?.[1] ?? 'task_alt';
}

const STAT_RULES: [RegExp, string][] = [
  [/customer|client|brand|compan/, 'groups'],
  [/advis|expert|consultant|specialist|team|engineer|chemist/, 'support_agent'],
  [/office|branch|cit(y|ies)|location/, 'location_on'],
  [/countr|global|export|market/, 'public'],
  [/mandatory|required|law|order|icdr|qco|rule/, 'gavel'],
  [/year|since|experience/, 'workspace_premium'],
  [/hour|day|week|turnaround|time|fast/, 'schedule'],
  [/lab|test/, 'science'],
  [/rating|review|star/, 'star'],
  [/certific|licen[cs]e|registration|approv|grant/, 'verified'],
  [/fee|price|₹|cost/, 'payments'],
];

/** Trust-strip numbers entered without an icon get one from their label. */
export function statIcon(label: string): string {
  const text = label.toLowerCase();
  return STAT_RULES.find(([re]) => re.test(text))?.[1] ?? 'verified';
}
