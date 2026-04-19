// src/app/features/admin/payments/admin-payments.component.ts
import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe, CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PaymentService, Payment } from '../../../core/services/payment.service';

@Component({
  selector: 'app-admin-payments',
  standalone: true,
  imports: [CommonModule, FormsModule, DatePipe, CurrencyPipe],
  template: `
    <div class="admin-payments">
      <div class="page-header">
        <h1>Gestion des paiements</h1>

        <!-- Filtre par statut -->
        <select [(ngModel)]="filterStatus" (ngModelChange)="loadPayments()" class="filter-select">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="succeeded">Réussis</option>
          <option value="failed">Échoués</option>
          <option value="refunded">Remboursés</option>
        </select>
      </div>

      <!-- Stats rapides -->
      <div class="stats-row" *ngIf="!loading">
        <div class="stat-card">
          <div class="stat-label">Total encaissé</div>
          <div class="stat-value success">
            {{ totalSucceeded | currency:'EUR':'symbol':'1.2-2' }}
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Paiements réussis</div>
          <div class="stat-value">{{ countByStatus('succeeded') }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Échoués</div>
          <div class="stat-value error">{{ countByStatus('failed') }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Remboursés</div>
          <div class="stat-value warn">{{ countByStatus('refunded') }}</div>
        </div>
      </div>

      <!-- Table -->
      <div class="table-container" *ngIf="!loading">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Client</th>
              <th>Commande</th>
              <th>Montant</th>
              <th>Statut</th>
              <th>Payé le</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let p of payments">
              <td>{{ p.id }}</td>
              <td>
                <div class="client-info">
                  <span class="client-name">{{ p['user']?.name }}</span>
                  <span class="client-email">{{ p['user']?.email }}</span>
                </div>
              </td>
              <td><a class="order-link">#{{ p.order_id }}</a></td>
              <td><strong>{{ p.amount | currency:'EUR':'symbol':'1.2-2' }}</strong></td>
              <td>
                <span class="status-pill" [ngClass]="'pill-' + p.status">
                  {{ statusLabel(p.status) }}
                </span>
              </td>
              <td>{{ p.paid_at ? (p.paid_at | date:'dd/MM/yyyy HH:mm') : '—' }}</td>
              <td>
                <button
                  *ngIf="p.status === 'succeeded'"
                  class="btn-refund"
                  (click)="refund(p)"
                  [disabled]="refunding === p.id"
                >
                  {{ refunding === p.id ? '…' : 'Rembourser' }}
                </button>
                <span *ngIf="p.status !== 'succeeded'" class="no-action">—</span>
              </td>
            </tr>
            <tr *ngIf="payments.length === 0">
              <td colspan="7" class="empty-row">Aucun paiement trouvé.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div *ngIf="loading" class="loading">Chargement…</div>
      <div *ngIf="successMsg" class="flash-success">{{ successMsg }}</div>
      <div *ngIf="errorMsg"   class="flash-error">{{ errorMsg }}</div>
    </div>
  `,
  styles: [`
    .admin-payments { padding: 28px; }

    .page-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 24px;
      h1 { font-size: 22px; font-weight: 700; }
    }

    .filter-select {
      border: 1px solid #e2e8f0; border-radius: 8px;
      padding: 8px 14px; font-size: 14px; outline: none;
      &:focus { border-color: #4f46e5; }
    }

    /* Stats */
    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
    .stat-card {
      background: #fff; border-radius: 10px;
      box-shadow: 0 1px 8px rgba(0,0,0,0.07);
      padding: 20px;
    }
    .stat-label { font-size: 12px; color: #718096; margin-bottom: 6px; text-transform: uppercase; }
    .stat-value { font-size: 22px; font-weight: 700; color: #1a202c; }
    .stat-value.success { color: #16a34a; }
    .stat-value.error   { color: #dc2626; }
    .stat-value.warn    { color: #d97706; }

    /* Table */
    .table-container {
      background: #fff; border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0,0,0,0.07);
      overflow: auto;
    }

    table { width: 100%; border-collapse: collapse; }
    thead tr { background: #f8fafc; }
    th, td { padding: 13px 16px; text-align: left; font-size: 14px; }
    th { color: #64748b; font-weight: 600; font-size: 12px; text-transform: uppercase; }
    tbody tr { border-top: 1px solid #f1f5f9; }
    tbody tr:hover { background: #fafafa; }

    .client-name  { display: block; font-weight: 600; color: #1a202c; }
    .client-email { font-size: 12px; color: #718096; }

    .order-link { color: #4f46e5; font-weight: 600; text-decoration: none; }

    /* Status pills */
    .status-pill {
      font-size: 11px; font-weight: 600; padding: 3px 10px;
      border-radius: 999px; display: inline-block;
    }
    .pill-pending    { background: #fef3c7; color: #92400e; }
    .pill-succeeded  { background: #dcfce7; color: #166534; }
    .pill-failed     { background: #fee2e2; color: #991b1b; }
    .pill-cancelled  { background: #f1f5f9; color: #475569; }
    .pill-refunded   { background: #f0f9ff; color: #0369a1; }

    .btn-refund {
      background: #fff0f0; color: #dc2626;
      border: 1px solid #fca5a5;
      padding: 5px 12px; border-radius: 6px;
      font-size: 12px; cursor: pointer;
      &:hover:not(:disabled) { background: #fee2e2; }
      &:disabled { opacity: 0.5; cursor: not-allowed; }
    }
    .no-action { color: #cbd5e1; }
    .empty-row { text-align: center; color: #9ca3af; padding: 40px; }

    .loading { text-align: center; padding: 60px; color: #718096; }

    .flash-success, .flash-error {
      margin-top: 12px; padding: 12px 16px; border-radius: 8px; font-size: 14px;
    }
    .flash-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .flash-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
  `],
})
export class AdminPaymentsComponent implements OnInit {
  payments: Payment[] = [];
  filterStatus = '';
  loading    = true;
  refunding: number | null = null;
  successMsg = '';
  errorMsg   = '';

  constructor(private paymentService: PaymentService) {}

  ngOnInit(): void { this.loadPayments(); }

  loadPayments(): void {
    this.loading = true;
    this.paymentService.adminGetPayments(this.filterStatus || undefined).subscribe({
      next: (res) => { this.payments = res.data; this.loading = false; },
      error: ()  => { this.loading = false; },
    });
  }

  refund(payment: Payment): void {
    if (!confirm(`Rembourser le paiement #${payment.id} de ${payment.amount} ${payment.currency.toUpperCase()} ?`)) return;

    this.refunding = payment.id;
    this.paymentService.adminRefund(payment.id).subscribe({
      next: (res) => {
        this.successMsg = res.message;
        payment.status  = 'refunded';
        this.refunding  = null;
        setTimeout(() => this.successMsg = '', 4000);
      },
      error: (err) => {
        this.errorMsg  = err.error?.message || 'Erreur lors du remboursement.';
        this.refunding = null;
        setTimeout(() => this.errorMsg = '', 4000);
      },
    });
  }

  get totalSucceeded(): number {
    return this.payments
      .filter(p => p.status === 'succeeded')
      .reduce((sum, p) => sum + p.amount, 0);
  }

  countByStatus(status: string): number {
    return this.payments.filter(p => p.status === status).length;
  }

  statusLabel(status: string): string {
    const l: Record<string, string> = {
      pending:   'En attente', succeeded: 'Réussi',
      failed:    'Échoué',     cancelled: 'Annulé',
      refunded:  'Remboursé',  processing: 'En cours',
    };
    return l[status] ?? status;
  }
}
