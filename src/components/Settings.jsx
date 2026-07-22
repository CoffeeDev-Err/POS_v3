import { useEffect, useState } from 'react';
import { getErrorMessage } from '../utils/errors';

export default function Settings({ settings, onSaveSettings }) {
  const [store, setStore] = useState({
    storeName: settings?.storeName || '8ShineRice',
    address: settings?.address || 'Urdaneta, Ilocos',
    phone: settings?.phone || '09XX-XXX-XXXX',
    receiptFooter: settings?.receiptFooter || 'Salamat sa inyong pagbili! Please come again :)',
  });
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!settings) return;
    setStore({
      storeName: settings.storeName,
      address: settings.address,
      phone: settings.phone,
      receiptFooter: settings.receiptFooter,
    });
  }, [settings]);

  const handleSave = async () => {
    setSaving(true);
    setError('');
    try {
      await onSaveSettings({
        storeName: store.storeName,
        address: store.address,
        phone: store.phone,
        receiptFooter: store.receiptFooter,
      });
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    } catch (err) {
      setError(getErrorMessage(err, { fallback: 'An error occurred while saving the settings. Please try again.' }));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="settings-page">
      <div className="row justify-content-center">
        <div className="col-12 col-xl-8">
          <div className="card card-custom card-data-backup">
            <div className="card-header-custom">
              <i className="bi bi-shop me-2"></i>
              Store Information
            </div>
            <div className="card-body">
              <div className="mb-3">
                <label className="form-label fw-semibold">Store Name</label>
                <input
                  className="form-control"
                  value={store.storeName}
                  onChange={e => setStore({ ...store, storeName: e.target.value })}
                />
              </div>
              <div className="mb-3">
                <label className="form-label fw-semibold">Address</label>
                <input
                  className="form-control"
                  value={store.address}
                  onChange={e => setStore({ ...store, address: e.target.value })}
                />
              </div>
              <div className="mb-3">
                <label className="form-label fw-semibold">Phone Number</label>
                <input
                  className="form-control"
                  value={store.phone}
                  onChange={e => setStore({ ...store, phone: e.target.value })}
                />
              </div>
              <div className="mb-3">
                <label className="form-label fw-semibold">Receipt Footer Message</label>
                <textarea
                  className="form-control"
                  rows="2"
                  value={store.receiptFooter}
                  onChange={e => setStore({ ...store, receiptFooter: e.target.value })}
                />
              </div>
              {error && (
                <div className="alert alert-danger py-2 small">
                  <i className="bi bi-exclamation-circle me-1"></i>
                  {error}
                </div>
              )}
              {saved && (
                <div className="alert alert-success py-2 small">
                  <i className="bi bi-check-circle me-1"></i>
                  Settings saved successfully!
                </div>
              )}
              <button className="btn btn-dark" onClick={handleSave} disabled={saving}>
                {saving
                  ? <><span className="spinner-border spinner-border-sm me-2"></span>Saving...</>
                  : <><i className="bi bi-save me-2"></i>Save Settings</>
                }
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
