type Daypart = 'night' | 'morning' | 'afternoon' | 'evening';

const GREETINGS: Record<Daypart | 'anytime', string[]> = {
  night: ['Burning the midnight oil, {name}?', 'Still shipping, {name}?', 'Quiet hours, sharp links, {name}'],
  morning: ['Good morning, {name}', 'Fresh day, fresh links, {name}', 'Coffee first, then links, {name}?'],
  afternoon: ['Good afternoon, {name}', 'What are we sharing today, {name}?', 'Ready when you are, {name}'],
  evening: ['Good evening, {name}', 'One last link, {name}?', 'Wrapping up the day, {name}?'],
  anytime: ['Welcome back, {name}', 'Let’s keep it short, {name}', 'Paste it, share it, {name}'],
};

function daypart(hour: number): Daypart {
  if (hour < 5) return 'night';
  if (hour < 12) return 'morning';
  if (hour < 18) return 'afternoon';
  return 'evening';
}

export function greetingFor(name: string, date = new Date()): string {
  const pool = [...GREETINGS[daypart(date.getHours())], ...GREETINGS.anytime];
  const phrase = pool[Math.floor(Math.random() * pool.length)];

  return phrase.replace('{name}', name);
}
