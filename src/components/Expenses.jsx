import { useMemo, useState } from 'react';
import { toLocalDateString } from '../utils/date';
import { getErrorMessage } from '../utils/errors';
import LoadingSkeleton from './LoadingSkeleton';
import ExpenseModal from './expenses/ExpenseModal';

const currencyFormatter = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
  minimumFractionDigits: 2,
});

const formatCurrency = value => currencyFormatter.format(Number(value || 0));

const initialForm = date => ({
  date,
  amount: '',
  category: '',
  note: '',
});

export default function Expenses({
  expenses,
  onCreateExpense,
  onUpdateExpense,
  onDeleteExpense,
  loading,
}) {
  const today = toLocalDateString();
  const monthStart = `${today.slice(0, 8)}01`;
  const [fromDate, setFromDate] = useState(monthStart);
  const [toDate, setToDate] = useState(today);
  const [categoryFilter, setCategoryFilter] = useState('');
  const [search, setSearch] = useState('');
  const [editingExpense, setEditingExpense] = useState(null);
  const [expenseToDelete, setExpenseToDelete] = useState(null);
  const [form, setForm] = useState(initialForm(today));
  const [modalOpen, setModalOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [formError, setFormError] = useState('');
  const [deleteError, setDeleteError] = useState('');

  const categories = useMemo(
    () => [...new Set((expenses || []).map(expense => expense.category).filter(Boolean))].sort(),
    [expenses]
  );

  const filteredExpenses = useMemo(() => {
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
  }, [expenses, fromDate, toDate, categoryFilter, search]);

  const summary = useMemo(() => {
    const total = filteredExpenses.reduce((sum, expense) => sum + Number(expense.amount || 0), 0);
    const largest = filteredExpenses.reduce(
      (max, expense) => Math.max(max, Number(expense.amount || 0)),
      0
    );

    return {
      total,
      count: filteredExpenses.length,
      average: filteredExpenses.length ? total / filteredExpenses.length : 0,
      largest,
    };
  }, [filteredExpenses]);

  const openCreateModal = () => {
    setEditingExpense(null);
    setForm(initialForm(today));
    setFormError('');
    setModalOpen(true);
  };

  const openEditModal = expense => {
    setEditingExpense(expense);
    setForm({
      date: expense.date || today,
      amount: String(expense.amount ?? ''),
      category: expense.category || '',
      note: expense.note || expense.name || '',
    });
    setFormError('');
    setModalOpen(true);
  };

  const closeModal = () => {
    if (saving) return;
    setModalOpen(false);
    setEditingExpense(null);
    setFormError('');
  };

  const handleSave = async event => {
    event.preventDefault();
    setSaving(true);
    setFormError('');

    const payload = {
      date: form.date,
      amount: Number(form.amount),
      category: form.category.trim(),
      note: form.note.trim(),
      ...(editingExpense?.name ? { name: editingExpense.name } : {}),
    };

    try {
      if (editingExpense) {
        await onUpdateExpense(editingExpense.id, payload);
      } else {
        await onCreateExpense(payload);
      }
      setModalOpen(false);
      setEditingExpense(null);
      setFormError('');
    } catch (error) {
      setFormError(getErrorMessage(error, {
        fallback: `Unable to ${editingExpense ? 'update' : 'add'} the expense.`,
      }));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!expenseToDelete) return;
    setDeleting(true);
    setDeleteError('');

    try {
      await onDeleteExpense(
        expenseToDelete.id,
        expenseToDelete.note || expenseToDelete.category
      );
      setExpenseToDelete(null);
    } catch (error) {
      setDeleteError(getErrorMessage(error, { fallback: 'Unable to delete the expense.' }));
    } finally {
      setDeleting(false);
    }
  };

  const resetFilters = () => {
    setFromDate(monthStart);
    setToDate(today);
    setCategoryFilter('');
    setSearch('');
  };

  return (
    <div>
      <div className="page-header mb-3">
        <h5 className="mb-0">Expense Records</h5>
        <button className="btn btn-dark" onClick={openCreateModal}>
          <i className="bi bi-plus-lg me-2"></i>
          Add Expense
        </button>
      </div>

      <div className="card-custom mb-3">
        <div className="card-header-custom">
          <i className="bi bi-funnel me-2"></i>
          Filters
        </div>
        <div className="p-3">
          <div className="row g-3 align-items-end">
            <div className="col-sm-6 col-xl-2">
              <label className="form-label small fw-semibold" htmlFor="expense-from-date">From</label>
              <input
                id="expense-from-date"
                type="date"
                className="form-control"
                value={fromDate}
                onChange={event => setFromDate(event.target.value)}
              />
            </div>
            <div className="col-sm-6 col-xl-2">
              <label className="form-label small fw-semibold" htmlFor="expense-to-date">To</label>
              <input
                id="expense-to-date"
                type="date"
                className="form-control"
                value={toDate}
                onChange={event => setToDate(event.target.value)}
              />
            </div>
            <div className="col-sm-6 col-xl-3">
              <label className="form-label small fw-semibold" htmlFor="expense-category-filter">Category</label>
              <select
                id="expense-category-filter"
                className="form-select"
                value={categoryFilter}
                onChange={event => setCategoryFilter(event.target.value)}
              >
                <option value="">All categories</option>
                {categories.map(category => (
                  <option value={category} key={category}>{category}</option>
                ))}
              </select>
            </div>
            <div className="col-sm-6 col-xl-4">
              <label className="form-label small fw-semibold" htmlFor="expense-search">Search</label>
              <div className="input-group">
                <span className="input-group-text"><i className="bi bi-search"></i></span>
                <input
                  id="expense-search"
                  className="form-control"
                  value={search}
                  onChange={event => setSearch(event.target.value)}
                  placeholder="Category or description"
                />
              </div>
            </div>
            <div className="col-xl-1 d-flex justify-content-xl-end">
              <button
                className="btn btn-outline-secondary expense-icon-button"
                onClick={resetFilters}
                title="Reset filters"
                aria-label="Reset filters"
              >
                <i className="bi bi-arrow-counterclockwise"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div className="row g-3 mb-3">
        <div className="col-6 col-xl-3">
          <div className="stat-card h-100">
            <div className="stat-value">{formatCurrency(summary.total)}</div>
            <div className="stat-label">Total Expenses</div>
          </div>
        </div>
        <div className="col-6 col-xl-3">
          <div className="stat-card h-100">
            <div className="stat-value">{summary.count.toLocaleString()}</div>
            <div className="stat-label">Records</div>
          </div>
        </div>
        <div className="col-6 col-xl-3">
          <div className="stat-card h-100">
            <div className="stat-value">{formatCurrency(summary.average)}</div>
            <div className="stat-label">Average</div>
          </div>
        </div>
        <div className="col-6 col-xl-3">
          <div className="stat-card h-100">
            <div className="stat-value">{formatCurrency(summary.largest)}</div>
            <div className="stat-label">Largest Expense</div>
          </div>
        </div>
      </div>

      {loading ? (
        <LoadingSkeleton variant="page" />
      ) : (
        <div className="card-custom">
          <div className="card-header-custom justify-content-between">
            <span>
              <i className="bi bi-wallet2 me-2"></i>
              Expenses
            </span>
            <span className="text-muted fw-normal">{filteredExpenses.length} records</span>
          </div>
          <div className="table-responsive table-scroll-panel table-scroll-panel--page">
            <table className="table table-hover mb-0 align-middle">
              <thead className="table-light">
                <tr>
                  <th>Date</th>
                  <th>Category</th>
                  <th>Description</th>
                  <th className="text-end">Amount</th>
                  <th className="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredExpenses.map(expense => (
                  <tr key={expense.id}>
                    <td className="text-nowrap">{expense.date}</td>
                    <td><span className="badge bg-light text-dark border">{expense.category}</span></td>
                    <td className="text-muted">{expense.note || expense.name || '-'}</td>
                    <td className="text-end fw-semibold text-nowrap">{formatCurrency(expense.amount)}</td>
                    <td>
                      <div className="d-flex justify-content-end gap-2">
                        <button
                          className="btn btn-sm btn-outline-secondary expense-icon-button"
                          onClick={() => openEditModal(expense)}
                          title="Edit expense"
                          aria-label={`Edit ${expense.category} expense`}
                        >
                          <i className="bi bi-pencil"></i>
                        </button>
                        <button
                          className="btn btn-sm btn-outline-danger expense-icon-button"
                          onClick={() => {
                            setDeleteError('');
                            setExpenseToDelete(expense);
                          }}
                          title="Delete expense"
                          aria-label={`Delete ${expense.category} expense`}
                        >
                          <i className="bi bi-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {filteredExpenses.length === 0 && (
                  <tr>
                    <td colSpan="5">
                      <div className="empty-state text-muted">
                        <i className="bi bi-receipt fs-3 d-block mb-2"></i>
                        No expenses found
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <ExpenseModal
        open={modalOpen}
        expense={editingExpense}
        form={form}
        onChange={setForm}
        onClose={closeModal}
        onSave={handleSave}
        saving={saving}
        error={formError}
      />

      {expenseToDelete && (
        <div className="modal fade show d-block report-modal-backdrop" role="dialog" aria-modal="true">
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">
                  <i className="bi bi-trash me-2"></i>
                  Delete Expense
                </h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setExpenseToDelete(null)}
                  aria-label="Close"
                  disabled={deleting}
                ></button>
              </div>
              <div className="modal-body">
                {deleteError && <div className="alert alert-danger py-2 small">{deleteError}</div>}
                <p className="mb-1">Delete this expense record?</p>
                <div className="small text-muted">
                  {expenseToDelete.category} - {formatCurrency(expenseToDelete.amount)}
                </div>
              </div>
              <div className="modal-footer">
                <button
                  className="btn btn-outline-secondary"
                  onClick={() => setExpenseToDelete(null)}
                  disabled={deleting}
                >
                  Cancel
                </button>
                <button className="btn btn-danger" onClick={handleDelete} disabled={deleting}>
                  {deleting ? (
                    <>
                      <span className="spinner-border spinner-border-sm me-2"></span>
                      Deleting...
                    </>
                  ) : (
                    <>
                      <i className="bi bi-trash me-2"></i>
                      Delete
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
