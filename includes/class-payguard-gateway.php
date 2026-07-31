<?php

if (! defined('ABSPATH')) exit;

class WC_PayGuard_Gateway extends WC_Payment_Gateway
{
    /**
     * All supported providers.
     * key   → PayGuard API provider slug
     * label → shown to customer
     * api   → initiate endpoint prefix
     */
    public const PROVIDERS = [
        'bkash'  => ['label' => 'bKash',                    'api' => 'bkash'],
        'nagad'  => ['label' => 'Nagad',                    'api' => 'nagad'],
        'rocket' => ['label' => 'Rocket',                   'api' => 'rocket'],
        'upay'   => ['label' => 'Upay',                     'api' => 'upay'],
        'card'   => ['label' => 'Credit / Debit Card',      'api' => 'card'],
        'tap'    => ['label' => 'TAP Wallet',               'api' => 'tap'],
    ];

    public string $api_key        = '';
    public string $base_url       = '';
    public string $webhook_secret = '';

    public function __construct()
    {
        $this->id                 = 'payguard';
        $this->has_fields         = true;   // we render the radio buttons
        $this->method_title       = 'PayGuard';
        $this->method_description = 'Accept bKash, Nagad, Card/TAP, Rocket and Upay via PayGuard. Configure one API key and a connection ID per method below.';
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option('title', 'Mobile Banking / Card');
        $this->description    = $this->get_option('description', '');
        $this->api_key        = $this->get_option('api_key');
        $this->base_url       = rtrim($this->get_option('base_url', 'https://app.sourcemonkey.online/api/v1'), '/');
        $this->webhook_secret = $this->get_option('webhook_secret');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_payguard_callback', [$this, 'handle_callback']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    // -------------------------------------------------------------------------
    // ADMIN SETTINGS
    // -------------------------------------------------------------------------

    public function init_form_fields(): void
    {
        $fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable PayGuard',
                'default' => 'yes',
            ],
            'title' => [
                'title'       => 'Checkout Title',
                'type'        => 'text',
                'default'     => 'Mobile Banking / Card',
                'description' => 'Shown as the payment method name at checkout.',
            ],
            'description' => [
                'title'   => 'Checkout Description',
                'type'    => 'textarea',
                'default' => 'Choose your preferred payment method below.',
            ],

            // ---- Shared API credentials ----------------------------------
            'api_credentials_title' => [
                'title' => 'PayGuard API Credentials',
                'type'  => 'title',
                'description' => 'Get these from <a href="https://app.sourcemonkey.online/merchant/api" target="_blank">PayGuard Dashboard → API & Webhooks</a>.',
            ],
            'api_key' => [
                'title' => 'API Key',
                'type'  => 'password',
            ],
            'base_url' => [
                'title'   => 'API Base URL',
                'type'    => 'text',
                'default' => 'https://app.sourcemonkey.online/api/v1',
            ],
            'webhook_secret' => [
                'title'       => 'Webhook Secret',
                'type'        => 'password',
                'description' => 'Found in PayGuard Dashboard → Connections → Edit → Webhook Secret.',
            ],

            // ---- Per-provider enable + connection ID ---------------------
            'payment_methods_title' => [
                'title'       => 'Payment Methods',
                'type'        => 'title',
                'description' => 'Enable each method and enter its Connection ID from <a href="https://app.sourcemonkey.online/merchant/connections" target="_blank">PayGuard Dashboard → Connections</a>. Disabled methods are hidden at checkout.',
            ],
        ];

        // Add enable + connection_id pair for each provider
        foreach (self::PROVIDERS as $key => $meta) {
            $fields["enable_{$key}"] = [
                'title'   => $meta['label'],
                'type'    => 'checkbox',
                'label'   => "Enable {$meta['label']}",
                'default' => $key === 'bkash' ? 'yes' : 'no',
            ];
            $fields["connection_id_{$key}"] = [
                'title'       => "{$meta['label']} Connection ID",
                'type'        => 'text',
                'description' => "PayGuard Connection ID for {$meta['label']}.",
                'default'     => '',
            ];
        }

        $this->form_fields = $fields;
    }

    /**
     * Return only the enabled providers with their connection IDs.
     */
    public function get_enabled_providers(): array
    {
        $enabled = [];
        foreach (self::PROVIDERS as $key => $meta) {
            if ($this->get_option("enable_{$key}") === 'yes' && $this->get_option("connection_id_{$key}")) {
                $enabled[$key] = array_merge($meta, [
                    'connection_id' => $this->get_option("connection_id_{$key}"),
                ]);
            }
        }
        return $enabled;
    }

    // -------------------------------------------------------------------------
    // CHECKOUT RADIO BUTTON UI
    // -------------------------------------------------------------------------

    public function enqueue_scripts(): void
    {
        if (! is_checkout()) return;

        wp_enqueue_style(
            'payguard-checkout',
            PAYGUARD_PLUGIN_URL . 'assets/checkout.css',
            [],
            PAYGUARD_VERSION
        );

        wp_enqueue_script(
            'payguard-checkout',
            PAYGUARD_PLUGIN_URL . 'assets/checkout.js',
            [],
            PAYGUARD_VERSION,
            true
        );
    }

    /**
     * Logo img tags for each provider.
     * Card shows Visa + Mastercard + Amex side by side.
     * Replace logo filenames with your actual assets.
     */
    private function get_provider_logos(string $key): string
    {
        $dir = PAYGUARD_PLUGIN_URL . 'assets/logos/';

        $logos = [
            'bkash'  => '<img src="' . $dir . 'bkash.svg"          alt="bKash">',
            'nagad'  => '<img src="' . $dir . 'nagad.svg"           alt="Nagad">',
            'rocket' => '<img src="' . $dir . 'rocket.svg"          alt="Rocket">',
            'upay'   => '<img src="' . $dir . 'upay.svg"            alt="Upay">',
            'card'   => '<img src="' . $dir . 'card-visa.svg"       alt="Visa">'
                      . '<img src="' . $dir . 'card-mastercard.svg" alt="Mastercard">'
                      . '<img src="' . $dir . 'card-amex.svg"       alt="Amex">',
            'tap'    => '<img src="' . $dir . 'tap.svg"             alt="TAP Wallet">',
        ];

        return $logos[$key] ?? '';
    }

    /**
     * Render the radio button group inside the payment method box.
     */
    public function payment_fields(): void
    {
        $enabled = $this->get_enabled_providers();

        if (empty($enabled)) {
            echo '<p>' . esc_html__('No payment methods are currently available. Please contact the store owner.', 'payguard') . '</p>';
            return;
        }

        if ($this->description) {
            echo '<p class="payguard-description">' . esc_html($this->description) . '</p>';
        }

        echo '<div class="payguard-methods">';

        $first = true;
        foreach ($enabled as $key => $meta) {
            $id      = 'payguard_provider_' . $key;
            $checked = $first ? 'checked' : '';
            $first   = false;
            ?>
            <label class="payguard-method <?php echo $checked ? 'payguard-method--selected' : ''; ?>" for="<?php echo esc_attr($id); ?>">
                <input
                    type="radio"
                    id="<?php echo esc_attr($id); ?>"
                    name="payguard_provider"
                    value="<?php echo esc_attr($key); ?>"
                    <?php echo $checked; ?>
                />
                <span class="payguard-method__logo payguard-method__logo--<?php echo esc_attr($key); ?>">
                    <?php echo $this->get_provider_logos($key); ?>
                </span>
                <span class="payguard-method__label"><?php echo esc_html($meta['label']); ?></span>
            </label>
            <?php
        }

        echo '</div>';
    }

    /**
     * Validate that a provider was selected and it's still enabled.
     */
    public function validate_fields(): bool
    {
        $provider = sanitize_key($_POST['payguard_provider'] ?? '');
        $enabled  = $this->get_enabled_providers();

        if (! $provider || ! isset($enabled[$provider])) {
            wc_add_notice(__('Please select a payment method.', 'payguard'), 'error');
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // PROCESS PAYMENT
    // -------------------------------------------------------------------------

    public function process_payment($order_id): array
    {
        $provider = sanitize_key($_POST['payguard_provider'] ?? '');
        $enabled  = $this->get_enabled_providers();
        $order    = wc_get_order($order_id);

        if (! isset($enabled[$provider])) {
            wc_add_notice(__('Invalid payment method selected.', 'payguard'), 'error');
            return ['result' => 'failure'];
        }

        if (! $this->api_key) {
            wc_add_notice(__('PayGuard is not configured. Please contact the store owner.', 'payguard'), 'error');
            return ['result' => 'failure'];
        }

        $meta = $enabled[$provider];

        try {
            // Step 1: create transaction
            $response = $this->api_request('POST', 'transactions', [
                'mfs_connection_id' => (int) $meta['connection_id'],
                'amount'            => (float) $order->get_total(),
                'reference_id'      => 'WC-' . $order_id . '-' . time(),
                'customer_name'     => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'customer_number'   => $order->get_billing_phone(),
                'customer_email'    => $order->get_billing_email(),
                'callback_url'      => WC()->api_request_url('payguard_callback'),
                'webhook_url'       => rest_url('payguard/v1/ipn'),
                'metadata'          => [
                    'wc_order_id'  => (string) $order_id,
                    'wc_order_key' => $order->get_order_key(),
                    'provider'     => $provider,
                ],
            ]);

            $transactionId = $response['data']['id'];
            $referenceId   = $response['data']['reference_id'];

            $order->update_meta_data('_payguard_transaction_id', $transactionId);
            $order->update_meta_data('_payguard_reference_id',   $referenceId);
            $order->update_meta_data('_payguard_provider',       $provider);
            $order->save();

            $order->update_status('pending', ucfirst($provider) . ' payment initiated via PayGuard.');

            // Step 2: initiate with the chosen provider
            $checkoutUrl = $this->initiate_provider($provider, $meta['api'], $transactionId);

            if (! $checkoutUrl) {
                throw new \Exception('PayGuard did not return a checkout URL.');
            }

            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return ['result' => 'success', 'redirect' => $checkoutUrl];

        } catch (\Exception $e) {
            wc_get_logger()->error('PayGuard payment error: ' . $e->getMessage(), ['source' => 'payguard']);
            wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
            return ['result' => 'failure'];
        }
    }

    private function initiate_provider(string $provider, string $apiPrefix, int $transactionId): ?string
    {
        $response = $this->api_request('POST', "{$apiPrefix}/initiate/{$transactionId}");

        return $response['data']['checkout_url']
            ?? $response['data']['callBackUrl']
            ?? $response['data']['payment_url']
            ?? null;
    }

    // -------------------------------------------------------------------------
    // BROWSER RETURN CALLBACK
    // -------------------------------------------------------------------------

    public function handle_callback(): void
    {
        // Parse raw query string — PayGuard sometimes HTML-encodes & → &amp;
        $params = [];
        parse_str(html_entity_decode($_SERVER['QUERY_STRING'] ?? ''), $params);

        $status      = $params['status']       ?? $_GET['status']       ?? '';
        $referenceId = $params['reference_id'] ?? $_GET['reference_id'] ?? '';
        $txnId       = $params['txn_id']       ?? $_GET['txn_id']       ?? '';

        wc_get_logger()->info(
            "PayGuard callback: status={$status} ref={$referenceId} txn={$txnId}",
            ['source' => 'payguard']
        );

        $order = $this->find_order($referenceId);

        // CANCEL / FAILURE
        if (in_array($status, ['cancelled', 'failed', 'cancel'])) {
            if ($order && ! $order->is_paid()) {
                $provider = $order->get_meta('_payguard_provider') ?: 'PayGuard';
                $order->update_status('cancelled', ucfirst($provider) . ' payment cancelled by customer.');
            }
            wc_add_notice(__('Payment was cancelled. Please try again or choose another method.', 'payguard'), 'error');
            wp_redirect(wc_get_cart_url());
            exit;
        }

        // SUCCESS
        if ($status === 'success' && $order) {
            if (! $order->is_paid()) {
                $order->payment_complete($txnId ?: null);
                $provider = $order->get_meta('_payguard_provider') ?: 'PayGuard';
                $order->add_order_note(sprintf(
                    'PayGuard %s payment confirmed via callback. TXN: %s',
                    ucfirst($provider),
                    $txnId ?: 'N/A'
                ));
                if ($txnId) {
                    $order->update_meta_data('_payguard_txn_id', $txnId);
                    $order->save();
                }
            }
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }

        // Unknown
        wc_add_notice(__('Payment status unknown. Please contact us if your payment was deducted.', 'payguard'), 'notice');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    // -------------------------------------------------------------------------
    // REFUND
    // -------------------------------------------------------------------------

    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order         = wc_get_order($order_id);
        $transactionId = $order->get_meta('_payguard_transaction_id');
        $provider      = $order->get_meta('_payguard_provider') ?: 'bkash';

        if (! $transactionId) return false;

        $apiPrefix = self::PROVIDERS[$provider]['api'] ?? $provider;

        try {
            $this->api_request('POST', "{$apiPrefix}/refund/{$transactionId}", [
                'amount' => (float) $amount,
                'reason' => $reason,
            ]);
            $order->add_order_note("PayGuard refund of ৳{$amount} initiated via {$provider}.");
            return true;
        } catch (\Exception $e) {
            wc_get_logger()->error('PayGuard refund error: ' . $e->getMessage(), ['source' => 'payguard']);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function find_order(string $referenceId): ?\WC_Order
    {
        if (! $referenceId) return null;

        // Fast path: WC-{order_id}-{timestamp}
        if (preg_match('/^WC-(\d+)-\d+$/', $referenceId, $m)) {
            $order = wc_get_order((int) $m[1]);
            if ($order instanceof \WC_Order) return $order;
        }

        // Fallback: meta search
        $orders = wc_get_orders([
            'meta_key'   => '_payguard_reference_id',
            'meta_value' => $referenceId,
            'limit'      => 1,
        ]);
        return $orders[0] ?? null;
    }

    public function api_request(string $method, string $endpoint, array $body = []): array
    {
        $url  = $this->base_url . '/' . ltrim($endpoint, '/');
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
            throw new \Exception('PayGuard API error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            throw new \Exception("PayGuard API [{$code}]: " . ($data['message'] ?? $data['error'] ?? 'Unknown error'));
        }

        return $data ?? [];
    }

    public function get_webhook_secret(): string
    {
        return $this->webhook_secret ?? '';
    }
}
