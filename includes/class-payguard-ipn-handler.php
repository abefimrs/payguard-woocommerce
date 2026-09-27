<?php

if (! defined('ABSPATH')) exit;

class WC_PayGuard_IPN_Handler
{
    /**
     * Handle incoming IPN from PayGuard.
     * Called via REST API — no nonce needed (server-to-server, signature verified).
     */
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload   = $request->get_body();
        $signature = $request->get_header('x-payguard-signature');
        $data      = $request->get_json_params();

        // Verify signature
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = $gateways['payguard'] ?? null;

        if (! $gateway) {
            return new \WP_REST_Response(['error' => 'Gateway not found'], 500);
        }

        $secret = $gateway->get_webhook_secret();

        if (! empty($secret)) {
            $expected  = hash_hmac('sha256', $payload, $secret);
            $received  = sanitize_text_field($signature ?? '');

            if (! hash_equals($expected, $received)) {
                return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
            }
        }

        // Process event
        $event    = isset($data['event']) ? sanitize_text_field($data['event']) : '';
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
        $order_id = isset($metadata['wc_order_id']) ? absint($metadata['wc_order_id']) : 0;

        if (! $order_id) {
            return new \WP_REST_Response(['error' => 'No order ID in metadata'], 400);
        }

        $order = wc_get_order($order_id);

        if (! $order) {
            return new \WP_REST_Response(['error' => 'Order not found'], 404);
        }

        // Verify order key
        $order_key    = isset($metadata['wc_order_key']) ? sanitize_text_field($metadata['wc_order_key']) : '';
        $stored_key   = $order->get_order_key();

        if (! empty($order_key) && ! hash_equals($stored_key, $order_key)) {
            return new \WP_REST_Response(['error' => 'Order key mismatch'], 401);
        }

        if ($event === 'payment.success') {
            if (! $order->is_paid()) {
                $reference_id = isset($data['reference_id']) ? sanitize_text_field($data['reference_id']) : '';
                $txn_id       = isset($data['mfs_transaction_id']) ? sanitize_text_field($data['mfs_transaction_id']) : '';

                $order->payment_complete($reference_id);
                $order->update_meta_data('_payguard_mfs_txn_id', $txn_id);
                $order->save();

                $order->add_order_note(
                    sprintf(
                        /* translators: 1: PayGuard reference, 2: MFS transaction ID */
                        esc_html__('PayGuard payment confirmed. Reference: %1$s | MFS TXN: %2$s', 'payguard-for-woocommerce'),
                        esc_html($reference_id),
                        esc_html($txn_id)
                    )
                );
            }

        } elseif ($event === 'payment.failed') {
            $reason = isset($data['failure_reason']) ? sanitize_text_field($data['failure_reason']) : 'Unknown';

            if ($order->get_status() === 'pending') {
                $order->update_status(
                    'failed',
                    sprintf(
                        /* translators: %s: failure reason */
                        esc_html__('PayGuard payment failed: %s', 'payguard-for-woocommerce'),
                        esc_html($reason)
                    )
                );
            }
        }

        return new \WP_REST_Response(['success' => true], 200);
    }
}
