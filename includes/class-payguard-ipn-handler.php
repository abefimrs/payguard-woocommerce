<?php

if (! defined('ABSPATH')) exit;

class WC_PayGuard_IPN_Handler
{
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $raw_body  = $request->get_body();
        $signature = $request->get_header('X-PayGuard-Signature') ?? '';
        $payload   = json_decode($raw_body, true);

        if (empty($payload)) {
            return new \WP_REST_Response('Invalid payload', 400);
        }

        // Verify HMAC-SHA256 signature
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = $gateways['payguard'] ?? null;

        if ($gateway) {
            $secret = $gateway->get_webhook_secret();
            if ($secret && $signature) {
                $expected = hash_hmac('sha256', $raw_body, $secret);
                if (! hash_equals($expected, $signature)) {
                    wc_get_logger()->warning('PayGuard IPN: invalid signature', ['source' => 'payguard']);
                    return new \WP_REST_Response('Invalid signature', 401);
                }
            }
        }

        $event       = $payload['event']               ?? '';
        $referenceId = $payload['reference_id']        ?? '';
        $amount      = $payload['amount']              ?? 0;
        $txnId       = $payload['mfs_transaction_id']  ?? $payload['txn_id'] ?? '';
        $metadata    = $payload['metadata']            ?? [];
        $wcOrderId   = $metadata['wc_order_id']        ?? null;

        wc_get_logger()->info(
            "PayGuard IPN: event={$event} ref={$referenceId} txn={$txnId}",
            ['source' => 'payguard']
        );

        $order = self::find_order($wcOrderId, $referenceId);

        if (! $order) {
            wc_get_logger()->error("PayGuard IPN: order not found. ref={$referenceId}", ['source' => 'payguard']);
            return new \WP_REST_Response('OK', 200); // 200 so PayGuard stops retrying
        }

        if ($event === 'payment.success') {
            if (! $order->is_paid()) {
                $order->payment_complete($txnId);
                $provider = $order->get_meta('_payguard_provider') ?: 'PayGuard';
                $order->add_order_note(sprintf(
                    'PayGuard %s payment confirmed via webhook. TXN: %s | Amount: ৳%s',
                    ucfirst($provider),
                    $txnId,
                    number_format((float) $amount, 2)
                ));
                if ($txnId) {
                    $order->update_meta_data('_payguard_txn_id', $txnId);
                    $order->save();
                }
                wc_get_logger()->info("PayGuard IPN: order #{$order->get_id()} marked paid.", ['source' => 'payguard']);
            }

        } elseif ($event === 'payment.failed') {
            if (! $order->is_paid()) {
                $reason = $payload['failure_reason'] ?? 'Payment failed';
                $order->update_status('failed', "PayGuard payment failed: {$reason}");
                $order->add_order_note("PayGuard payment failed. Reason: {$reason}");
            }
        }

        return new \WP_REST_Response('OK', 200);
    }

    private static function find_order(?string $wcOrderId, string $referenceId): ?\WC_Order
    {
        if ($wcOrderId) {
            $order = wc_get_order((int) $wcOrderId);
            if ($order instanceof \WC_Order) return $order;
        }

        if ($referenceId && preg_match('/^WC-(\d+)-\d+$/', $referenceId, $m)) {
            $order = wc_get_order((int) $m[1]);
            if ($order instanceof \WC_Order) return $order;
        }

        if ($referenceId) {
            $orders = wc_get_orders([
                'meta_key'   => '_payguard_reference_id',
                'meta_value' => $referenceId,
                'limit'      => 1,
            ]);
            return $orders[0] ?? null;
        }

        return null;
    }
}
