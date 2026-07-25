const CATEGORY_SUGGESTIONS = [
  'Fuel',
  'Rent',
  'Supplies',
  'Transportation',
  'Utilities',
  'Wages',
  'Other',
];

export default function ExpenseModal({
  open,
  expense,
  form,
  onChange,
  onClose,
  onSave,
  saving,
  error,
}) {
  if (!open) return null;

  const isEditing = Boolean(expense);

  return (
    <div className="modal fade show d-block report-modal-backdrop" role="dialog" aria-modal="true">
      <div className="modal-dialog modal-dialog-centered">
        <form className="modal-content" onSubmit={onSave}>
          <div className="modal-header">
            <h5 className="modal-title">
              <i className={`bi ${isEditing ? 'bi-pencil-square' : 'bi-plus-circle'} me-2`}></i>
              {isEditing ? 'Edit Expense' : 'Add Expense'}
            </h5>
            <button
              type="button"
              className="btn-close"
              onClick={onClose}
              aria-label="Close"
              disabled={saving}
            ></button>
          </div>

          <div className="modal-body">
            {error && (
              <div className="alert alert-danger py-2 small" role="alert">
                <i className="bi bi-exclamation-circle me-2"></i>
                {error}
              </div>
            )}

            <div className="row g-3">
              <div className="col-sm-6">
                <label className="form-label fw-semibold" htmlFor="expense-date">Date</label>
                <input
                  id="expense-date"
                  type="date"
                  className="form-control"
                  value={form.date}
                  onChange={event => onChange({ ...form, date: event.target.value })}
                  required
                />
              </div>

              <div className="col-sm-6">
                <label className="form-label fw-semibold" htmlFor="expense-amount">Amount</label>
                <div className="input-group">
                  <span className="input-group-text">PHP</span>
                  <input
                    id="expense-amount"
                    type="number"
                    className="form-control"
                    value={form.amount}
                    onChange={event => onChange({ ...form, amount: event.target.value })}
                    min="0.01"
                    max="999999999.99"
                    step="0.01"
                    inputMode="decimal"
                    required
                  />
                </div>
              </div>

              <div className="col-12">
                <label className="form-label fw-semibold" htmlFor="expense-category">Category</label>
                <input
                  id="expense-category"
                  className="form-control"
                  list="expense-category-options"
                  value={form.category}
                  onChange={event => onChange({ ...form, category: event.target.value })}
                  maxLength={100}
                  placeholder="Select or enter a category"
                  required
                />
                <datalist id="expense-category-options">
                  {CATEGORY_SUGGESTIONS.map(category => (
                    <option value={category} key={category} />
                  ))}
                </datalist>
              </div>

              <div className="col-12">
                <label className="form-label fw-semibold" htmlFor="expense-note">Description</label>
                <textarea
                  id="expense-note"
                  className="form-control"
                  rows="3"
                  value={form.note}
                  onChange={event => onChange({ ...form, note: event.target.value })}
                  maxLength={500}
                  placeholder="Optional details"
                ></textarea>
              </div>
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-outline-secondary" onClick={onClose} disabled={saving}>
              Cancel
            </button>
            <button type="submit" className="btn btn-dark" disabled={saving}>
              {saving ? (
                <>
                  <span className="spinner-border spinner-border-sm me-2"></span>
                  Saving...
                </>
              ) : (
                <>
                  <i className="bi bi-check2 me-2"></i>
                  {isEditing ? 'Save Changes' : 'Save Expense'}
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
