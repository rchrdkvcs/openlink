import type {
  RoutingCondition,
  RoutingConditionType,
  RoutingOperatorGroup,
  RoutingOption,
  RoutingRange,
  RoutingSchema,
} from '@/types/shortLinks';

const VALUELESS_OPERATORS = new Set(['is_empty', 'is_not_empty']);

const PLACEHOLDERS: Record<string, string> = { country: 'e.g. FR', language: 'e.g. fr' };

function conditionType(schema: RoutingSchema, type: string): RoutingConditionType | undefined {
  return schema.conditionTypes.find((option) => option.value === type);
}

function operatorGroup(schema: RoutingSchema, type: string): RoutingOperatorGroup {
  return conditionType(schema, type)?.operatorGroup ?? 'scalar';
}

function copyValue(value: RoutingCondition['value']): RoutingCondition['value'] {
  if (Array.isArray(value)) return [...value];

  return isRange(value) ? { ...value } : value;
}

export function usesTimeOperators(schema: RoutingSchema, type: string): boolean {
  return operatorGroup(schema, type) === 'time';
}

export function isZoned(schema: RoutingSchema, type: string): boolean {
  return conditionType(schema, type)?.zoned === true;
}

export function operatorsFor(schema: RoutingSchema, condition: RoutingCondition): RoutingOption[] {
  return schema.operators[operatorGroup(schema, condition.type)];
}

export function conditionValueOptions(schema: RoutingSchema, condition: RoutingCondition): RoutingOption[] | null {
  return schema.valueOptions[condition.type] ?? null;
}

export function newCondition(schema: RoutingSchema, type: string, timezone: string): RoutingCondition {
  const definition = conditionType(schema, type);
  const condition: RoutingCondition = {
    type,
    operator: definition?.defaultOperator ?? operatorsFor(schema, { type, operator: '' })[0]?.value ?? 'is',
    value:
      definition?.defaultValue !== undefined
        ? copyValue(definition.defaultValue)
        : (schema.defaults[type] ?? schema.valueOptions[type]?.[0]?.value ?? ''),
  };

  return isZoned(schema, type) ? { ...condition, timezone } : condition;
}

export function changeConditionType(
  schema: RoutingSchema,
  condition: RoutingCondition,
  type: string,
  timezone: string,
): void {
  const replacement = newCondition(schema, type, timezone);
  condition.type = type;
  condition.operator = replacement.operator;
  condition.value = replacement.value;
  condition.timezone = replacement.timezone;
}

export function isRange(value: RoutingCondition['value']): value is RoutingRange {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function rangeValue(condition: RoutingCondition): RoutingRange {
  if (isRange(condition.value)) return condition.value;

  const range: RoutingRange = { from: '', to: '' };
  condition.value = range;

  return range;
}

export function changeOperator(condition: RoutingCondition, operator: string): void {
  condition.operator = operator;
  if (operator === 'between') {
    rangeValue(condition);
  } else if (isRange(condition.value)) {
    condition.value = condition.value.from ?? '';
  }
}

export function hasValueInput(condition: RoutingCondition): boolean {
  return !VALUELESS_OPERATORS.has(condition.operator);
}

export function scalarValue(condition: RoutingCondition): string {
  if (Array.isArray(condition.value)) return condition.value.join(', ');

  return typeof condition.value === 'string' ? condition.value : '';
}

export function valueInputType(type: string): 'datetime-local' | 'time' | 'text' {
  if (type === 'date_time') return 'datetime-local';

  return type === 'time_of_day' ? 'time' : 'text';
}

export function rangeInputType(type: string): 'datetime-local' | 'time' {
  return type === 'date_time' ? 'datetime-local' : 'time';
}

export function valuePlaceholder(type: string): string {
  return PLACEHOLDERS[type] ?? 'Enter a value';
}
