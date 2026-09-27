<?php

if (! defined('ABSPATH')) exit;

class WC_PayGuard_Gateway extends WC_Payment_Gateway
{
    public string $api_key        = '';
    public string $base_url       = '';
    public string $connection_id  = '';
    public string $webhook_secret = '';
    public string $provider       = 'bkash';

    public function __construct()
    {
        $this->id                 = 'payguard';
        $this->has_fields         = false;
        $this->method_title       = __('PayGuard', 'payguard-payment-gateway-for-woocommerce');
        $this->method_description = __('Accept bKash, Nagad, Rocket and more via PayGuard — zero commission gateway for Bangladesh.', 'payguard-payment-gateway-for-woocommerce');
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option('title', __('PayGuard (bKash / Nagad / Rocket)', 'payguard-payment-gateway-for-woocommerce'));
        $this->description    = $this->get_option('description', __('Pay securely via bKash, Nagad or Rocket. Zero commission.', 'payguard-payment-gateway-for-woocommerce'));
        $this->enabled        = $this->get_option('enabled');
        $this->api_key        = $this->get_option('api_key', '');
        $this->base_url       = $this->get_option('base_url', 'https://app.sourcemonkey.online/api/v1');
        $this->connection_id  = $this->get_option('connection_id', '');
        $this->webhook_secret = $this->get_option('webhook_secret', '');
        $this->provider       = $this->get_option('provider', 'bkash');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'payguard-payment-gateway-for-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable PayGuard Payment Gateway', 'payguard-payment-gateway-for-woocommerce'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Title', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Payment title shown to customer at checkout.', 'payguard-payment-gateway-for-woocommerce'),
                'default'     => __('PayGuard (bKash / Nagad / Rocket)', 'payguard-payment-gateway-for-woocommerce'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'textarea',
                'description' => __('Description shown to customer at checkout.', 'payguard-payment-gateway-for-woocommerce'),
                'default'     => __('Pay securely via bKash, Nagad or Rocket. Zero commission.', 'payguard-payment-gateway-for-woocommerce'),
            ],
            'api_key' => [
                'title'       => __('API Key', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'password',
                'description' => __('Your PayGuard API key. Get it from Dashboard → API & Webhooks.', 'payguard-payment-gateway-for-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'connection_id' => [
                'title'       => __('MFS Connection ID', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Your MFS connection ID from Dashboard → Connections.', 'payguard-payment-gateway-for-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'provider' => [
                'title'       => __('Default Provider', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'select',
                'description' => __('Which payment provider to use.', 'payguard-payment-gateway-for-woocommerce'),
                'options'     => [
                    'bkash'  => __('bKash', 'payguard-payment-gateway-for-woocommerce'),
                    'nagad'  => __('Nagad', 'payguard-payment-gateway-for-woocommerce'),
                    'rocket' => __('Rocket', 'payguard-payment-gateway-for-woocommerce'),
                    'tap'    => __('TAP Wallet', 'payguard-payment-gateway-for-woocommerce'),
                ],
                'default' => 'bkash',
            ],
            'webhook_secret' => [
                'title'       => __('Webhook Secret', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'password',
                'description' => __('Your webhook secret from Dashboard → Connections → Webhook Secret.', 'payguard-payment-gateway-for-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'base_url' => [
                'title'       => __('API Base URL', 'payguard-payment-gateway-for-woocommerce'),
                'type'        => 'text',
                'description' => __('PayGuard API URL. Do not change unless instructed.', 'payguard-payment-gateway-for-woocommerce'),
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

            $transaction_id = $response['data']['id'];
            $reference_id   = $response['data']['reference_id'];

            $order->update_meta_data('_payguard_transaction_id', $transaction_id);
            $order->update_meta_data('_payguard_reference_id',   $reference_id);
            $order->save();

            $order->update_status('pending', __('Awaiting PayGuard payment.', 'payguard-payment-gateway-for-woocommerce'));

            $checkout_url = $this->initiate_provider_payment($transaction_id);

            if (! $checkout_url) {
                throw new \Exception(esc_html__('Could not get payment URL from PayGuard.', 'payguard-payment-gateway-for-woocommerce'));
            }

            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $checkout_url,
            ];

        } catch (\Exception $e) {
            wc_add_notice(
                esc_html__('Payment error: ', 'payguard-payment-gateway-for-woocommerce') . esc_html($e->getMessage()),
                'error'
            );
            return ['result' => 'failure'];
        }
    }

    /**
     * Initiate payment with selected provider.
     */
    private function initiate_provider_payment(int $transaction_id): ?string
    {
        $provider = sanitize_key($this->provider);
        $response = $this->api_request('POST', "{$provider}/initiate/{$transaction_id}");

        if (isset($response['data']['checkout_url'])) {
            return esc_url_raw($response['data']['checkout_url']);
        }

        if (isset($response['data']['callBackUrl'])) {
            return esc_url_raw($response['data']['callBackUrl']);
        }

        if (isset($response['data']['payment_url'])) {
            return esc_url_raw($response['data']['payment_url']);
        }

        return null;
    }

    /**
     * Process refund.
     */
    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order          = wc_get_order($order_id);
        $transaction_id = $order->get_meta('_payguard_transaction_id');

        if (! $transaction_id) {
            return false;
        }

        try {
            $this->api_request('POST', sanitize_key($this->provider) . "/refund/{$transaction_id}", [
                'amount' => $amount,
                'reason' => sanitize_text_field($reason),
            ]);

            $order->add_order_note(
                sprintf(
                    /* translators: %s: refund amount */
                    esc_html__('PayGuard refund of %s initiated.', 'payguard-payment-gateway-for-woocommerce'),
                    esc_html(wc_price($amount))
                )
            );
            return true;

        } catch (\Exception $e) {
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
            throw new \Exception(
                esc_html__('PayGuard API error: ', 'payguard-payment-gateway-for-woocommerce') .
                esc_html($response->get_error_message())
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $message = isset($data['message']) ? sanitize_text_field($data['message']) :
                       (isset($data['error'])   ? sanitize_text_field($data['error'])   : 'Unknown error');
            throw new \Exception(
                esc_html(sprintf('PayGuard API [%d]: %s', $code, $message))
            );
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
