import { useState } from 'react';
import {
  ProductsToolbar,
  CategoryCards,
  ProductsTable,
  ProductModal,
  DeleteConfirmModal,
} from './products/index';
import { getErrorMessage } from '../utils/errors';

function formatMoney(value) {
  return `PHP ${Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatDateTime(value) {
  if (!value) return 'Unknown time';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function renderPricingSnapshot(snapshot) {
  if (!snapshot) {
    return <div className="small text-muted">No pricing snapshot recorded.</div>;
  }

  if (snapshot.hasVariants) {
    const variants = Array.isArray(snapshot.variants) ? snapshot.variants : [];

    return (
      <div className="d-flex flex-column gap-2">
        {variants.map((variant, index) => (
          <div key={variant.id || `${variant.name || 'variant'}-${index}`} className="border rounded p-2 bg-body-tertiary">
            <div className="fw-semibold">{variant.name || `Variant ${index + 1}`}</div>
            <div className="small text-muted mb-1">{variant.unit || snapshot.baseUnit || 'pc'}</div>
            <div className="small">Retail: <strong>{formatMoney(variant.priceRetail)}</strong></div>
            {variant.priceWholesale != null && (
              <div className="small">Wholesale: <strong>{formatMoney(variant.priceWholesale)}</strong></div>
            )}
            <div className="small">Cost: <strong>{formatMoney(variant.cost)}</strong></div>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="d-flex flex-column gap-1 small">
      <div>Retail: <strong>{formatMoney(snapshot.priceRetail)}</strong></div>
      {snapshot.priceWholesale != null && (
        <div>Wholesale: <strong>{formatMoney(snapshot.priceWholesale)}</strong></div>
      )}
      <div>Cost: <strong>{formatMoney(snapshot.cost)}</strong></div>
      <div className="text-muted">Unit: {snapshot.unit || 'pc'}</div>
    </div>
  );
}

const EMPTY = {
  name: '', category: '',
  hasVariants: false,
  baseUnit: 'pc',
  // non-variant fields
  price: '', cost: '', unit: 'pc', conversionRate: 1,
  priceRetail: '', priceWholesale: '', wholesaleQtyThreshold: '',
  stock: '', lowStockAlert: '',
  // variant fields
  variants: [],
};

export default function Products({
  products,
  categories,
  onCreateProduct,
  onUpdateProduct,
  onDeleteProduct,
  onCreateCategory,
  onDeleteCategory,
}) {
  const [search, setSearch] = useState('');
  const [catFilter, setCatFilter] = useState('All');
  const [showModal, setShowModal] = useState(false);
  const [editProduct, setEditProduct] = useState(null);
  const [form, setForm] = useState({ ...EMPTY, category: categories[0] || '' });
  const [deleteId, setDeleteId] = useState(null);
  const [historyProduct, setHistoryProduct] = useState(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  // New-category creation inside the form
  const [newCatMode, setNewCatMode] = useState(false);
  const [newCatInput, setNewCatInput] = useState('');

  const filtered = products.filter(p => {
    const cat = catFilter === 'All' || p.category === catFilter;
    const s = p.name.toLowerCase().includes(search.toLowerCase());
    return cat && s;
  });

  const openAdd = () => {
    setForm({ ...EMPTY, category: categories[0] || '' });
    setEditProduct(null);
    setNewCatMode(false);
    setNewCatInput('');
    setError('');
    setShowModal(true);
  };

  const openEdit = (p) => {
    setForm({
      ...EMPTY,
      ...p,
      price:        String(p.price ?? ''),
      cost:         String(p.cost ?? ''),
      stock:        String(p.stock ?? ''),
      lowStockAlert: String(p.lowStockAlert ?? ''),
      hasVariants:  p.hasVariants || false,
      baseUnit:     p.baseUnit || p.unit || 'pc',
      conversionRate: p.conversionRate || 1,
      variants:     Array.isArray(p.variants) ? p.variants : [],
      priceRetail:  String(p.priceRetail ?? p.price ?? ''),
      priceWholesale: String(p.priceWholesale ?? ''),
      wholesaleQtyThreshold: String(p.wholesaleQtyThreshold ?? ''),
    });
    setEditProduct(p);
    setNewCatMode(false);
    setNewCatInput('');
    setError('');
    setShowModal(true);
  };

  const handleAddCategory = async () => {
    const trimmed = newCatInput.trim();
    if (!trimmed || categories.includes(trimmed)) return;

    setSaving(true);
    setError('');
    try {
      await onCreateCategory(trimmed);
      setForm(f => ({ ...f, category: trimmed }));
      setNewCatMode(false);
      setNewCatInput('');
    } catch (err) {
      setError(getErrorMessage(err, { fallback: 'An error occurred while adding the category. Please try again.' }));
    } finally {
      setSaving(false);
    }
  };

  const handleSave = async () => {
    // Validate
    if (!form.name || !form.category) return;
    if (form.hasVariants) {
      if (!form.stock || form.variants.length === 0) return;
      if (form.variants.some(v => !v.name || !(v.priceRetail || v.price) || !v.cost || !v.unit)) return;
    } else {
      if (!form.price || !form.cost || !form.stock) return;
    }

    setSaving(true);
    setError('');

    let payload;
    if (form.hasVariants) {
      payload = {
        name:          form.name,
        category:      form.category,
        hasVariants:   true,
        baseUnit:      form.baseUnit || 'pc',
        stock:         parseInt(form.stock),
        lowStockAlert: parseInt(form.lowStockAlert) || 0,
        // store first variant's price at top level for backward-compat display
        price:         parseFloat(form.variants[0]?.priceRetail ?? form.variants[0]?.price) || 0,
        priceRetail:   parseFloat(form.variants[0]?.priceRetail ?? form.variants[0]?.price) || 0,
        cost:          parseFloat(form.variants[0]?.cost)  || 0,
        unit:          form.baseUnit || 'pc',
        conversionRate: 1,
        variants: form.variants.map(v => ({
          id:                   v.id,
          name:                 v.name,
          unit:                 v.unit,
          conversionRate:       Number(v.conversionRate) || 1,
          price:                parseFloat(v.priceRetail ?? v.price) || 0,
          priceRetail:          parseFloat(v.priceRetail ?? v.price) || 0,
          priceWholesale:       v.priceWholesale ? parseFloat(v.priceWholesale) : null,
          wholesaleQtyThreshold: v.wholesaleQtyThreshold ? parseInt(v.wholesaleQtyThreshold) : 0,
          cost:                 parseFloat(v.cost),
          lowStockAlert:        parseInt(v.lowStockAlert) || 0,
        })),
      };
    } else {
      payload = {
        name:          form.name,
        category:      form.category,
        hasVariants:   false,
        baseUnit:      form.unit || 'pc',
        price:         parseFloat(form.priceRetail || form.price) || 0,
        priceRetail:   parseFloat(form.priceRetail || form.price) || 0,
        priceWholesale: form.priceWholesale ? parseFloat(form.priceWholesale) : null,
        wholesaleQtyThreshold: form.wholesaleQtyThreshold ? parseInt(form.wholesaleQtyThreshold) : 0,
        cost:          parseFloat(form.cost),
        unit:          form.unit,
        conversionRate: Number(form.conversionRate) || 1,
        stock:         parseInt(form.stock),
        lowStockAlert: parseInt(form.lowStockAlert) || 0,
        variants:      [],
      };
    }

    try {
      if (editProduct) {
        await onUpdateProduct(editProduct.id, payload);
      } else {
        await onCreateProduct(payload);
      }
      setShowModal(false);
    } catch (err) {
      setError(getErrorMessage(err, { fallback: 'An error occurred while saving the product. Please try again.' }));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    setSaving(true);
    setError('');
    try {
      const productName = products.find(p => p.id === deleteId)?.name;
      await onDeleteProduct(deleteId, productName);
      setDeleteId(null);
    } catch (err) {
      setError(getErrorMessage(err, { fallback: 'An error occurred while deleting the product. Please try again.' }));
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteCategory = async (cat) => {
    const inUse = products.some(p => p.category === cat);
    const confirmMessage = inUse
      ? `Delete "${cat}" and its products? This will remove ${products.filter(p => p.category === cat).length} product(s) and cannot be undone.`
      : `Delete "${cat}" category? This cannot be undone.`;

    if (!window.confirm(confirmMessage)) return;

    setSaving(true);
    setError('');
    try {
      await onDeleteCategory(cat, { deleteProducts: inUse });
      if (catFilter === cat) setCatFilter('All');
    } catch (err) {
      setError(getErrorMessage(err, { fallback: 'An error occurred while deleting the category. Please try again.' }));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <ProductsToolbar
        search={search}
        onSearchChange={setSearch}
        catFilter={catFilter}
        categories={categories}
        onCategoryChange={setCatFilter}
        onAddProduct={openAdd}
      />

      <CategoryCards
        categories={categories}
        products={products}
        catFilter={catFilter}
        onSelectCategory={setCatFilter}
        onDeleteCategory={handleDeleteCategory}
      />

      <ProductsTable
        products={filtered}
        onEdit={openEdit}
        onViewHistory={setHistoryProduct}
        onDelete={setDeleteId}
      />

      <ProductModal
        open={showModal}
        editProduct={editProduct}
        form={form}
        onFormChange={setForm}
        categories={categories}
        newCatMode={newCatMode}
        newCatInput={newCatInput}
        onNewCatMode={setNewCatMode}
        onNewCatInput={setNewCatInput}
        onAddCategory={handleAddCategory}
        onClose={() => setShowModal(false)}
        onSave={handleSave}
        saving={saving}
        error={error}
      />

      <DeleteConfirmModal
        open={Boolean(deleteId)}
        onCancel={() => setDeleteId(null)}
        onConfirm={handleDelete}
        saving={saving}
      />

      {historyProduct && (
        <div className="modal fade show d-block" style={{ background: 'rgba(0,0,0,0.5)', zIndex: 1050 }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content">
              <div className="modal-header">
                <div>
                  <h5 className="modal-title mb-1">
                    <i className="bi bi-clock-history me-2"></i>
                    {historyProduct.name} Price History
                  </h5>
                  <div className="small text-muted">
                    Track cost and selling price changes for this product.
                  </div>
                </div>
                <button className="btn-close" onClick={() => setHistoryProduct(null)} aria-label="Close" />
              </div>
              <div className="modal-body">
                {(historyProduct.priceHistory || []).length === 0 ? (
                  <div className="text-center text-muted py-4">
                    <i className="bi bi-clock-history fs-2 d-block mb-2"></i>
                    No price history recorded yet.
                  </div>
                ) : (
                  <div className="d-flex flex-column gap-3">
                    {historyProduct.priceHistory.map((entry, index) => (
                      <div key={entry.id || `${historyProduct.id}-history-${index}`} className="border rounded-3 p-3">
                        <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                          <div>
                            <div className="fw-semibold text-capitalize">{entry.type || 'updated'}</div>
                            <div className="small text-muted">{formatDateTime(entry.changedAt)}</div>
                          </div>
                          <div className="small text-muted">
                            Changed by <strong>{entry.changedByName || 'System'}</strong>
                          </div>
                        </div>

                        {entry.before ? (
                          <div className="row g-3">
                            <div className="col-md-6">
                              <div className="small text-muted fw-semibold mb-2">Before</div>
                              {renderPricingSnapshot(entry.before)}
                            </div>
                            <div className="col-md-6">
                              <div className="small text-muted fw-semibold mb-2">After</div>
                              {renderPricingSnapshot(entry.after)}
                            </div>
                          </div>
                        ) : (
                          <div>
                            <div className="small text-muted fw-semibold mb-2">Initial pricing</div>
                            {renderPricingSnapshot(entry.after)}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div className="modal-footer">
                <button className="btn btn-secondary" onClick={() => setHistoryProduct(null)}>
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
