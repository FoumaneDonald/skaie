// src/app/core/services/payment.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface OrderItem {
  product_id: number;
  quantity: number;
}

export interface CreateOrderPayload {
  address_id?: number;
  address_data?: {
    label: string;
    street: string;
    city: string;
    state?: string;
    zip?: string;
    country?: string;
    phone?: string;
  };
  notes?: string;
  items: OrderItem[];
}

export interface Order {
  id: number;
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  subtotal: number;
  shipping_fee: number;
  total: number;
  shipping_name: string;
  shipping_city: string;
  shipping_country: string;
  notes?: string;
  items: any[];
  payment?: Payment;
  created_at: string;
}

export interface Payment {
  id: number;
  order_id: number;
  status: 'pending' | 'processing' | 'succeeded' | 'failed' | 'cancelled' | 'refunded';
  amount: number;
  currency: string;
  paid_at?: string;
  failure_message?: string;
}

export interface PaymentInitResponse {
  message: string;
  client_secret: string;
  amount: number;
  currency: string;
  order_id: number;
}

export interface PaymentStatusResponse {
  order_id: number;
  order_status: string;
  payment_status: string;
  amount: number;
  currency: string;
  paid_at?: string;
  failure_message?: string;
}

@Injectable({ providedIn: 'root' })
export class PaymentService {
  private api = environment.apiUrl;

  constructor(private http: HttpClient) {}

  // ── Commandes ───────────────────────────────────────────────

  /** Créer une nouvelle commande */
  createOrder(payload: CreateOrderPayload): Observable<{ message: string; order: Order }> {
    return this.http.post<{ message: string; order: Order }>(
      `${this.api}/customer/orders`,
      payload
    );
  }

  /** Lister mes commandes */
  getOrders(): Observable<{ data: Order[] }> {
    return this.http.get<{ data: Order[] }>(`${this.api}/customer/orders`);
  }

  /** Détail d'une commande */
  getOrder(orderId: number): Observable<Order> {
    return this.http.get<Order>(`${this.api}/customer/orders/${orderId}`);
  }

  /** Annuler une commande */
  cancelOrder(orderId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(
      `${this.api}/customer/orders/${orderId}/cancel`
    );
  }

  // ── Paiements ───────────────────────────────────────────────

  /**
   * Initier un paiement Stripe pour une commande.
   * Retourne le client_secret à passer à Stripe.js.
   */
  initiatePayment(orderId: number): Observable<PaymentInitResponse> {
    return this.http.post<PaymentInitResponse>(
      `${this.api}/customer/orders/${orderId}/payment`,
      {}
    );
  }

  /** Vérifier le statut d'un paiement */
  getPaymentStatus(orderId: number): Observable<PaymentStatusResponse> {
    return this.http.get<PaymentStatusResponse>(
      `${this.api}/customer/orders/${orderId}/payment/status`
    );
  }

  /** Historique des paiements */
  getPaymentHistory(): Observable<{ data: Payment[] }> {
    return this.http.get<{ data: Payment[] }>(`${this.api}/customer/payments`);
  }

  // ── Admin ────────────────────────────────────────────────────

  adminGetOrders(status?: string): Observable<{ data: Order[] }> {
    const params = status ? { status } : {};
    return this.http.get<{ data: Order[] }>(`${this.api}/admin/orders`, { params });
  }

  adminUpdateOrderStatus(
    orderId: number,
    status: string
  ): Observable<{ message: string; order: Order }> {
    return this.http.patch<{ message: string; order: Order }>(
      `${this.api}/admin/orders/${orderId}/status`,
      { status }
    );
  }

  adminGetPayments(status?: string): Observable<{ data: Payment[] }> {
    const params = status ? { status } : {};
    return this.http.get<{ data: Payment[] }>(`${this.api}/admin/payments`, { params });
  }

  adminRefund(paymentId: number): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(
      `${this.api}/admin/payments/${paymentId}/refund`,
      {}
    );
  }
}
