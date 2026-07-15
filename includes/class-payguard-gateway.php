<?php

if (! defined('ABSPATH')) exit;

class WC_PayGuard_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id                 = 'payguard';
        $this->icon               = PAYGUARD_PLUGIN_URL . 'assets/payguard-logo.png';
        $this->has_fields         = false;
        $this->method_title       = 'PayGuard';
        $this->method_description = 'Accept bKash, Nagad, TAP and more via PayGuard — zero commission.';
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        // Load settings
        $this->title              = $this->get_option('title', 'PayGuard (bKash / Nagad / TAP)');
        $this->description        = $this->get_option('description', 'Pay securely via bKash, Nagad or TAP wallet.');
        $this->enabled            = $this->get_option('enabled');
        $this->api_key            = $this->get_option('api_key');
        $this->base_url           = $this->get_option('base_url', 'https://app.sourcemonkey.online/api/v1');
        $this->connection_id      = $this->get_option('connection_id');
        $this->webhook_secret     = $this->get_option('webhook_secret');
        $this->provider           = $this->get_option('provider', 'bkash');
        $this->payment_page_mode  = $this->get_option('payment_page_mode', 'redirect');

        // Save settings hook
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable PayGuard Payment Gateway',
                'default' => 'yes',
            ],
            'title' => [
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Payment title shown to customer at checkout.',
                'default'     => 'PayGuard (bKash / Nagad / TAP)',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Description shown to customer at checkout.',
                'default'     => 'Pay securely via bKash, Nagad or TAP wallet. Zero commission.',
            ],
            'api_key' => [
                'title'       => 'API Key',
                'type'        => 'password',
                'description' => 'Your PayGuard API key. Get it from Dashboard → API & Webhooks.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'connection_id' => [
                'title'       => 'MFS Connection ID',
                'type'        => 'text',
                'description' => 'Your MFS connection ID from Dashboard → Connections.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'provider' => [
                'title'       => 'Default Provider',
                'type'        => 'select',
                'description' => 'Which payment provider to use.',
                'options'     => [
                    'bkash' => 'bKash',
                    'nagad' => 'Nagad',
                    'tap'   => 'TAP Wallet',
                ],
                'default'     => 'bkash',
            ],
            'webhook_secret' => [
                'title'       => 'Webhook Secret',
                'type'        => 'password',
                'description' => 'Your webhook secret from Dashboard → Connections → Webhook Secret.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'base_url' => [
                'title'       => 'API Base URL',
                'type'        => 'text',
                'description' => 'PayGuard API URL. Do not change unless instructed.',
                'default'     => 'https://app.sourcemonkey.online/api/v1',
                'desc_tip'    => true,
            ],
        ];
    }

    /**
     * Process payment when customer places order.
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        try {
            // Create PayGuard transaction
            $response = $this->api_request('POST', 'transactions', [
                'mfs_connection_id' => (int) $this->connection_id,
                'amount'            => (float) $order->get_total(),
                'reference_id'      => 'WC-' . $order_id . '-' . time(),
                'customer_name'     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_number'   => $order->get_billing_phone(),
                'customer_email'    => $order->get_billing_email(),
                'callback_url'      => rest_url('payguard/v1/ipn'),
                'metadata'          => [
                    'wc_order_id'  => $order_id,
                    'wc_order_key' => $order->get_order_key(),
                ],
            ]);

            $transactionId = $response['data']['id'];
            $referenceId   = $response['data']['reference_id'];

            // Store PayGuard reference on order
            $order->update_meta_data('_payguard_transaction_id', $transactionId);
            $order->update_meta_data('_payguard_reference_id',   $referenceId);
            $order->save();

            // Mark order as pending payment
            $order->update_status('pending', 'Awaiting PayGuard payment.');

            // Initiate payment with provider
            $checkout_url = $this->initiate_provider_payment($transactionId, $order);

            if (! $checkout_url) {
                throw new Exception('Could not get payment URL from PayGuard.');
            }

            // Reduce stock
            wc_reduce_stock_levels($order_id);

            // Clear cart
            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $checkout_url,
            ];

        } catch (Exception $e) {
            wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
            return ['result' => 'failure'];
        }
    }

    /**
     * Initiate payment with selected provider.
     */
    private function initiate_provider_payment(int $transactionId, $order): ?string
    {
        $provider = $this->provider;

        $response = $this->api_request('POST', "{$provider}/initiate/{$transactionId}");

        // bKash returns bkashURL
        if (isset($response['data']['checkout_url'])) {
            return $response['data']['checkout_url'];
        }

        // Nagad returns callBackUrl
        if (isset($response['data']['callBackUrl'])) {
            return $response['data']['callBackUrl'];
        }

        // TAP returns iframe config — redirect to PayGuard hosted payment page
        if (isset($response['data']['payment_url'])) {
            return $response['data']['payment_url'];
        }

        return null;
    }

    /**
     * Process refund.
     */
    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order         = wc_get_order($order_id);
        $transactionId = $order->get_meta('_payguard_transaction_id');

        if (! $transactionId) {
            return false;
        }

        try {
            $this->api_request('POST', "{$this->provider}/refund/{$transactionId}", [
                'amount' => $amount,
                'reason' => $reason,
            ]);

            $order->add_order_note("PayGuard refund of ৳{$amount} initiated.");
            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Make API request to PayGuard.
     */
    public function api_request(string $method, string $endpoint, array $body = []): array
    {
        $url  = rtrim($this->base_url, '/') . '/' . $endpoint;
        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (! empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('PayGuard API error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $message = $data['message'] ?? $data['error'] ?? 'Unknown error';
            throw new Exception("PayGuard API [{$code}]: {$message}");
        }

        return $data;
    }

    /**
     * Get webhook secret.
     */
    public function get_webhook_secret(): string
    {
        return $this->webhook_secret ?? '';
    }
}