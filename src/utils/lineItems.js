const WEIGHT_UNITS = new Set(['g', 'kg']);
const VOLUME_UNITS = new Set(['ml', 'liter']);

const UNIT_LABELS = {
  pc: ['pc', 'pcs'],
  pack: ['pack', 'packs'],
  sack: ['sack', 'sacks'],
  tray: ['tray', 'trays'],
  btl: ['btl', 'btls'],
  box: ['box', 'boxes'],
  case: ['case', 'cases'],
  sachet: ['sachet', 'sachets'],
  crate: ['crate', 'crates'],
  dozen: ['dozen', 'dozen'],
  kg: ['kg', 'kg'],
  g: ['g', 'g'],
  liter: ['liter', 'liters'],
  ml: ['ml', 'ml'],
};

function normalizeUnit(value) {
  const unit = String(value || '').trim().toLowerCase();
  if (['kilogram', 'kilograms', 'kgs'].includes(unit)) return 'kg';
  if (['gram', 'grams'].includes(unit)) return 'g';
  if (['litre', 'litres', 'liters', 'l'].includes(unit)) return 'liter';
  if (['milliliter', 'milliliters'].includes(unit)) return 'ml';
  return unit;
}

function measurementFromVariantName(value) {
  const match = String(value || '').match(
    /(?:^|[\s(])(\d+(?:\.\d+)?)\s*(kg|kgs?|kilograms?|g|grams?|liters?|litres?|l|ml|milliliters?)(?:\b|$)/i
  );

  if (!match) return null;
  return {
    quantity: Number(match[1]),
    unit: normalizeUnit(match[2]),
  };
}

export function formatQuantity(value) {
  return Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  });
}

function unitLabel(unit, quantity) {
  const normalized = normalizeUnit(unit) || 'pc';
  const labels = UNIT_LABELS[normalized];
  if (!labels) return normalized;
  return Number(quantity) === 1 ? labels[0] : labels[1];
}

export function resolveSellingUnit({ unit, baseUnit, variantName, conversionRate } = {}) {
  const configuredUnit = normalizeUnit(unit) || 'pc';
  const measurement = measurementFromVariantName(variantName);
  const inferredBaseUnit = normalizeUnit(baseUnit) || measurement?.unit || '';
  const rate = Number(conversionRate || 1);

  // Some packaged variants were saved using their stock unit (for example "kg").
  // A sale that consumes multiple base units is a package, not multiple kilograms.
  if (variantName && rate > 1 && inferredBaseUnit && configuredUnit === inferredBaseUnit) {
    return 'pack';
  }

  return configuredUnit;
}

export function getLineItemDisplay(item = {}) {
  const quantity = Number(item.qty || 0);
  const conversionRate = Number(item.conversionRate || 1);
  const measurement = measurementFromVariantName(item.variantName);
  const baseUnit = normalizeUnit(item.baseUnit) || measurement?.unit || '';
  const sellingUnit = resolveSellingUnit({
    unit: item.sellingUnit || item.unit,
    baseUnit,
    variantName: item.variantName,
    conversionRate,
  });
  const totalBaseQuantity = quantity * conversionRate;
  const showBaseTotal = Boolean(baseUnit) && conversionRate !== 1 && sellingUnit !== baseUnit;

  let baseTotalText = '';
  if (showBaseTotal) {
    const label = WEIGHT_UNITS.has(baseUnit)
      ? 'Total weight'
      : VOLUME_UNITS.has(baseUnit)
        ? 'Total volume'
        : 'Total quantity';
    baseTotalText = `${label}: ${formatQuantity(totalBaseQuantity)} ${unitLabel(baseUnit, totalBaseQuantity)}`;
  }

  return {
    sellingUnit,
    sellingUnitLabel: unitLabel(sellingUnit, quantity),
    quantityText: `${formatQuantity(quantity)} ${unitLabel(sellingUnit, quantity)}`,
    baseUnit,
    totalBaseQuantity,
    baseTotalText,
  };
}
