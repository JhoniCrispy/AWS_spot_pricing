export function roundPrice(value, decimals = 4) {
    if (typeof value !== 'number') return value;
    const factor = Math.pow(10, decimals);
    return (Math.round(value * factor) / factor).toFixed(decimals);
  }