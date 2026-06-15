<?php

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    die;
}

/**
 * Sends WooCommerce order change events to NoParcels so orders are imported without manual refresh.
 */
class MP_Webhooks
{
    private const ENDPOINT = 'https://app.noparcels.com/shops/woocommerce/webhook/orderactions';

    public function __construct()
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'checkout_order_processed'], 20, 1);
        add_action('woocommerce_order_status_changed', [$this, 'order_status_changed'], 20, 4);
    }

    public function checkout_order_processed($order_id)	{		if (! function_exists('wc_get_order')) {			return;		}		$order = wc_get_order((int) $order_id);		if (! $order) {			return;		}		$this->send_order_event((int) $order_id, 'order.created', $order);	}

    public function order_status_changed($order_id, $old_status, $new_status, $order)	{		if ($old_status === $new_status) {			return;		}		// Jeigu statusas pakeistas į on-hold — nesiunčiam į NoParcels		if ($new_status === 'on-hold') {			return;		}		$this->send_order_event((int) $order_id, 'order.status_changed', $order, [			'old_status' => $old_status,			'new_status' => $new_status,		]);	}

    private function send_order_event(int $order_id, string $event, $order = null, array $extra = []): void
    {
        if ($order_id <= 0) {
            return;
        }

        if (! function_exists('wc_get_order')) {
            return;
        }

        $api_key = MultiParcels()->options->get_api_key();
        if (! $api_key) {
            return;
        }

        if (! $order) {			$order = wc_get_order($order_id);		}		if (! $order) {			return;		}		$order_status = $order->get_status();		$allowed_statuses = [			'processing',			'completed',		];		if (! in_array($order_status, $allowed_statuses, true)) {			return;		}

        $payload = array_merge([
            'event' => $event,
            'order_id' => $order_id,
            'order_status' => $order_status,
            'site_url' => home_url(),
            'wp_url' => get_bloginfo('wpurl'),
            'sent_at' => gmdate('c'),
        ], $extra);

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 0.01,
            'blocking' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'X-NoParcels-Source' => home_url(),
                'X-NoParcels-Event' => $event,
            ],
            'body' => wp_json_encode($payload),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            error_log('NoParcels order webhook failed to dispatch: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log('NoParcels order webhook returned HTTP ' . $status . ': ' . wp_remote_retrieve_body($response));
        }
    }
}

return new MP_Webhooks();