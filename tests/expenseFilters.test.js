import test from 'node:test';
import assert from 'node:assert/strict';
import {
  filterExpenses,
  isValidExpenseDateRange,
  summarizeExpenses,
} from '../src/utils/expenseFilters.js';

const expenses = [
  { id: 1, date: '2026-10-20', category: 'Salary', amount: 800 },
  { id: 2, date: '2026-10-20', category: 'Salary', amount: 2500 },
  { id: 3, date: '2026-10-23', category: 'Salary', amount: 800 },
  { id: 4, date: '2026-10-25', category: 'Salary', amount: 3000 },
  { id: 5, date: '2026-10-26', category: 'Salary', amount: 350 },
];

test('date range includes expenses on both boundary dates', () => {
  const filtered = filterExpenses(expenses, {
    fromDate: '2026-10-19',
    toDate: '2026-10-25',
  });

  assert.deepEqual(summarizeExpenses(filtered), {
    total: 7100,
    count: 4,
    average: 1775,
    largest: 3000,
  });
});

test('changing the date range recalculates all summary values', () => {
  const filtered = filterExpenses(expenses, {
    fromDate: '2026-10-21',
    toDate: '2026-10-25',
  });

  assert.deepEqual(summarizeExpenses(filtered), {
    total: 3800,
    count: 2,
    average: 1900,
    largest: 3000,
  });
});

test('a range without matching records returns zero summary values', () => {
  const filtered = filterExpenses(expenses, {
    fromDate: '2026-10-27',
    toDate: '2026-10-28',
  });

  assert.deepEqual(summarizeExpenses(filtered), {
    total: 0,
    count: 0,
    average: 0,
    largest: 0,
  });
});

test('date range validation rejects a from date after the to date', () => {
  assert.equal(isValidExpenseDateRange('2026-10-25', '2026-10-19'), false);
  assert.equal(isValidExpenseDateRange('2026-10-19', '2026-10-25'), true);
});
