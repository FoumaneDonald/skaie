# 💳 Module Paiement — Skaie (`feature/paiement`)

Documentation complète de la gestion des paiements par carte (Stripe Sandbox).

---

## 📦 Fichiers créés / modifiés

```
skaie/
├── app/
│   ├── Models/
│   │   ├── Order.php              ← Nouveau
│   │   ├── OrderItem.php          ← Nouveau
│   │   ├── Payment.php            ← Nouveau
│   │   └── User.php               ← Modifié  (relations orders/payments)
│   └── Http/Controllers/Api/
│       ├── Order/
│       │   └── OrderController.php   ← Nouveau (customer + admin)
│       ├── Payment/
│       │   └── PaymentController.php ← Nouveau (Stripe + webhook)
│       └── Admin/
│           └── DashboardController.php ← Nouveau (stats)
├── database/
│   ├── migrations/
│   │   ├── 2026_04_18_120000_create_orders_table.php
│   │   ├── 2026_04_18_120001_create_order_items_table.php
│   │   └── 2026_04_18_120002_create_payments_table.php
│   └── seeders/
│       └── PaymentSeeder.php      ← Nouveau (données de test)
├── routes/api.php                 ← Modifié  (nouvelles routes)
├── config/services.php            ← Modifié  (config Stripe)
├── bootstrap/app.php              ← Modifié  (CSRF exempt webhook)
└── composer.json                  ← Modifié  (stripe/stripe-php)
```

---

## ⚙️ Installation

### 1. Installer la dépendance Stripe
```bash
composer require stripe/stripe-php
```

### 2. Configurer les variables d'environnement
Copier `.env.example` → `.env` et renseigner :

```env
STRIPE_KEY=pk_test_...          # Clé publique (Dashboard Stripe → Sandbox)
STRIPE_SECRET=sk_test_...       # Clé secrète
STRIPE_WEBHOOK_SECRET=whsec_... # Généré par Stripe CLI (voir ci-dessous)
STRIPE_CURRENCY=eur
```

> **Obtenir les clés :** https://dashboard.stripe.com/test/apikeys

### 3. Migrer la base de données
```bash
php artisan migrate
```

### 4. (Optionnel) Charger des données de test
```bash
php artisan db:seed --class=PaymentSeeder
```

---

## 🔗 Routes API

### Client (auth:api + role:customer)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/customer/orders` | Liste mes commandes |
| `POST` | `/api/customer/orders` | Créer une commande |
| `GET` | `/api/customer/orders/{id}` | Détail d'une commande |
| `DELETE` | `/api/customer/orders/{id}/cancel` | Annuler (si pending) |
| `POST` | `/api/customer/orders/{id}/payment` | **Initier le paiement Stripe** |
| `GET` | `/api/customer/orders/{id}/payment/status` | Vérifier le statut du paiement |
| `GET` | `/api/customer/payments` | Historique de mes paiements |

### Admin (auth:api + role:admin)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/admin/dashboard` | Stats globales (KPIs, revenus…) |
| `GET` | `/api/admin/dashboard/revenue-summary` | Revenus mensuels (12 mois) |
| `GET` | `/api/admin/orders` | Toutes les commandes |
| `GET` | `/api/admin/orders/{id}` | Détail commande |
| `PATCH` | `/api/admin/orders/{id}/status` | Mettre à jour le statut |
| `GET` | `/api/admin/payments` | Tous les paiements |
| `POST` | `/api/admin/payments/{id}/refund` | Rembourser via Stripe |

### Webhook (public)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `POST` | `/api/stripe/webhook` | Réception événements Stripe |

---

## 🔄 Flux de paiement complet

```
Frontend Angular                    Laravel API                    Stripe
─────────────────────────────────────────────────────────────────────────
1. POST /customer/orders    ──────►  Crée Order + OrderItems
                            ◄──────  { order_id, total }

2. POST /customer/orders/{id}/payment ──► Crée PaymentIntent Stripe ──► Stripe
                                    ◄────────────────────────────── { client_secret }
                            ◄──────  { client_secret, amount, currency }

3. stripe.confirmCardPayment(client_secret, { card })  ──────────────► Stripe
                                                        ◄──────────── Confirmation

4. POST /stripe/webhook  ◄──────────────────────────────────────────── Stripe
   (payment_intent.succeeded)
   → Payment.status = 'succeeded'
   → Order.status   = 'processing'

5. GET /customer/orders/{id}/payment/status ──► { payment_status: 'succeeded' }
```

---

## 🃏 Cartes de test Stripe

| Carte | Numéro | Résultat |
|-------|--------|----------|
| Visa | `4242 4242 4242 4242` | ✅ Succès |
| Refusée | `4000 0000 0000 0002` | ❌ Déclinée |
| Auth requise | `4000 0025 0000 3155` | 🔐 3D Secure |
| Fonds insuffisants | `4000 0000 0000 9995` | ❌ Fonds insuf. |

> Date d'expiration : n'importe quelle date future — CVC : n'importe quel chiffre à 3 chiffres.

---

## 🪝 Configurer le Webhook en local (Stripe CLI)

```bash
# Installer Stripe CLI : https://stripe.com/docs/stripe-cli
stripe login

# Écouter et forwarder vers votre serveur local
stripe listen --forward-to http://localhost:8000/api/stripe/webhook

# La commande affiche :  whsec_xxxxx  → copier dans .env STRIPE_WEBHOOK_SECRET
```

**Événements gérés :**
- `payment_intent.succeeded` → Commande passée en `processing`
- `payment_intent.payment_failed` → Commande annulée, stock remis
- `payment_intent.canceled` → Commande annulée
- `charge.refunded` → Paiement marqué `refunded`

---

## 📊 Dashboard Admin — Exemples de réponse

```json
GET /api/admin/dashboard?days=30

{
  "period_days": 30,
  "kpis": {
    "total_revenue": 4825.50,
    "revenue_period": 1250.00,
    "total_orders": 142,
    "orders_period": 38,
    "total_customers": 56,
    "new_customers": 12,
    "pending_orders": 5,
    "processing_orders": 8,
    "failed_payments": 2
  },
  "orders_by_status": {
    "pending": 5,
    "processing": 8,
    "shipped": 10,
    "delivered": 112,
    "cancelled": 7
  },
  "daily_revenue": [
    { "date": "2026-03-19", "revenue": 120.50, "transactions": 4 },
    ...
  ],
  "top_products": [
    { "id": 1, "name": "Produit A", "total_sold": 45, "total_revenue": 900.00 },
    ...
  ],
  "recent_orders": [ ... ],
  "low_stock_products": [ ... ]
}
```

---

## 🏗️ Modèle de données

```
users ──────────────────────────────────────────────┐
  id, name, email, role, ...                        │
                                                    │
orders ─────────────────────────────────────────────┤
  id, user_id (FK)                                  │
  shipping_name/street/city/...                     │
  subtotal, shipping_fee, total                     │
  status: pending|processing|shipped|delivered|cancelled
                                                    │
order_items ────────────────────────────────────────┤
  id, order_id (FK), product_id (FK)                │
  product_name*, unit_price*, quantity, subtotal    │
  (* snapshot au moment de la commande)             │
                                                    │
payments ───────────────────────────────────────────┘
  id, order_id (FK), user_id (FK)
  stripe_payment_intent_id (unique)
  stripe_client_secret (hidden en lecture)
  amount, currency
  status: pending|processing|succeeded|failed|cancelled|refunded
  paid_at, failure_message, stripe_metadata (JSON)
```

---

## 🧪 Exemple de requête — Créer une commande

```json
POST /api/customer/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "address_id": 2,
  "notes": "Livrer le matin",
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 5, "quantity": 1 }
  ]
}
```

**Réponse 201 :**
```json
{
  "message": "Commande créée. Procédez au paiement.",
  "order": {
    "id": 12,
    "total": 45.97,
    "status": "pending",
    "items": [ ... ]
  }
}
```

## 🧪 Exemple de requête — Initier le paiement

```json
POST /api/customer/orders/12/payment
Authorization: Bearer {token}
```

**Réponse 200 :**
```json
{
  "message": "PaymentIntent créé. Finalisez le paiement avec Stripe.js.",
  "client_secret": "pi_3xxx_secret_xxx",
  "amount": 45.97,
  "currency": "eur",
  "order_id": 12
}
```

Le `client_secret` est passé à **Stripe.js** côté Angular :

```typescript
// Angular — stripe.service.ts
const result = await this.stripe.confirmCardPayment(clientSecret, {
  payment_method: {
    card: this.cardElement,
    billing_details: { name: 'Jean Dupont' }
  }
});

if (result.paymentIntent?.status === 'succeeded') {
  // Poller GET /customer/orders/{id}/payment/status
}
```
