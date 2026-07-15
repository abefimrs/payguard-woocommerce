<?php

if (! defined('ABSPATH')) exit;

class WC_PayGuard_IPN_Handler
{
    /**
     * Handle PayGuard IPN webhook.
     * POST /wp-json/payguard/v1/ipn
     */
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $raw_body  = $request->get_body();
        $signature = $request->get_header('X-PayGuard-Signature') ?? '';
        $payload   = json_decode($raw_body, true);

        if (empty($payload)) {
            return new \WP_REST_Response('Invalid payload', 400);
        }

        // Get gateway settings for webhook secret
        $gateway = self::get_gateway();
        if (! $gateway) {
            return new \WP_REST_Response('Gateway not found', 500);
        }

        $secret = $gateway->get_webhook_secret();

        // Verify signature
        if ($secret && $signature) {
            $expected = hash_hmac('sha256', $raw_body, $secret);
            if (! hash_equals($expected, $signature)) {
                wc_get_logger()->warning('PayGuard IPN: Invalid signature', ['source' => 'payguard']);
                return new \WP_REST_Response('Invalid signature', 401);
            }
        }

        $event       = $payload['event']        ?? '';
        $referenceId = $payload['reference_id'] ?? '';
        $amount      = $payload['amount']        ?? 0;
        $txnId       = $payload['mfs_transaction_id'] ?? '';
        $metadata    = $payload['metadata']      ?? [];
        $wcOrderId   = $metadata['wc_order_id']  ?? null;

        wc_get_logger()->info("PayGuard IPN: {$event} for {$referenceId}", ['source' => 'payguard']);

        if (! $wcOrderId) {
            // Try to find order by PayGuard reference
            $orders = wc_get_orders([
                'meta_key'   => '_payguard_reference_id',
                'meta_value' => $referenceId,
                'limit'      => 1,
            ]);
            $order = $orders[0] ?? null;
        } else {
            $order = wc_get_order($wcOrderId);
        }

        if (! $order) {
            wc_get_logger()->error("PayGuard IPN: Order not found for {$referenceId}", ['source' => 'payguard']);
            return new \WP_REST_Response('Order not found', 404);
        }

        // Skip if already processed
        if ($order->is_paid()) {
            return new \WP_REST_Response('OK', 200);
        }

        if ($event === 'payment.success') {
            // Mark order as paid
            $order->payment_complete($txnId);
            $order->add_order_note(
                sprintf(
                    'PayGuard payment completed. Reference: %s | TXN: %s | Amount: ৳%s',
                    $referenceId,
                    $txnId,
                    number_format((float) $amount, 2)
                )
            );
            $order->update_meta_data('_payguard_txn_id', $txnId);
            $order->save();

            wc_get_logger()->info("PayGuard IPN: Order #{$wcOrderId} marked as paid", ['source' => 'payguard']);

        } elseif ($event === 'payment.failed') {
            $reason = $payload['failure_reason'] ?? 'Payment failed';
            $order->update_status('failed', "PayGuard payment failed: {$reason}");
            $order->add_order_note("PayGuard payment failed. Reason: {$reason}");

            wc_get_logger()->warning("PayGuard IPN: Order #{$wcOrderId} payment failed", ['source' => 'payguard']);
        }

        // Always return 200 to PayGuard
        return new \WP_REST_Response('OK', 200);
    }

    /**
     * Get PayGuard gateway instance.
     */
    private static function get_gateway(): ?WC_PayGuard_Gateway
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return $gateways['payguard'] ?? null;
    }
}