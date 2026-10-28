export function isValidExpenseDateRange(fromDate, toDate) {
  return !fromDate || !toDate || fromDate <= toDate;
}

export function filterExpenses(expenses, {
  fromDate = '',
  toDate = '',
  categoryFilter = '',
  search = '',
} = {}) {
  const needle = search.trim().toLowerCase();

  return (expenses || [])
    .filter(expense => !fromDate || expense.date >= fromDate)
    .filter(expense => !toDate || expense.date <= toDate)
    .filter(expense => !categoryFilter || expense.category === categoryFilter)
    .filter(expense => {
      if (!needle) return true;
      return [expense.category, expense.note, expense.name]
        .some(value => String(value || '').toLowerCase().includes(needle));
    })
    .slice()
    .sort((a, b) => {
      const dateComparison = String(b.date || '').localeCompare(String(a.date || ''));
      return dateComparison || Number(b.id) - Number(a.id);
    });
}

export function summarizeExpenses(expenses) {
  const total = (expenses || []).reduce(
    (sum, expense) => sum + Number(expense.amount || 0),
    0
  );
  const largest = (expenses || []).reduce(
    (max, expense) => Math.max(max, Number(expense.amount || 0)),
    0
  );
  const count = (expenses || []).length;

  return {
    total,
    count,
    average: count ? total / count : 0,
    largest,
  };
}
