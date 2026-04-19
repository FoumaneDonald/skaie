// src/app/features/checkout/checkout-payment/checkout-payment.component.ts
import {
  Component,
  OnInit,
  OnDestroy,
  ElementRef,
  ViewChild,
  AfterViewInit,
} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { Subject, interval } from 'rxjs';
import { takeUntil, switchMap, filter, take } from 'rxjs/operators';
import { PaymentService, PaymentStatusResponse } from '../../../core/services/payment.service';

// Déclaration globale Stripe.js (chargé via CDN dans index.html)
declare const Stripe: any;

@Component({
  selector: 'app-checkout-payment',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './checkout-payment.component.html',
  styleUrls: ['./checkout-payment.component.scss'],
})
export class CheckoutPaymentComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('cardElement') cardElementRef!: ElementRef;

  // ── State ────────────────────────────────────────────────────
  orderId!: number;
  orderTotal = 0;
  currency = 'eur';

  loading = true;
  processing = false;
  errorMessage = '';
  successMessage = '';
  paymentStatus: PaymentStatusResponse | null = null;

  // ── Stripe objects ───────────────────────────────────────────
  private stripe: any;
  private elements: any;
  private cardElement: any;
  private clientSecret = '';

  private destroy$ = new Subject<void>();

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private paymentService: PaymentService
  ) {}

  ngOnInit(): void {
    // Récupérer l'orderId depuis les query params ou la route
    this.orderId = Number(this.route.snapshot.paramMap.get('orderId'));
    if (!this.orderId) {
      this.errorMessage = 'Commande introuvable.';
      this.loading = false;
      return;
    }

    // Vérifier d'abord si déjà payé
    this.paymentService.getPaymentStatus(this.orderId).subscribe({
      next: (status) => {
        if (status.payment_status === 'succeeded') {
          this.paymentStatus = status;
          this.successMessage = 'Cette commande a déjà été payée avec succès.';
          this.loading = false;
          return;
        }
        // Sinon initier le paiement Stripe
        this.initStripePayment();
      },
      error: () => {
        // Pas encore de paiement initié → on crée
        this.initStripePayment();
      },
    });
  }

  ngAfterViewInit(): void {
    // L'élément Stripe est monté après que loading = false
  }

  // ── Initialisation Stripe ────────────────────────────────────

  private initStripePayment(): void {
    this.paymentService.initiatePayment(this.orderId).subscribe({
      next: (res) => {
        this.clientSecret = res.client_secret;
        this.orderTotal   = res.amount;
        this.currency     = res.currency;
        this.loading      = false;
        // Monter Stripe Elements après que la vue soit rendue
        setTimeout(() => this.mountStripeElements(), 0);
      },
      error: (err) => {
        this.errorMessage = err.error?.message || 'Impossible d\'initier le paiement.';
        this.loading = false;
      },
    });
  }

  private mountStripeElements(): void {
    // Clé publique Stripe (pk_test_...)
    const stripePublicKey = (window as any)['STRIPE_PUBLIC_KEY']
      || 'pk_test_VOTRE_CLE_PUBLIQUE'; // remplacer ou injecter via environment

    this.stripe   = Stripe(stripePublicKey);
    this.elements = this.stripe.elements();

    this.cardElement = this.elements.create('card', {
      style: {
        base: {
          color: '#1a202c',
          fontFamily: '"Inter", sans-serif',
          fontSize: '16px',
          '::placeholder': { color: '#a0aec0' },
        },
        invalid: { color: '#e53e3e' },
      },
      hidePostalCode: false,
    });

    if (this.cardElementRef?.nativeElement) {
      this.cardElement.mount(this.cardElementRef.nativeElement);

      this.cardElement.on('change', (event: any) => {
        this.errorMessage = event.error ? event.error.message : '';
      });
    }
  }

  // ── Soumettre le paiement ────────────────────────────────────

  async submitPayment(): Promise<void> {
    if (this.processing || !this.stripe || !this.cardElement) return;

    this.processing  = true;
    this.errorMessage = '';

    const { error, paymentIntent } = await this.stripe.confirmCardPayment(
      this.clientSecret,
      {
        payment_method: {
          card: this.cardElement,
        },
      }
    );

    if (error) {
      this.errorMessage = error.message || 'Paiement refusé.';
      this.processing = false;
      return;
    }

    if (paymentIntent?.status === 'succeeded') {
      this.processing = false;
      this.pollPaymentStatus();
    }
  }

  /**
   * Poller notre API toutes les 2s pour confirmer que le webhook
   * a bien mis à jour le statut en base.
   */
  private pollPaymentStatus(): void {
    this.successMessage = 'Paiement confirmé par Stripe. Mise à jour en cours…';

    interval(2000)
      .pipe(
        takeUntil(this.destroy$),
        take(10), // max 10 tentatives = 20s
        switchMap(() => this.paymentService.getPaymentStatus(this.orderId)),
        filter((s) => s.payment_status === 'succeeded')
      )
      .subscribe({
        next: (status) => {
          this.paymentStatus  = status;
          this.successMessage = '✅ Paiement validé ! Votre commande est en cours de traitement.';
          // Rediriger vers la page de confirmation après 2s
          setTimeout(() => {
            this.router.navigate(['/orders', this.orderId, 'confirmation']);
          }, 2000);
        },
        error: () => {
          this.successMessage = 'Paiement confirmé. Redirection…';
          setTimeout(() => this.router.navigate(['/orders']), 2000);
        },
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.cardElement?.destroy();
  }

  // ── Helpers template ─────────────────────────────────────────

  get formattedAmount(): string {
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: this.currency.toUpperCase(),
    }).format(this.orderTotal);
  }
}
