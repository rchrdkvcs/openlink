import type { ConversionMeasures, ConversionReport } from '@/lib/analytics';
import { formatNumber } from '@/lib/analyticsFormat';

export function conversionRate(measures: ConversionMeasures): string {
  return measures.rate === null ? '—' : `${measures.rate}%`;
}

export function windowNote(report: Pick<ConversionReport, 'window_hours'>): string {
  const hours = report.window_hours === 1 ? '1 hour' : `${report.window_hours} hours`;
  return `Conversions reported within ${hours} of an attributed visit`;
}

export function evidenceNote(
  report: Pick<ConversionReport, 'minimum_attributed_visits'>,
  row: { insufficient_evidence: boolean },
): string | null {
  return row.insufficient_evidence ? `Fewer than ${report.minimum_attributed_visits} attributed visits` : null;
}

export function pendingNote(pending: number): string | null {
  return pending > 0 ? `${formatNumber(pending)} still in window` : null;
}

export function hasRoutedConversions(report: Pick<ConversionReport, 'routing'>): boolean {
  return report.routing.some((row) => row.routing_rule_id !== null);
}
