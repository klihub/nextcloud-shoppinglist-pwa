import React from 'react';
import { createRoot } from 'react-dom/client';
import './style.css';

function App() {
  return (
    <main className="app">
      <header className="app-header">
        <h1>Shopping List</h1>
      </header>
      <section className="empty-state" aria-live="polite">
        <h2>Your lists will appear here</h2>
        <p>The application scaffold is ready. List management is the next implementation step.</p>
      </section>
    </main>
  );
}

const rootElement = document.getElementById('shoppinglist');
if (rootElement) {
  createRoot(rootElement).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
  );
}
