// src/app/features/orders/order-list/order-list.component.ts
import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe, CurrencyPipe } from '@angular/common';
import { RouterModule } from '@angular/router';
import { PaymentService, Order } from '../../../core/services/payment.service';

@Component({
  selector: 'app-order-list',
  standalone: true,
  imports: [CommonModule, RouterModule, DatePipe, CurrencyPipe],
  template: `
    <div class="orders-page">
      <h1>Mes commandes</h1>

      <div *ngIf="loading" class="loading-state">Chargement…</div>

      <div *ngIf="!loading && orders.length === 0" class="empty-state">
        <p>Vous n'avez encore passé aucune commande.</p>
        <a routerLink="/products" class="btn-primary">Découvrir nos produits</a>
      </div>

      <div *ngIf="!loading && orders.length > 0" class="orders-list">
        <div *ngFor="let order of orders" class="order-card">

          <!-- Entête commande -->
          <div class="order-card__header">
            <div>
              <span class="order-number">Commande #{{ order.id }}</span>
              <span class="order-date">{{ order.created_at | date:'dd/MM/yyyy' }}</span>
            </div>
            <span class="status-badge" [ngClass]="'status-' + order.status">
              {{ statusLabel(order.status) }}
            </span>
          </div>

          <!-- Résumé articles -->
          <div class="order-card__items">
            <div *ngFor="let item of order.items" class="item-row">
              <span class="item-name">{{ item.product_name }}</span>
              <span class="item-qty">× {{ item.quantity }}</span>
              <span class="item-price">{{ item.subtotal | currency: 'EUR':'symbol':'1.2-2' }}</span>
            </div>
          </div>

          <!-- Footer commande -->
          <div class="order-card__footer">
            <div class="totals">
              <span>Sous-total : {{ order.subtotal | currency:'EUR':'symbol':'1.2-2' }}</span>
              <span>Livraison : {{ order.shipping_fee | currency:'EUR':'symbol':'1.2-2' }}</span>
              <strong>Total : {{ order.total | currency:'EUR':'symbol':'1.2-2' }}</strong>
            </div>

            <div class="order-card__actions">
              <!-- Payer si en attente -->
              <a
                *ngIf="order.status === 'pending' && order.payment?.status !== 'succeeded'"
                [routerLink]="['/checkout/payment', order.id]"
                class="btn-pay"
              >
                💳 Payer
              </a>

              <!-- Badge payé -->
              <span *ngIf="order.payment?.status === 'succeeded'" class="badge-paid">
                ✅ Payé
              </span>

              <!-- Annuler si pending -->
              <button
                *ngIf="order.status === 'pending'"
                class="btn-cancel"
                (click)="cancelOrder(order)"
              >
                Annuler
              </button>

              <a [routerLink]="['/orders', order.id]" class="btn-details">Détails</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .orders-page { max-width: 760px; margin: 0 auto; padding: 32px 16px; }
    h1 { font-size: 24px; font-weight: 700; margin-bottom: 24px; color: #1a202c; }

    .loading-state, .empty-state { text-align: center; padding: 60px 0; color: #718096; }

    .orders-list { display: flex; flex-direction: column; gap: 16px; }

    .order-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.07);
      overflow: hidden;
    }

    .order-card__header {
      display: flex; justify-content: space-between; align-items: center;
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
      background: #f8fafc;
    }

    .order-number { font-weight: 700; color: #1a202c; margin-right: 10px; }
    .order-date   { font-size: 13px; color: #718096; }

    .status-badge {
      font-size: 12px; font-weight: 600;
      padding: 4px 12px; border-radius: 999px;
    }
    .status-pending    { background: #fef3c7; color: #92400e; }
    .status-processing { background: #dbeafe; color: #1e40af; }
    .status-shipped    { background: #e0e7ff; color: #3730a3; }
    .status-delivered  { background: #dcfce7; color: #166534; }
    .status-cancelled  { background: #fee2e2; color: #991b1b; }

    .order-card__items { padding: 14px 20px; }

    .item-row {
      display: flex; gap: 8px; align-items: center;
      font-size: 14px; padding: 4px 0;
      border-bottom: 1px dashed #f1f5f9;
      &:last-child { border-bottom: none; }
    }
    .item-name  { flex: 1; color: #374151; }
    .item-qty   { color: #9ca3af; }
    .item-price { font-weight: 600; color: #1a202c; min-width: 70px; text-align: right; }

    .order-card__footer {
      display: flex; justify-content: space-between; align-items: center;
      padding: 14px 20px;
      border-top: 1px solid #f1f5f9;
      background: #fafafa;
      flex-wrap: wrap; gap: 12px;
    }

    .totals {
      display: flex; flex-direction: column; gap: 2px;
      font-size: 13px; color: #4b5563;
      strong { color: #1a202c; font-size: 15px; }
    }

    .order-card__actions { display: flex; gap: 8px; align-items: center; }

    .btn-pay {
      background: #4f46e5; color: #fff;
      padding: 8px 16px; border-radius: 8px;
      font-size: 13px; font-weight: 600;
      text-decoration: none; transition: background 0.2s;
      &:hover { background: #4338ca; }
    }

    .badge-paid {
      background: #dcfce7; color: #166534;
      padding: 6px 12px; border-radius: 8px;
      font-size: 13px; font-weight: 600;
    }

    .btn-cancel {
      border: 1px solid #fca5a5; background: transparent; color: #dc2626;
      padding: 7px 14px; border-radius: 8px; font-size: 13px;
      cursor: pointer; transition: background 0.2s;
      &:hover { background: #fee2e2; }
    }

    .btn-details {
      border: 1px solid #e2e8f0; background: transparent; color: #374151;
      padding: 7px 14px; border-radius: 8px; font-size: 13px;
      text-decoration: none; transition: background 0.2s;
      &:hover { background: #f1f5f9; }
    }

    .btn-primary {
      background: #4f46e5; color: #fff;
      padding: 10px 24px; border-radius: 8px;
      text-decoration: none; font-weight: 600;
      display: inline-block; margin-top: 12px;
    }
  `],
})
export class OrderListComponent implements OnInit {
  orders: Order[] = [];
  loading = true;

  constructor(private paymentService: PaymentService) {}

  ngOnInit(): void {
    this.paymentService.getOrders().subscribe({
      next: (res) => {
        this.orders  = res.data;
        this.loading = false;
      },
      error: () => { this.loading = false; },
    });
  }

  cancelOrder(order: Order): void {
    if (!confirm(`Annuler la commande #${order.id} ?`)) return;

    this.paymentService.cancelOrder(order.id).subscribe({
      next: () => {
        order.status = 'cancelled';
      },
    });
  }

  statusLabel(status: string): string {
    const labels: Record<string, string> = {
      pending:    'En attente',
      processing: 'En traitement',
      shipped:    'Expédiée',
      delivered:  'Livrée',
      cancelled:  'Annulée',
    };
    return labels[status] ?? status;
  }
}
