<?php
if (!defined('ABSPATH')) {
    die;
}
class MP_Real_Time_Prices_Api
{
    const ENDPOINT = 'api/translate/mp/real_time_prices';
    public function get_rates($carrier_code, $package)
    {
        $debug = $this->setting('real_time_prices_debug', 'no') === 'yes';
        $this->log('get_rates_start', [
            'carrier_code' => $carrier_code,
            'endpoint' => self::ENDPOINT,
            'debug' => $debug,
        ]);
        $payload = $this->build_payload($carrier_code, $package);
        if (is_wp_error($payload)) {
            $this->log('payload_error', [
                'code' => $payload->get_error_code(),
                'message' => $payload->get_error_message(),
            ]);
            return $payload;
        }
        if ($debug) {
            $payload['debug_it'] = '1';
        }
        $cache_key = 'mp_rt_' . md5(wp_json_encode($payload));
        if (!$debug) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                $this->log('cache_hit', [
                    'cache_key' => $cache_key,
                    'rates_count' => is_array($cached) ? count($cached) : null,
                ]);
                return $cached;
            }
        }
        $this->log('request_payload', $payload);
        $response = MultiParcels()->api_client->request(self::ENDPOINT, 'POST', $payload);
        if (!$response) {
            $this->log('response_error', ['message' => 'No response object returned']);
            return new WP_Error('noparcels_real_time_api_error', __('Noparcels real-time price request failed.', 'multiparcels-shipping-for-woocommerce'));
        }
        $this->log('response_meta', [
            'was_successful' => $response->was_successful(),
            'has_error' => method_exists($response, 'has_error') ? $response->has_error() : null,
            'error_message' => method_exists($response, 'get_error_message') ? $response->get_error_message() : null,
            'validation_errors' => method_exists($response, 'get_validation_errors') ? $response->get_validation_errors() : null,
            'full_response' => method_exists($response, 'get_full_response') ? $response->get_full_response() : null,
        ]);
        if (!$response->was_successful()) {
            return new WP_Error('noparcels_real_time_api_error', __('Noparcels real-time price request failed.', 'multiparcels-shipping-for-woocommerce'));
        }
        $data = $response->get_data();
        $this->log('response_data', $data);
        $rates = $this->extract_rates($data);
        if (is_wp_error($rates)) {
            $this->log('rates_extract_error', [
                'code' => $rates->get_error_code(),
                'message' => $rates->get_error_message(),
                'data' => $data,
            ]);
            return $rates;
        }
        $ttl = absint($this->setting('real_time_prices_cache_ttl', 10));
        $ttl = max(1, min(60, $ttl));
        set_transient($cache_key, $rates, $ttl * MINUTE_IN_SECONDS);
        $this->log('rates_ready', [
            'rates_count' => count($rates),
            'cache_key' => $cache_key,
            'ttl_minutes' => $ttl,
        ]);
        return $rates;
    }
    public function first_rate($carrier_code, $package)
    {
        $rates = $this->get_rates($carrier_code, $package);
        if (is_wp_error($rates)) {
            return $rates;
        }
        $best_rate = null;
        foreach ($rates as $rate) {
            if (!is_array($rate)) {
                $rate = (array)$rate;
            }
            if (!isset($rate['total_price'])) {
                continue;
            }
            if ($best_rate === null || (float)$rate['total_price'] < (float)$best_rate['total_price']) {
                $best_rate = $rate;
            }
        }
        if ($best_rate === null) {
            $this->log('first_rate_error_no_total_price', ['rates' => $rates]);
            return new WP_Error('noparcels_real_time_no_price', __('No valid real-time price returned.', 'multiparcels-shipping-for-woocommerce'));
        }
        $this->log('first_rate_selected', $best_rate);
        return $best_rate;
    }
    private function extract_rates($data)
    {
        if (!is_array($data)) {
            return new WP_Error('noparcels_real_time_invalid_response', __('Invalid real-time response.', 'multiparcels-shipping-for-woocommerce'));
        }
        if (array_key_exists('success', $data) && !$data['success']) {
            $message = !empty($data['error']) ? $data['error'] : __('No real-time rates returned.', 'multiparcels-shipping-for-woocommerce');
            return new WP_Error('noparcels_real_time_backend_error', $message);
        }
        if (isset($data['rates']) && is_array($data['rates'])) {
            if (empty($data['rates'])) {
                $message = !empty($data['error']) ? $data['error'] : __('No real-time rates returned.', 'multiparcels-shipping-for-woocommerce');
                return new WP_Error('noparcels_real_time_no_rates', $message);
            }
            return $data['rates'];
        }
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }
        if (isset($data['total_price'])) {
            return [$data];
        }
        $message = !empty($data['error']) ? $data['error'] : __('No real-time rates returned.', 'multiparcels-shipping-for-woocommerce');
        return new WP_Error('noparcels_real_time_no_rates', $message);
    }
    private function build_payload($carrier_code, $package)
    {
        if (!function_exists('WC') || !WC() || !WC()->customer) {
            return new WP_Error('noparcels_real_time_missing_wc_customer', __('WooCommerce customer data is not available.', 'multiparcels-shipping-for-woocommerce'));
        }
        $receiver_country = WC()->customer->get_shipping_country() ?: WC()->customer->get_billing_country();
        $receiver_postcode = WC()->customer->get_shipping_postcode() ?: WC()->customer->get_billing_postcode();
        $receiver_city = WC()->customer->get_shipping_city() ?: WC()->customer->get_billing_city();
        $receiver_address = WC()->customer->get_shipping_address_1() ?: WC()->customer->get_billing_address_1();
        $this->log('customer_address_detected', [
            'country' => $receiver_country,
            'postcode' => $receiver_postcode,
            'city' => $receiver_city,
            'address_1' => $receiver_address,
        ]);
        if (!$receiver_country) {
            return new WP_Error('noparcels_real_time_missing_country', __('Customer country is required for real-time prices.', 'multiparcels-shipping-for-woocommerce'));
        }
        $receiver_country = strtoupper($receiver_country);
        $sender = $this->sender();
		
		if (
			empty($receiver_postcode) ||
			empty($receiver_city) ||
			empty($receiver_address)
		) {
			$this->log('incomplete_receiver_address', [
				'country' => $receiver_country,
				'postcode' => $receiver_postcode,
				'city' => $receiver_city,
				'address_1' => $receiver_address,
			]);

			return new WP_Error(
				'noparcels_real_time_incomplete_address',
				__('Customer address is incomplete.', 'multiparcels-shipping-for-woocommerce')
			);
		}

        $this->log('sender_detected', $sender);
        if (empty($sender['country_code'])) {
            return new WP_Error('noparcels_real_time_missing_sender', __('Default sender country is required for real-time prices.', 'multiparcels-shipping-for-woocommerce'));
        }
        $weight = $this->cart_weight($package);
        $package_size = $this->package_size($package);
		
		
		$is_international = !empty($sender['country_code'])
			&& !empty($receiver_country)
			&& strtoupper($sender['country_code']) !== strtoupper($receiver_country);

		if (
			in_array($carrier_code, ['post_lt', 'lp_express'], true) &&
			$is_international &&
			$weight > 0.05
		) {
			$configured_default_size = $this->setting('real_time_prices_package_size', '10x10x10');
			$configured_default_size = strtolower(str_replace([' ', '×'], ['', 'x'], (string)$configured_default_size));

			if (!preg_match('/^\d+(\.\d+)?x\d+(\.\d+)?x\d+(\.\d+)?$/', $configured_default_size)) {
				$configured_default_size = '10x10x10';
			}

			$package_size = $configured_default_size;

			$this->log('package_size_overridden_for_post_international', [
				'carrier_code' => $carrier_code,
				'weight' => $weight,
				'package_size' => $package_size,
			]);
		}


        $goods_items = $this->goods($package, $weight);
        $goods = $this->goods_for_carrier($carrier_code, $goods_items, $package, $weight);
        $is_domestic_lt = strtoupper($sender['country_code']) === 'LT'
		&& strtoupper($receiver_country) === 'LT';

		if ($carrier_code === 'post_lt') {
			$pickup_type = 'post_office';
			$delivery_type = 'hands';
		} else {
			$pickup_type = 'hands';
			$delivery_type = 'hands';
		}
        $services = [];

		if (
			$carrier_code === 'post_lt' &&
			!empty($sender['country_code']) &&
			!empty($receiver_country) &&
			strtoupper($sender['country_code']) !== strtoupper($receiver_country)
		) {
			$services[] = [
				'code' => 'with_tracking',
				'enabled' => true,
			];
		}

        return [
            'source' => 'woocommerce_real_time_prices',
            'sender' => $sender,
            'receiver' => [
                'street' => $receiver_address,
                'city' => $receiver_city,
                'postal_code' => $receiver_postcode,
                'country_code' => $receiver_country,
            ],
            'pickup' => [
                'packages' => 1,
                'package_sizes' => [$package_size],
                'weight' => $weight,
                'type' => $pickup_type,
            ],
            'delivery' => [
                'courier' => $carrier_code,
                'type' => $delivery_type,
            ],
            'goods' => $goods,
            'services' => $services,
        ];
    }
    private function sender()
    {
        $default_sender_id = MultiParcels()->options->get_default_sender_location();
        $sender = $default_sender_id ? MultiParcels()->options->get_sender_location($default_sender_id) : [];
        return [
            'street' => isset($sender['street']) ? $sender['street'] : '',
            'city' => isset($sender['city']) ? $sender['city'] : '',
            'postal_code' => isset($sender['postal_code']) ? $sender['postal_code'] : '',
            'country_code' => isset($sender['country_code']) ? strtoupper($sender['country_code']) : '',
        ];
    }
    private function weight_to_kg($weight)
    {
        $weight = (float)$weight;
        if ($weight <= 0) {
            return 0;
        }

        $from_unit = get_option('woocommerce_weight_unit', 'kg');

        if (function_exists('wc_get_weight')) {
            return (float) wc_get_weight($weight, 'kg', $from_unit);
        }

        if ($from_unit === 'g') {
            return $weight / 1000;
        }

        if ($from_unit === 'lbs') {
            return $weight * 0.45359237;
        }

        if ($from_unit === 'oz') {
            return $weight * 0.0283495231;
        }

        return $weight;
    }
    private function cart_weight($package)
    {
        $weight = 0;
        if (isset($package['contents']) && is_array($package['contents'])) {
            foreach ($package['contents'] as $item) {
                if (!isset($item['data']) || !$item['data']->needs_shipping()) {
                    continue;
                }
                $product_weight = $this->weight_to_kg((float)$item['data']->get_weight());
                $weight += $product_weight * (int)$item['quantity'];
            }
        }
        if ($weight <= 0 && WC()->cart) {
            $weight = $this->weight_to_kg((float)WC()->cart->cart_contents_weight);
        }
        $weight = max(0.001, round($weight, 3));
        $this->log('cart_weight_detected', [
            'weight_kg' => $weight,
            'woocommerce_weight_unit' => get_option('woocommerce_weight_unit'),
        ]);
        return $weight;
    }
    private function goods($package, $total_weight)
    {
        $goods = [];
        if (isset($package['contents']) && is_array($package['contents'])) {
            foreach ($package['contents'] as $item) {
                if (!isset($item['data']) || !$item['data']->needs_shipping()) {
                    continue;
                }
                $quantity = max(1, (int)$item['quantity']);
                $line_total = isset($item['line_total']) ? (float)$item['line_total'] : 0;
                $product_weight = $this->weight_to_kg((float)$item['data']->get_weight());
                $goods[] = [
                    'description' => $item['data']->get_name(),
                    'value' => $line_total > 0 ? round($line_total, 2) : 0.01,
                    'currency' => get_woocommerce_currency(),
                    'weight' => max(0.001, round($product_weight * $quantity, 3)),
                    'quantity' => $quantity,
                ];
            }
        }
        if (empty($goods)) {
            $goods[] = [
                'description' => __('WooCommerce cart items', 'multiparcels-shipping-for-woocommerce'),
                'value' => isset($package['contents_cost']) ? max(0.01, round((float)$package['contents_cost'], 2)) : 0.01,
                'currency' => get_woocommerce_currency(),
                'weight' => $total_weight,
                'quantity' => 1,
            ];
        }
        $this->log('goods_detected', $goods);
        return $goods;
    }
    private function goods_for_carrier($carrier_code, $goods_items, $package, $total_weight)
    {
        if (!in_array($carrier_code, ['fedex'], true)) {
            return $goods_items;
        }
        $value = 0;
        $descriptions = [];
        $currency = get_woocommerce_currency();
        foreach ($goods_items as $good) {
            if (!is_array($good)) {
                continue;
            }
            $value += isset($good['value']) ? (float)$good['value'] : 0;
            if (!empty($good['currency'])) {
                $currency = $good['currency'];
            }
            if (!empty($good['description'])) {
                $descriptions[] = $good['description'];
            }
        }
        if ($value <= 0 && isset($package['contents_cost'])) {
            $value = (float)$package['contents_cost'];
        }
        $aggregate = [
            'description' => !empty($descriptions) ? implode(', ', array_slice($descriptions, 0, 5)) : __('WooCommerce cart items', 'multiparcels-shipping-for-woocommerce'),
            'value' => max(0.01, round($value, 2)),
            'currency' => $currency ?: 'EUR',
            'weight' => max(0.001, round($total_weight, 3)),
            'quantity' => 1,
            'items' => $goods_items,
        ];
        $this->log('goods_aggregated_for_carrier', ['carrier_code' => $carrier_code, 'goods' => $aggregate]);
        return $aggregate;
    }
    private function package_size($package = [])
    {
        $mode = $this->setting('real_time_prices_package_size_mode', 'default');
        if ($mode === 'product_dimensions') {
            $size_from_products = $this->package_size_from_products($package);
            if ($size_from_products) {
                $this->log('package_size_detected_from_products', ['package_size' => $size_from_products]);
                return $size_from_products;
            }
            $this->log('package_size_products_missing_using_default', []);
        }
        $size = $this->setting('real_time_prices_package_size', '20x20x20');
        $size = strtolower(str_replace([' ', '×'], ['', 'x'], (string)$size));
        if (!preg_match('/^\d+(\.\d+)?x\d+(\.\d+)?x\d+(\.\d+)?$/', $size)) {
            $this->log('package_size_invalid_using_default', ['configured_size' => $size]);
            return '20x20x20';
        }
        $this->log('package_size_detected', ['package_size' => $size, 'mode' => $mode]);
        return $size;
    }
    private function package_size_from_products($package = [])
    {
        if (empty($package['contents']) || !is_array($package['contents'])) {
            return null;
        }
        $length = 0;
        $width = 0;
        $height = 0;
        foreach ($package['contents'] as $item) {
            if (empty($item['data']) || !is_object($item['data'])) {
                continue;
            }
            $product = $item['data'];
            if (!method_exists($product, 'get_length') || !method_exists($product, 'get_width') || !method_exists($product, 'get_height')) {
                continue;
            }
            $product_length = (float)$product->get_length();
            $product_width = (float)$product->get_width();
            $product_height = (float)$product->get_height();
            if ($product_length <= 0 || $product_width <= 0 || $product_height <= 0) {
                continue;
            }
            $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
            $length = max($length, $product_length * $quantity);
            $width = max($width, $product_width);
            $height += $product_height;
        }
        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return null;
        }
        if (function_exists('wc_get_dimension')) {
            $dimension_unit = get_option('woocommerce_dimension_unit', 'cm');
            $length = wc_get_dimension($length, 'cm', $dimension_unit);
            $width = wc_get_dimension($width, 'cm', $dimension_unit);
            $height = wc_get_dimension($height, 'cm', $dimension_unit);
        }
        return sprintf('%sx%sx%s', round(max(1, $length), 1), round(max(1, $width), 1), round(max(1, $height), 1));
    }
    private function setting($key, $default = null)
    {
        $options = MultiParcels()->options->all();
        if (array_key_exists($key, $options) && $options[$key] !== '') {
            return $options[$key];
        }
        return $default;
    }
    private function log($type, $data)
    {
        if ($this->setting('real_time_prices_debug', 'no') !== 'yes') {
            return;
        }
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->debug(wp_json_encode(['type' => $type, 'data' => $this->sanitize_log_data($data)]), ['source' => 'multiparcels-real-time-prices']);
        }
    }
    private function sanitize_log_data($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), ['password', 'siteid', 'api_key', 'token', 'authorization'], true)) {
                    $data[$key] = '***';
                } else {
                    $data[$key] = $this->sanitize_log_data($value);
                }
            }
        }
        return $data;
    }
}
return new MP_Real_Time_Prices_Api();