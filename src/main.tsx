import React, { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { createRoot } from 'react-dom/client';
import './style.css';

declare global {
  interface Window {
    OC: {
      requestToken: string;
      generateUrl: (url: string) => string;
    };
  }
}

type ShoppingList = {
  id: number;
  name: string;
  owner: string;
  role: 'owner' | 'member';
};

type ShoppingItem = {
  id: number;
  list_id: number;
  name: string;
  quantity: string | null;
  note: string | null;
  category: string | null;
  checked: boolean | number;
  sort_order: number;
};

type Member = {
  user_id: string;
  display_name?: string;
  role: 'owner' | 'member';
};

type ApiResult<T> = T;

const apiRoot = () => window.OC.generateUrl('/apps/shoppinglist/api');

async function api<T>(path: string, method = 'GET', values?: Record<string, string | number | boolean>): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'requesttoken': window.OC.requestToken,
  };
  const options: RequestInit = {
    method,
    credentials: 'same-origin',
    headers,
  };
  if (values) {
    headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
    options.body = new URLSearchParams(
      Object.entries(values).map(([key, value]) => [key, String(value)]),
    ).toString();
  }

  const response = await fetch(`${apiRoot()}${path}`, options);
  if (response.status === 204) return undefined as T;

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || `Request failed (${response.status})`);
  }
  return payload as ApiResult<T>;
}

function App() {
  const [lists, setLists] = useState<ShoppingList[]>([]);
  const [activeListId, setActiveListId] = useState<number | null>(null);
  const [items, setItems] = useState<ShoppingItem[]>([]);
  const [members, setMembers] = useState<Member[]>([]);
  const [newListName, setNewListName] = useState('');
  const [newItemName, setNewItemName] = useState('');
  const [newQuantity, setNewQuantity] = useState('');
  const [newNote, setNewNote] = useState('');
  const [newCategory, setNewCategory] = useState('');
  const [newMemberId, setNewMemberId] = useState('');
  const [editingItem, setEditingItem] = useState<ShoppingItem | null>(null);
  const [showSharing, setShowSharing] = useState(false);
  const [confirmDeleteList, setConfirmDeleteList] = useState(false);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const activeList = lists.find((list) => list.id === activeListId) ?? null;

  const loadLists = useCallback(async (preferredId?: number) => {
    const result = await api<{ lists: ShoppingList[] }>('/lists');
    setLists(result.lists);
    const nextId = preferredId && result.lists.some((list) => list.id === preferredId)
      ? preferredId
      : activeListId && result.lists.some((list) => list.id === activeListId)
        ? activeListId
        : result.lists[0]?.id ?? null;
    setActiveListId(nextId);
  }, [activeListId]);

  const loadListData = useCallback(async (listId: number) => {
    const [itemResult, memberResult] = await Promise.all([
      api<{ items: ShoppingItem[] }>(`/lists/${listId}/items`),
      api<{ members: Member[] }>(`/lists/${listId}/members`),
    ]);
    setItems(itemResult.items);
    setMembers(memberResult.members);
  }, []);

  useEffect(() => {
    let alive = true;
    api<{ lists: ShoppingList[] }>('/lists')
      .then((result) => {
        if (!alive) return;
        setLists(result.lists);
        setActiveListId(result.lists[0]?.id ?? null);
      })
      .catch((err: Error) => alive && setError(err.message))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, []);

  useEffect(() => {
    if (activeListId === null) {
      setItems([]);
      setMembers([]);
      return;
    }
    let alive = true;
    setError('');
    Promise.all([
      api<{ items: ShoppingItem[] }>(`/lists/${activeListId}/items`),
      api<{ members: Member[] }>(`/lists/${activeListId}/members`),
    ])
      .then(([itemResult, memberResult]) => {
        if (!alive) return;
        setItems(itemResult.items);
        setMembers(memberResult.members);
      })
      .catch((err: Error) => alive && setError(err.message));
    return () => { alive = false; };
  }, [activeListId]);

  async function perform(action: () => Promise<void>) {
    setBusy(true);
    setError('');
    try {
      await action();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Something went wrong');
    } finally {
      setBusy(false);
    }
  }

  function submitList(event: FormEvent) {
    event.preventDefault();
    const name = newListName.trim();
    if (!name) return;
    void perform(async () => {
      const result = await api<{ list: ShoppingList }>('/lists', 'POST', { name });
      setNewListName('');
      await loadLists(result.list.id);
    });
  }

  function submitItem(event: FormEvent) {
    event.preventDefault();
    if (!activeList || !newItemName.trim()) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}/items`, 'POST', {
        name: newItemName.trim(),
        quantity: newQuantity.trim(),
        note: newNote.trim(),
        category: newCategory.trim(),
      });
      setNewItemName('');
      setNewQuantity('');
      setNewNote('');
      setNewCategory('');
      await loadListData(activeList.id);
    });
  }

  function toggleItem(item: ShoppingItem) {
    if (!activeList) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}/items/${item.id}`, 'PUT', {
        checked: !(item.checked === true || item.checked === 1),
      });
      await loadListData(activeList.id);
    });
  }

  function saveItem(event: FormEvent) {
    event.preventDefault();
    if (!activeList || !editingItem) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}/items/${editingItem.id}`, 'PUT', {
        name: editingItem.name,
        quantity: editingItem.quantity ?? '',
        note: editingItem.note ?? '',
        category: editingItem.category ?? '',
      });
      setEditingItem(null);
      await loadListData(activeList.id);
    });
  }

  function deleteItem(item: ShoppingItem) {
    if (!activeList) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}/items/${item.id}`, 'DELETE');
      await loadListData(activeList.id);
    });
  }

  function deleteList() {
    if (!activeList) return;
    setConfirmDeleteList(true);
  }

  function confirmListDeletion() {
    if (!activeList) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}`, 'DELETE');
      setConfirmDeleteList(false);
      setShowSharing(false);
      await loadLists();
    });
  }

  function shareList(event: FormEvent) {
    event.preventDefault();
    if (!activeList || !newMemberId.trim()) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}/members`, 'POST', { userId: newMemberId.trim() });
      setNewMemberId('');
      const result = await api<{ members: Member[] }>(`/lists/${activeList.id}/members`);
      setMembers(result.members);
    });
  }

  function removeMember(userId: string) {
    if (!activeList || !window.confirm(`Remove ${userId} from this list?`)) return;
    void perform(async () => {
      await api(`/lists/${activeList.id}/members/${encodeURIComponent(userId)}`, 'DELETE');
      const result = await api<{ members: Member[] }>(`/lists/${activeList.id}/members`);
      setMembers(result.members);
    });
  }

  const sortedItems = [...items].sort((a, b) =>
    Number(a.checked === true || a.checked === 1) - Number(b.checked === true || b.checked === 1)
      || a.sort_order - b.sort_order || a.name.localeCompare(b.name),
  );

  return (
    <main className="shopping-app">
      <header className="topbar">
        <div>
          <p className="eyebrow">Shared household lists</p>
          <h1>Shopping List</h1>
        </div>
        <span className="account-pill">Nextcloud</span>
      </header>

      {error && <div className="notice error" role="alert">{error}</div>}

      <div className="workspace">
        <aside className="sidebar">
          <div className="section-heading">
            <h2>Your lists</h2>
            <span className="count">{lists.length}</span>
          </div>
          {loading ? <p className="muted">Loading lists…</p> : (
            <nav className="list-nav" aria-label="Shopping lists">
              {lists.map((list) => (
                <button
                  className={`list-choice ${list.id === activeListId ? 'selected' : ''}`}
                  key={list.id}
                  onClick={() => { setActiveListId(list.id); setShowSharing(false); }}
                  type="button"
                >
                  <span className="list-icon" aria-hidden="true">☷</span>
                  <span className="list-choice-name">{list.name}</span>
                  {list.role === 'member' && <span className="shared-mark" title="Shared with you">↗</span>}
                </button>
              ))}
              {!lists.length && <p className="muted small">No lists yet. Create your first one below.</p>}
            </nav>
          )}
          <form className="new-list-form" onSubmit={submitList}>
            <label htmlFor="new-list">Create a list</label>
            <div className="inline-form">
              <input id="new-list" value={newListName} onChange={(e) => setNewListName(e.target.value)} placeholder="e.g. Weekly groceries" maxLength={255} />
              <button className="icon-button add-button" type="submit" disabled={busy || !newListName.trim()} aria-label="Create list">+</button>
            </div>
          </form>
        </aside>

        <section className="main-panel">
          {activeList ? (
            <>
              <div className="list-header">
                <div className="list-title-wrap">
                  <h2>{activeList.name}</h2>
                  <p className="muted">{items.filter((item) => item.checked === true || item.checked === 1).length} of {items.length} completed</p>
                </div>
                <div className="toolbar">
                  <button className={`secondary-button ${showSharing ? 'active' : ''}`} onClick={() => setShowSharing(!showSharing)} type="button">
                    <span aria-hidden="true">♧</span> Sharing
                  </button>
                  {activeList.role === 'owner' && <button className="quiet-danger" onClick={deleteList} type="button" disabled={busy}>Delete list</button>}
                </div>
              </div>

              {showSharing && (
                <section className="sharing-panel">
                  <div className="section-heading"><div><h3>People with access</h3><p className="muted small">Everyone can add, edit and check off items.</p></div></div>
                  <ul className="member-list">
                    {members.map((member) => (
                      <li key={member.user_id}>
                        <span className="avatar" aria-hidden="true">{(member.display_name || member.user_id).slice(0, 1).toUpperCase()}</span>
                        <span className="member-identity">
                          <span className="member-name">{member.display_name || member.user_id}</span>
                          <span className="member-user-id">{member.user_id}</span>
                        </span>
                        <span className="role-label">{member.role === 'owner' ? 'Owner' : 'Member'}</span>
                        {activeList.role === 'owner' && member.role !== 'owner' && (
                          <button className="text-button danger-text" type="button" onClick={() => removeMember(member.user_id)} disabled={busy}>Remove</button>
                        )}
                      </li>
                    ))}
                  </ul>
                  {activeList.role === 'owner' && (
                    <form className="share-form" onSubmit={shareList}>
                      <label htmlFor="member-id">Share with a Nextcloud user</label>
                      <div className="inline-form">
                        <input id="member-id" value={newMemberId} onChange={(e) => setNewMemberId(e.target.value)} placeholder="Their Nextcloud user ID" />
                        <button className="primary-button" type="submit" disabled={busy || !newMemberId.trim()}>Add person</button>
                      </div>
                    </form>
                  )}
                </section>
              )}

              <div className="items-heading">
                <h3>Items</h3>
                <span className="muted small">{items.length} total</span>
              </div>
              {!sortedItems.length && (
                <div className="empty-state compact-empty-state">
                  <p>Your list is empty. Add your first item below.</p>
                </div>
              )}
              <ul className="items-list">
                {sortedItems.map((item) => {
                  const checked = item.checked === true || item.checked === 1;
                  return (
                    <li className={`item-row ${checked ? 'is-checked' : ''}`} key={item.id}>
                      <button className="remove-item" type="button" onClick={() => deleteItem(item)} aria-label={`Remove ${item.name}`} disabled={busy}>×</button>
                      <button className="check-control" type="button" onClick={() => toggleItem(item)} aria-label={checked ? `Uncheck ${item.name}` : `Check off ${item.name}`} disabled={busy}>
                        {checked ? '✓' : ''}
                      </button>
                      <button className="item-content item-details-button" type="button" onClick={() => setEditingItem({ ...item })} aria-label={`Edit ${item.name}`}>
                        <span className="item-name">{item.name}</span>
                        {(item.quantity || item.category || item.note) && (
                          <span className="item-meta">
                            {item.quantity && <span className="item-quantity">{item.quantity}</span>}
                            {item.category && <span>{item.category}</span>}
                            {item.note && <span>{item.note}</span>}
                          </span>
                        )}
                      </button>
                    </li>
                  );
                })}
                <li className="item-row new-item-row">
                  <form className="new-item-inline-form" onSubmit={submitItem}>
                    <input id="new-item" className="item-name-input" value={newItemName} onChange={(e) => setNewItemName(e.target.value)} placeholder="Add a new item…" maxLength={255} aria-label="New item name" />
                    <input className="quantity-input" aria-label="New item quantity" value={newQuantity} onChange={(e) => setNewQuantity(e.target.value)} placeholder="Qty" />
                    <button className="add-inline-button" type="submit" disabled={busy || !newItemName.trim()} aria-label="Add item">+</button>
                  </form>
                </li>
              </ul>
            </>
          ) : (
            <div className="welcome-state">
              <div className="empty-illustration" aria-hidden="true">🛒</div>
              <h2>Make shopping simpler</h2>
              <p>Create a list and share it with the people you shop with.</p>
            </div>
          )}
        </section>
      </div>

      {confirmDeleteList && activeList && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(e) => e.target === e.currentTarget && setConfirmDeleteList(false)}>
          <section className="edit-dialog" role="alertdialog" aria-modal="true" aria-labelledby="delete-list-title" aria-describedby="delete-list-description">
            <h2 id="delete-list-title">Delete list?</h2>
            <p id="delete-list-description">Delete “{activeList.name}” and all its items? This cannot be undone.</p>
            <div className="dialog-actions">
              <button className="secondary-button" type="button" onClick={() => setConfirmDeleteList(false)} disabled={busy}>Cancel</button>
              <button className="primary-button danger-confirm-button" type="button" onClick={confirmListDeletion} disabled={busy}>Delete list</button>
            </div>
          </section>
        </div>
      )}

      {editingItem && (
        <div className="modal-backdrop" role="presentation" onMouseDown={(e) => e.target === e.currentTarget && setEditingItem(null)}>
          <section className="edit-dialog" role="dialog" aria-modal="true" aria-labelledby="edit-title">
            <h2 id="edit-title">Edit item</h2>
            <form onSubmit={saveItem}>
              <label>Item name<input autoFocus value={editingItem.name} onChange={(e) => setEditingItem({ ...editingItem, name: e.target.value })} required maxLength={255} /></label>
              <label>Quantity<input value={editingItem.quantity ?? ''} onChange={(e) => setEditingItem({ ...editingItem, quantity: e.target.value })} /></label>
              <label>Category<input value={editingItem.category ?? ''} onChange={(e) => setEditingItem({ ...editingItem, category: e.target.value })} /></label>
              <label>Note<input value={editingItem.note ?? ''} onChange={(e) => setEditingItem({ ...editingItem, note: e.target.value })} /></label>
              <div className="dialog-actions">
                <button className="secondary-button" type="button" onClick={() => setEditingItem(null)}>Cancel</button>
                <button className="primary-button" type="submit" disabled={busy || !editingItem.name.trim()}>Save changes</button>
              </div>
            </form>
          </section>
        </div>
      )}
    </main>
  );
}

if ('serviceWorker' in navigator && window.isSecureContext) {
  const serviceWorkerUrl = window.OC.generateUrl('/apps/shoppinglist/sw.js');
  navigator.serviceWorker.register(serviceWorkerUrl).catch((error) => {
    // The app remains fully usable if service-worker installation is blocked.
    console.warn('Shopping List service worker registration failed', error);
  });
}

const rootElement = document.getElementById('shoppinglist');
if (rootElement) {
  createRoot(rootElement).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
  );
}
