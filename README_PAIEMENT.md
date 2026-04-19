# 💳 Module Paiement — Skaie (feature/paiement)

> **Développeur :** Marvin  
> **Stack :** Laravel 13 (API) · Angular (Frontend) · Stripe Sandbox

---

## 📦 Fichiers créés dans cette branche

### Backend (Laravel)

```
database/migrations/
  ├── 2026_04_18_120000_create_orders_table.php
  ├── 2026_04_18_120001_create_order_items_table.php
  └── 2026_04_18_120002_create_payments_table.php

app/Models/
  ├── Order.php
  ├── OrderItem.php
  └── Payment.php

app/Http/Controllers/Api/
  ├── Order/OrderController.php
  └── Payment/PaymentController.php

database/seeders/
  └── OrderPaymentSeeder.php

routes/api.php              (mis à jour)
config/services.php         (mis à jour — ajout Stripe)
.env.example                (mis à jour — variables Stripe)
```

### Frontend (Angular)

```
frontend/src/app/
  ├── core/services/payment.service.ts
  ├── features/checkout/checkout-payment/
  │   ├── checkout-payment.component.ts
  │   ├── checkout-payment.component.html
  │   └── checkout-payment.component.scss
  ├── features/orders/order-list/
  │   └── order-list.component.ts
  └── features/admin/payments/
      └── admin-payments.component.ts
```

---

## ⚙️ Installation & Setup

### 1. Installer la dépendance Stripe PHP

```bash
composer require stripe/stripe-php
```

### 2. Configurer le `.env`

Créer un compte sur [https://dashboard.stripe.com](https://dashboard.stripe.com) (**gratuit**),  
puis aller dans **Developers → API keys** (mode Test activé) :

```env
STRIPE_KEY=pk_test_xxxxxxxxxxxxxxxxxxxx        # Clé publique (pour Angular)
STRIPE_SECRET=sk_test_xxxxxxxxxxxxxxxxxxxx     # Clé secrète (pour Laravel)
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxx  # Voir section Webhook ci-dessous
STRIPE_CURRENCY=eur
```

### 3. Lancer les migrations

```bash
php artisan migrate
```

### 4. (Optionnel) Données de test

```bash
php artisan db:seed --class=OrderPaymentSeeder
```

---

## 🔗 Endpoints API

### Customer  `auth:api` + `verified.api` + `role:customer`

| Méthode | URL | Description |
|---------|-----|-------------|
| `POST`  | `/api/customer/orders` | Créer une commande |
| `GET`   | `/api/customer/orders` | Lister mes commandes |
| `GET`   | `/api/customer/orders/{id}` | Détail commande |
| `DELETE`| `/api/customer/orders/{id}/cancel` | Annuler commande |
| `POST`  | `/api/customer/orders/{id}/payment` | **Initier paiement Stripe** |
| `GET`   | `/api/customer/orders/{id}/payment/status` | Statut paiement |
| `GET`   | `/api/customer/payments` | Historique paiements |

### Admin  `auth:api` + `verified.api` + `role:admin`

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET`   | `/api/admin/orders` | Toutes les commandes |
| `GET`   | `/api/admin/orders/{id}` | Détail commande |
| `PATCH` | `/api/admin/orders/{id}/status` | Changer statut |
| `GET`   | `/api/admin/payments` | Tous les paiements |
| `POST`  | `/api/admin/payments/{id}/refund` | Rembourser |

### Public (Stripe)

| Méthode | URL | Description |
|---------|-----|-------------|
| `POST`  | `/api/stripe/webhook` | Webhook Stripe (ne pas protéger avec auth) |

---

## 💡 Flux de paiement (étape par étape)

```
Client Angular                    Laravel API                    Stripe
      │                                │                              │
      │  POST /customer/orders         │                              │
      │──────────────────────────────>│                              │
      │  ← { order_id: 42, total: ... }│                              │
      │                                │                              │
      │  POST /customer/orders/42/payment                            │
      │──────────────────────────────>│                              │
      │                                │  PaymentIntent.create()      │
      │                                │─────────────────────────────>│
      │                                │  ← { client_secret: "pi_..." }
      │  ← { client_secret }          │                              │
      │                                │                              │
      │  stripe.confirmCardPayment()   │                              │
      │─────────────────────────────────────────────────────────────>│
      │  ← { status: "succeeded" }     │                              │
      │                                │                              │
      │                                │<── Webhook: payment_intent.succeeded
      │                                │  → Payment.status = succeeded│
      │                                │  → Order.status = processing │
      │                                │                              │
      │  GET /customer/orders/42/payment/status                      │
      │──────────────────────────────>│                              │
      │  ← { payment_status: "succeeded" }                           │
      │  → Afficher page confirmation  │                              │
```

---

## 🔔 Webhook Stripe (Local)

Pour tester le webhook en local, utiliser le **Stripe CLI** :

```bash
# Installer Stripe CLI : https://stripe.com/docs/stripe-cli
stripe login

# Écouter et forwarder vers ton serveur local
stripe listen --forward-to http://localhost:8000/api/stripe/webhook

# Le CLI affiche le webhook secret à mettre dans .env :
# > Ready! Your webhook signing secret is whsec_xxxx
```

Les événements traités :
- `payment_intent.succeeded` → commande passe en `processing`
- `payment_intent.payment_failed` → commande `cancelled`, stock restitué
- `payment_intent.canceled` → commande `cancelled`
- `charge.refunded` → payment `refunded`

---

## 🧪 Cartes de test Stripe (Sandbox)

| Carte | Résultat |
|-------|----------|
| `4242 4242 4242 4242` | ✅ Paiement réussi |
| `4000 0000 0000 0002` | ❌ Carte refusée |
| `4000 0025 0000 3155` | 🔐 Authentification 3D Secure requise |
| `4000 0000 0000 9995` | ❌ Fonds insuffisants |

> Expiration : toute date future · CVC : n'importe quels 3 chiffres · Code postal : n'importe quel code

---

## 🔧 Intégration Angular

### 1. Charger Stripe.js dans `index.html`

```html
<!-- src/index.html -->
<script src="https://js.stripe.com/v3/"></script>
```

### 2. Injecter la clé publique via `environment.ts`

```typescript
// src/environments/environment.ts
export const environment = {
  apiUrl: 'http://localhost:8000/api',
  stripePublicKey: 'pk_test_xxxxxxxxxxxxxxxxxxxx',
};
```

Puis dans `main.ts` ou `app.config.ts` :

```typescript
(window as any)['STRIPE_PUBLIC_KEY'] = environment.stripePublicKey;
```

### 3. Ajouter les routes Angular

```typescript
// app.routes.ts
{
  path: 'checkout/payment/:orderId',
  component: CheckoutPaymentComponent,
  canActivate: [AuthGuard],
},
{
  path: 'orders',
  component: OrderListComponent,
  canActivate: [AuthGuard],
},
// Route admin
{
  path: 'admin/payments',
  component: AdminPaymentsComponent,
  canActivate: [AuthGuard, AdminGuard],
},
```

### 4. `HttpClient` avec le token Bearer

Ton intercepteur doit ajouter le header :
```
Authorization: Bearer <access_token>
```

---

## 🗃️ Schéma des tables

```
users
  └── orders (user_id)
        ├── order_items (order_id, product_id)
        └── payments (order_id, user_id)
```

### Table `orders`
| Colonne | Type | Description |
|---------|------|-------------|
| `status` | enum | `pending` → `processing` → `shipped` → `delivered` / `cancelled` |
| `subtotal` | decimal | Somme des articles |
| `shipping_fee` | decimal | Frais de livraison calculés |
| `total` | decimal | subtotal + shipping_fee |
| `shipping_*` | string | Snapshot adresse (immuable) |

### Table `payments`
| Colonne | Type | Description |
|---------|------|-------------|
| `stripe_payment_intent_id` | string | ID Stripe du PaymentIntent |
| `stripe_client_secret` | string | Envoyé au frontend pour Stripe.js |
| `status` | enum | `pending / processing / succeeded / failed / cancelled / refunded` |
| `paid_at` | timestamp | Rempli par le webhook `succeeded` |
| `stripe_metadata` | json | Données brutes de l'événement Stripe |

---

## 🔒 Sécurité

- La route `/api/stripe/webhook` est **publique** mais protégée par vérification de signature HMAC (`Stripe-Signature` header)
- Le `stripe_client_secret` est en `$hidden` dans le modèle Payment — il n'est retourné **que** lors de l'appel à `POST /payment`
- Le stock est décrémenté **dans une transaction** lors de la création de commande
- Le stock est **restitué** si le paiement échoue ou si la commande est annulée

---

*Skaie — Module paiement · Branche `feature/paiement`*
