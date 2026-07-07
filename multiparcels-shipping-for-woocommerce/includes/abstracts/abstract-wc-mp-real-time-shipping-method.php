<?php



if (!defined('ABSPATH')) {

    die;

}



if (class_exists('WC_Shipping_Method')) {

    abstract class WC_MP_Real_Time_Shipping_Method extends WC_Shipping_Method

    {

        public $carrier_code = '';

        public $carrier_name = '';

        public $tax_status = 'none';



        public function __construct($instance_id = 0)

        {

            $this->id = 'multiparcels_' . $this->carrier_code . '_rt_courier';

            $this->instance_id = absint($instance_id);

            $this->method_title = sprintf('%s %s', $this->carrier_name, __('Real-time Courier', 'multiparcels-shipping-for-woocommerce'));

            $this->method_description = __('Live courier price calculated by Noparcels during checkout.', 'multiparcels-shipping-for-woocommerce');

            $this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];



            $this->init();



            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);

        }



        public function init()

        {

            $this->instance_form_fields = [

                'title' => [

                    'title' => __('Title', 'multiparcels-shipping-for-woocommerce'),

                    'type' => 'text',

                    'description' => __('This controls the title which the customer sees during checkout.', 'multiparcels-shipping-for-woocommerce'),

                    'default' => sprintf('%s %s', $this->carrier_name, __('Courier', 'multiparcels-shipping-for-woocommerce')),

                    'desc_tip' => true,

                ],

                'tax_status' => [

                    'title' => __('Tax status', 'multiparcels-shipping-for-woocommerce'),

                    'type' => 'select',

                    'class' => 'wc-enhanced-select',

                    'default' => 'none',

                    'options' => [

                        'none' => _x('None', 'Tax status', 'multiparcels-shipping-for-woocommerce'),

                        'taxable' => __('Taxable', 'multiparcels-shipping-for-woocommerce'),

                    ],

                ],

            ];



            $this->init_settings();

            $this->title = $this->get_option('title');

            $this->tax_status = $this->get_option('tax_status');

        }



        public function calculate_shipping($package = [])

        {

            $this->debug('calculate_shipping_start', [

                'method_id' => $this->id,

                'instance_id' => $this->instance_id,

                'carrier_code' => $this->carrier_code,

                'destination' => isset($package['destination']) ? $package['destination'] : null,

                'contents_cost' => isset($package['contents_cost']) ? $package['contents_cost'] : null,

            ]);



            if ($this->setting('real_time_prices_enabled', 'no') !== 'yes') {

                $this->debug('calculate_shipping_stopped_disabled');

                return false;

            }



            $enabled_carriers = $this->enabled_carriers();

            if (!in_array($this->carrier_code, $enabled_carriers, true)) {

                $this->debug('calculate_shipping_stopped_carrier_not_enabled', [

                    'enabled_carriers' => $enabled_carriers,

                ]);

                return false;

            }



            if (!isset(MultiParcels()->real_time_prices_api) || !MultiParcels()->real_time_prices_api) {

                $this->debug('calculate_shipping_stopped_api_client_missing');

                return false;

            }



            $rate = MultiParcels()->real_time_prices_api->first_rate($this->carrier_code, $package);



            if (is_wp_error($rate)) {

                $this->debug('calculate_shipping_api_error', [

                    'code' => $rate->get_error_code(),

                    'message' => $rate->get_error_message(),

                ]);



                $no_fallback_error_codes = [
					'noparcels_real_time_unsupported_destination',
					'noparcels_real_time_backend_error',
					'noparcels_real_time_no_rates',
					'noparcels_real_time_no_price',
					'noparcels_real_time_invalid_response',
					'noparcels_real_time_incomplete_address',
					'noparcels_real_time_missing_country',
				];



                if (in_array($rate->get_error_code(), $no_fallback_error_codes, true)) {

                    $this->debug('calculate_shipping_stopped_business_error_no_fallback', [

                        'code' => $rate->get_error_code(),

                    ]);

                    return false;

                }



                $this->debug('calculate_shipping_stopped_error_no_fallback', [

                    'code' => $rate->get_error_code(),

                ]);

                return false;

            } else {

                $price = isset($rate['total_price']) ? (float)$rate['total_price'] : 0;

                $price = $this->apply_margin($price);

                $label = $this->title;



                if (!empty($rate['estimated_delivery'])) {

                    $timestamp = strtotime($rate['estimated_delivery']);

                    if ($timestamp) {

                        $label .= ' — ' . sprintf(__('delivery by %s', 'multiparcels-shipping-for-woocommerce'), date_i18n(get_option('date_format'), $timestamp));

                    }

                }



                $this->debug('calculate_shipping_rate_found', [

                    'rate' => $rate,

                    'final_price' => $price,

                ]);

                if (!$this->should_display_rate($rate, $package)) {

                    $this->debug('calculate_shipping_stopped_not_cheapest', [

                        'carrier_code' => $this->carrier_code,

                        'rate' => $rate,

                    ]);

                    return false;

                }

            }



            if ($price < 0) {

                $this->debug('calculate_shipping_stopped_negative_price', [

                    'price' => $price,

                ]);

                return false;

            }



            $rate_payload = [

                'id' => $this->get_rate_id(),

                'label' => $label,

                'cost' => round($price, 2),

                'package' => $package,

            ];



            if ($this->tax_status) {

                $rate_payload['tax_status'] = $this->tax_status;

            }



            $this->debug('calculate_shipping_add_rate', [

                'id' => $rate_payload['id'],

                'label' => $rate_payload['label'],

                'cost' => $rate_payload['cost'],

            ]);



            $this->add_rate($rate_payload);

        }



        private function should_display_rate($current_rate, $package)

        {

            if ($this->setting('real_time_prices_display_mode', 'all') !== 'cheapest') {

                return true;

            }



            if (!is_array($current_rate) || !isset($current_rate['total_price'])) {

                return true;

            }



            $best_carrier = $this->carrier_code;

            $best_price = (float)$current_rate['total_price'];



            $carriers = $this->enabled_carriers_for_package($package);

            if (count($carriers) <= 1) {

                return true;

            }



            foreach ($carriers as $carrier_code) {

                if ($carrier_code === $this->carrier_code) {

                    continue;

                }



                if (!isset(MultiParcels()->real_time_prices_api) || !MultiParcels()->real_time_prices_api) {

                    continue;

                }



                $rate = MultiParcels()->real_time_prices_api->first_rate($carrier_code, $package);

                if (is_wp_error($rate) || !is_array($rate) || !isset($rate['total_price'])) {

                    continue;

                }



                $price = (float)$rate['total_price'];

                if ($price < $best_price) {

                    $best_price = $price;

                    $best_carrier = $carrier_code;

                }

            }



            $this->debug('cheapest_rate_check', [

                'current_carrier' => $this->carrier_code,

                'best_carrier' => $best_carrier,

                'best_price' => $best_price,

            ]);



            return $best_carrier === $this->carrier_code;

        }



        private function enabled_carriers_for_package($package)

        {

            $zone_carriers = [];



            if (class_exists('WC_Shipping_Zones')) {

                $zone = WC_Shipping_Zones::get_zone_matching_package($package);

                if ($zone && method_exists($zone, 'get_shipping_methods')) {

                    foreach ($zone->get_shipping_methods(true) as $method) {

                        if (!is_object($method) || empty($method->id)) {

                            continue;

                        }



                        if (preg_match('/^multiparcels_(lp_express|post_lt|fedex)_rt_courier$/', $method->id, $matches)) {

                            $zone_carriers[] = $matches[1];

                        }

                    }

                }

            }



            $zone_carriers = array_values(array_unique($zone_carriers));

            $carriers = array_values(array_intersect($this->enabled_carriers(), $zone_carriers));



            if (empty($carriers)) {

                return [$this->carrier_code];

            }



            return $carriers;

        }



        private function apply_margin($price)

        {

            $type = $this->setting('real_time_prices_margin_type', 'percentage');

            $value = (float)$this->setting('real_time_prices_margin_value', 0);



            if ($value <= 0) {

                return $price;

            }



            if ($type === 'fixed') {

                return $price + $value;

            }



            return $price * (1 + ($value / 100));

        }



        private function enabled_carriers()

        {

            $options = MultiParcels()->options->all();

            $carriers = isset($options['real_time_prices_carriers']) ? $options['real_time_prices_carriers'] : [];



            if (is_string($carriers)) {

                $decoded_carriers = json_decode($carriers, true);

                if (is_array($decoded_carriers)) {

                    $carriers = $decoded_carriers;

                } else {

                    $carriers = array_filter(array_map('trim', explode(',', $carriers)));

                }

            }



            if (!is_array($carriers)) {

                return [];

            }



            return array_values(array_intersect(['lp_express', 'post_lt', 'fedex'], $carriers));

        }



        private function setting($key, $default = null)

        {

            $options = MultiParcels()->options->all();



            if (array_key_exists($key, $options) && $options[$key] !== '') {

                return $options[$key];

            }



            return $default;

        }



        private function debug($type, $data = [])

        {

            if ($this->setting('real_time_prices_debug', 'no') !== 'yes') {

                return;

            }



            if (function_exists('wc_get_logger')) {

                wc_get_logger()->debug(wp_json_encode([

                    'type' => $type,

                    'data' => $data,

                ]), ['source' => 'multiparcels-real-time-prices']);

            }

        }

    }

}

