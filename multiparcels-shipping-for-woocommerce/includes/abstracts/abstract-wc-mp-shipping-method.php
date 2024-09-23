<?php

// If this file is called directly, abort.
if ( ! defined('ABSPATH')) {
    die;
}

if (class_exists('WC_Shipping_Method')) {

    /**
     * Class WC_MP_Shipping_Method
     */
    abstract class WC_MP_Shipping_Method extends WC_Shipping_Method implements Wc_Mp_Shipping_Method_Interface
    {
        const TYPE_TERMINALS_ONLY = 'terminals_only';
        const TYPE_TERMINALS_AND_PICKUP_POINTS = 'terminals_and_pickup_points';
        const TYPE_PICKUP_POINTS_ONLY = 'pickup_points';
        const TYPE_COURIER = 'courier';

        const OPTION_DISPLAY_FROM_TIME = 'display_from_time';
        const OPTION_DISPLAY_UNTIL_TIME = 'display_until_time';
	    const OPTION_DISPLAY_ON_WEEKDAY = 'display_on_weekday';
	    const OPTION_IGNORE_DISCOUNTS = 'ignore_discounts';
        const OPTION_DISABLE_FREE_SHIPPING_COUPON = 'disable_free_shipping_coupon';
        const OPTION_PICKUP_TYPE = 'pickup_type';
	    const OPTION_SHIPPING_METHOD = 'shipping_method';
	    const OPTION_ZITICITY_ONLY_CITY = 'ziticity_only_city';
	    const OPTION_SIUNTOS_AUTOBUSAIS_ONLY_CITY = 'siuntos_autobusais_only_city';
	    const OPTION_VENIPAK_DOOR_CODE = 'venipak_door_code';
	    const OPTION_VENIPAK_DISPLAYED_PICKUP_POINTS = 'venipak_displayed_pickup_points';

	    const SUFFIX_COURIER = 'courier';
        const SUFFIX_PICKUP_POINT = 'pickup_point';
        const SUFFIX_TERMINAL = 'terminal';
        const SUFFIX_POST = 'post';
        const SUFFIX_BUS_STATION = 'bus_station';

        const DELIVERY_METHOD_ECONOMY = 'economy';
        const DELIVERY_METHOD_ECONOMY_12H = 'economy_12h';
        const DELIVERY_METHOD_EXPRESS = 'express';
        const DELIVERY_METHOD_EXPRESS_09H = 'express_09h';
        const DELIVERY_METHOD_EXPRESS_10H = 'express_10h';
        const DELIVERY_METHOD_EXPRESS_12H = 'express_12h';
        const DELIVERY_METHOD_EXPRESS_PLUS = 'express_plus';
        const DELIVERY_METHOD_EXPRESS_SAVER = 'express_saver';
        const DELIVERY_METHOD_EXPEDITED = 'expedited';
        const DELIVERY_METHOD_POST_DE_PACKET_PRIORITY = 'post_de_packet_priority';
        const DELIVERY_METHOD_POST_DE_PACKET_PLUS = 'post_de_packet_plus';
        const DELIVERY_METHOD_POST_DE_PACKET_TRACKED = 'post_de_packet_tracked';
        const DELIVERY_METHOD_UPS_SUREPOST_LESS_THAN_1LB = 'ups_surepost_less_than_1lb';
        const DELIVERY_METHOD_UPS_SUREPOST_1LB_OR_GREATER = 'ups_surepost_1lb_or_greater';
        const DELIVERY_METHOD_UPS_SUREPOST_BOUND_PRINTED_MATTER = 'ups_surepost_bound_printed_matter';
        const DELIVERY_METHOD_UPS_SUREPOST_MEDIA_MAIL = 'ups_surepost_media_mail';
        const DELIVERY_METHOD_SAME_DAY = 'same_day';
        const DELIVERY_METHOD_SIUNTOS_AUTOBUSAIS_TO_TERMINAL = 'siuntos_autobusais_to_terminal';
        const DELIVERY_METHOD_VENIPAK_GLS_ECONOMY = 'venipak_gls_economy';
        const DELIVERY_METHOD_VENIPAK_TNT_EXPRESS = 'venipak_tnt_express';
        const DELIVERY_METHOD_VENIPAK_TNT_ECONOMY_EXPRESS = 'venipak_tnt_economy_express';
        const DELIVERY_METHOD_FEDEX_INTERNATIONAL_ECONOMY = 'fedex_international_economy';
        const DELIVERY_METHOD_FEDEX_INTERNATIONAL_PRIORITY = 'fedex_international_priority';
        const DELIVERY_METHOD_FEDEX_EUROPE_INTERNATIONAL_FIRST = 'fedex_europe_international_first';
        const DELIVERY_METHOD_FEDEX_EUROPE_FIRST_INTERNATIONAL_PRIORITY = 'fedex_europe_first_international_priority';
        const DELIVERY_METHOD_FEDEX_INTERNATIONAL_FREIGHT_ECONOMY = 'fedex_international_economy_freight';
        const DELIVERY_METHOD_FEDEX_INTERNATIONAL_FREIGHT_PRIORITY = 'fedex_international_priority_freight';

        const SUFFIXES = [
            self::SUFFIX_COURIER,
            self::SUFFIX_PICKUP_POINT,
            self::SUFFIX_TERMINAL,
            self::SUFFIX_POST,
            self::SUFFIX_BUS_STATION,
        ];

        const INPUT_DOOR_CODE = 'door_code';
        const INPUT_PREFERRED_DELIVERY_TIME = 'preferred_delivery_time';

	    public $carrier_code = '';
        public $courier_settings = [];
        public $default_title = '';

        public $delivery_type = self::SUFFIX_COURIER;

        /**
         * Cost passed to [fee] shortcode.
         *
         * @var string Cost.
         */
        protected $fee_cost = '';

        /**
         * Constructor for your shipping class
         *
         * @access public
         *
         * @param int $instance_id
         * @param string $id_suffix
         */
        public function __construct($instance_id = 0, $id_suffix = '')
        {
            $this->id               = 'multiparcels' . '_' . $this->carrier_code . '_' . $id_suffix;
            $this->instance_id      = absint($instance_id);
            $this->courier_settings = MultiParcels()->carriers->get($this->carrier_code);

            $this->default_title = $this->default_title();
            $this->title         = $this->default_title;
            $this->method_title  = $this->default_title();

            $this->supports = [
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            ];

            $this->init();

            $this->method_description = $this->build_method_description();

            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        }

        /**
         * init user set variables.
         */
        public function init()
        {
            $maximum_weight = 0;

            if (substr($this->id, strlen(self::SUFFIX_COURIER) * -1) == self::SUFFIX_COURIER) {
                $maximum_weight = $this->courier_settings['courier_service_maximum_weight'];
            }

            if (substr($this->id, strlen(self::SUFFIX_PICKUP_POINT) * -1) == self::SUFFIX_PICKUP_POINT) {
                $maximum_weight = $this->courier_settings['pickup_points_maximum_weight'];
            }

            if (substr($this->id, strlen(self::SUFFIX_POST) * -1) == self::SUFFIX_POST) {
                $maximum_weight = $this->courier_settings['postal_office_maximum_weight'];
            }

	        $displayTimes = [
		        0 => __( 'Disabled', 'multiparcels-shipping-for-woocommerce' ),
	        ];

	        for ( $i = 1; $i <= 23; $i ++ ) {
		        $displayTimes[ $i * 60 ]          = $i . ':00';
		        $displayTimes[ ( $i * 60 ) + 30 ] = $i . ':30';
	        }

            $this->instance_form_fields = [
                'title' => [
                    'title' => __('Title', 'multiparcels-shipping-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.',
                        'multiparcels-shipping-for-woocommerce'),
                    'default' => $this->default_title,
                    'desc_tip' => true,
                ]
            ];

            if (is_array($this->courier_settings['delivery_methods']) && count($this->courier_settings['delivery_methods'])) {
                $shipping_method_options = [];

                foreach ($this->courier_settings['delivery_methods'] as $method) {
                    $shipping_method_options[$method] = MultiParcels()->carriers->delivery_method_name($method);
                }

                $this->instance_form_fields = array_merge($this->instance_form_fields, [
                    self::OPTION_SHIPPING_METHOD => [
                        'title' => __('Shipping method', 'multiparcels-shipping-for-woocommerce'),
                        'type' => 'select',
                        'default' => 0,
                        'desc_tip' => true,
                        'options' => $shipping_method_options,
                    ],
                ]);
            }

            $pickupTypes = [
                '0'    => __('Default', 'multiparcels-shipping-for-woocommerce'),
                'hands' => _x('From hands', 'Pickup type', 'multiparcels-shipping-for-woocommerce'),
                'terminal' => _x( 'From terminal', 'Pickup type', 'multiparcels-shipping-for-woocommerce' ),
            ];

            if ($this->courier_settings['carrier_code'] == WC_MP_Shipping_Helper::CARRIER_SIUNTOS_AUTOBUSAIS) {
                $pickupTypes = [
                    '0'    => __('Default', 'multiparcels-shipping-for-woocommerce'),
                    'hands' => _x('From hands', 'Pickup type', 'multiparcels-shipping-for-woocommerce'),
                    'bus_station' => _x( 'From bus station', 'Pickup type', 'multiparcels-shipping-for-woocommerce' ),
                ];
            }

            if ( $this->courier_settings['carrier_code'] == WC_MP_Shipping_Helper::CARRIER_VENIPAK ) {
                $this->instance_form_fields = array_merge( $this->instance_form_fields, [
                    self::OPTION_VENIPAK_DISPLAYED_PICKUP_POINTS => [
                        'title'       => __( 'Venipak displayed pickup points', 'multiparcels-shipping-for-woocommerce' ),
                        'type'        => 'select',
                        'default'     => 0,
                        'options' => [
                            '0'    => __('All', 'multiparcels-shipping-for-woocommerce'),
                            'terminals' => __( 'Terminals', 'multiparcels-shipping-for-woocommerce' ),
                            'pickup_points' => __('Pickup points', 'multiparcels-shipping-for-woocommerce'),
                        ],
                    ],
                ] );
            }

            $this->instance_form_fields = array_merge( $this->instance_form_fields, [
                'fee'                          => [
                    'title'       => __('Delivery Fee', 'multiparcels-shipping-for-woocommerce'),
                    'type'        => 'text',
                    'default'     => '0',
                    'placeholder' => '0',
                    'description' => __( 'Enter a cost or sum, e.g. 10.00 * [qty].', 'multiparcels-shipping-for-woocommerce' ) . '<br/><br/>' . __( 'Use [qty] for the number of items, <br/>[cost] for the total cost of items, and [fee percent="10" min_fee="20" max_fee=""] for percentage based fees.', 'multiparcels-shipping-for-woocommerce' ),
                    'desc_tip'    => true,
                ],
                'minimum_weight'               => [
                    'title'       => __('Minimum weight', 'multiparcels-shipping-for-woocommerce'),
                    'type'        => 'price',
                    'default'     => 0,
                    'placeholder' => wc_format_localized_price(0),
                    'description' => __('Kilograms', 'multiparcels-shipping-for-woocommerce'),
                    'desc_tip'    => true,
                ],
                'maximum_weight'               => [
                    'title'       => __('Maximum weight', 'multiparcels-shipping-for-woocommerce'),
                    'type'        => 'price',
                    'default'     => $maximum_weight,
                    'placeholder' => wc_format_localized_price(0),
                    'description' => __('Kilograms', 'multiparcels-shipping-for-woocommerce'),
                    'desc_tip'    => true,
                ],
                'tax_status'                   => [
                    'title'   => __('Tax status', 'multiparcels-shipping-for-woocommerce'),
                    'type'    => 'select',
                    'class'   => 'wc-enhanced-select',
                    'default' => 'none',
                    'options' => [
                        'none'    => _x('None', 'Tax status', 'multiparcels-shipping-for-woocommerce'),
                        'taxable' => __('Taxable', 'multiparcels-shipping-for-woocommerce'),
                    ],
                ],
                'min_amount_for_free_shipping' => [
                    'title'       => __('Minimum Order Amount For Free Shipping',
                        'multiparcels-shipping-for-woocommerce'),
                    'type'        => 'price',
                    'placeholder' => wc_format_localized_price(0),
                    'description' => __('Users will need to spend this amount to get free shipping (if enabled above).',
                        'multiparcels-shipping-for-woocommerce'),
                    'default'     => '0',
                    'desc_tip'    => true,
                ],
                self::OPTION_PICKUP_TYPE                   => [
                    'title'   => __('Preferred pickup type', 'multiparcels-shipping-for-woocommerce'),
                    'type'    => 'select',
                    'class'   => 'wc-enhanced-select',
                    'default' => 'none',
                    'options' => $pickupTypes,
                ],
                self::OPTION_IGNORE_DISCOUNTS => array(
                    'title'       => __( 'Coupons discounts', 'multiparcels-shipping-for-woocommerce' ),
                    'label'       => __( 'Apply minimum order amount rule before coupon discount', 'multiparcels-shipping-for-woocommerce' ),
                    'type'        => 'checkbox',
                    'description' => __( 'If checked, free shipping would be available based on pre-discount order amount.', 'multiparcels-shipping-for-woocommerce' ),
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
                self::OPTION_DISABLE_FREE_SHIPPING_COUPON => array(
                    'title'       => __( 'Disable free shipping coupon', 'multiparcels-shipping-for-woocommerce' ),
                    'label'       => __( 'Do not allow free shipping shipping with free shipping coupon', 'multiparcels-shipping-for-woocommerce' ),
                    'type'        => 'checkbox',
                    'description' => __( 'No checked - with free shipping coupon the shipping method will be free. Checked - free shipping coupon will not make this shipping method free. Example: check this checkbox for shipping methods that cost more and you do not want to give for free.', 'multiparcels-shipping-for-woocommerce' ),
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
                self::OPTION_DISPLAY_ON_WEEKDAY => [
                    'title'       => __('Display only on selected day of the week',
                        'multiparcels-shipping-for-woocommerce'),
                    'type'        => 'select',
                    'placeholder' => 0,
                    'description' => __('Customers will only see this shipping method on the selected day', 'multiparcels-shipping-for-woocommerce'),
                    'default'     => '0',
                    'desc_tip'    => true,
                    'options' => [
	                    0 => __( 'Disabled', 'multiparcels-shipping-for-woocommerce' ),
	                    1 => __( 'Monday', 'multiparcels-shipping-for-woocommerce' ),
	                    2 => __( 'Tuesday', 'multiparcels-shipping-for-woocommerce' ),
	                    3 => __( 'Wednesday', 'multiparcels-shipping-for-woocommerce' ),
	                    4 => __( 'Thursday', 'multiparcels-shipping-for-woocommerce' ),
	                    5 => __( 'Friday', 'multiparcels-shipping-for-woocommerce' ),
	                    6 => __( 'Saturday', 'multiparcels-shipping-for-woocommerce' ),
	                    7 => __( 'Sunday', 'multiparcels-shipping-for-woocommerce' ),
                    ],
                ],
                self::OPTION_DISPLAY_FROM_TIME => [
	                'title'       => __( 'Display only from the selected time',
		                'multiparcels-shipping-for-woocommerce' ),
	                'type'        => 'select',
	                'placeholder' => 0,
	                'description' => __( 'Customers will only see this shipping method from the selected time',
		                'multiparcels-shipping-for-woocommerce' ),
	                'default'     => '0',
	                'desc_tip'    => true,
	                'options'     => $displayTimes,
                ],
                self::OPTION_DISPLAY_UNTIL_TIME => [
	                'title'       => __( 'Display only until the selected time',
		                'multiparcels-shipping-for-woocommerce' ),
	                'type'        => 'select',
	                'placeholder' => 0,
	                'description' => __( 'Customers will only see this shipping method until the selected time',
		                'multiparcels-shipping-for-woocommerce' ),
	                'default'     => '0',
	                'desc_tip'    => true,
	                'options'     => $displayTimes,
                ],
            ]);

            $taxes_enabled = apply_filters( 'wc_tax_enabled', get_option( 'woocommerce_calc_taxes' ) === 'yes' );

            if (!$taxes_enabled) {
                unset($this->instance_form_fields['tax_status']);
            }

            if ( $this->courier_settings['carrier_code'] == 'ziticity' ) {
                $this->instance_form_fields = array_merge( $this->instance_form_fields, [
                    self::OPTION_ZITICITY_ONLY_CITY => [
                        'title'       => __( 'ZITICITY delivery only to this city', 'multiparcels-shipping-for-woocommerce' ),
                        'type'        => 'select',
                        'default'     => 0,
                        'desc_tip'    => true,
                        'options' => [
                            'all' => __('All cities', 'multiparcels-shipping-for-woocommerce'),
                            'Vilnius' => 'Vilnius',
                            'Kaunas' => 'Kaunas',
                            'Klaipeda' => 'Klaipėda',
                            'Riga' => 'Riga',
                            'Tallinn' => 'Tallinn',
                            'Paris' => 'Paris',
                            'Lion' => 'Lion',
                        ],
                    ],
                ] );
            }

            if ( $this->courier_settings['carrier_code'] == WC_MP_Shipping_Helper::CARRIER_SIUNTOS_AUTOBUSAIS && $this->delivery_type == self::SUFFIX_COURIER) {
                $this->instance_form_fields = array_merge( $this->instance_form_fields, [
                    self::OPTION_SIUNTOS_AUTOBUSAIS_ONLY_CITY => [
                        'title'       => __( 'Delivery only to this city', 'multiparcels-shipping-for-woocommerce' ),
                        'type'        => 'select',
                        'default'     => 0,
                        'desc_tip'    => true,
                        'options' => [
                            'all' => __('All cities and only Vilnius, Kaunas, Klaipėda, Alytus in Lithuanian', 'multiparcels-shipping-for-woocommerce'),
                            'Vilnius' => 'Vilnius',
                            'Kaunas' => 'Kaunas',
                            'Klaipeda' => 'Klaipėda',
                            'Alytus' => 'Alytus',
                        ],
                    ],
                ] );
            }

            if (MultiParcels()->permissions->isFull()) {
                $this->instance_form_fields = array_merge($this->instance_form_fields, [
                    'maximum_packages'          => [
                        'title'       => __('Maximum packages', 'multiparcels-shipping-for-woocommerce'),
                        'type'        => 'number',
                        'default'     => 1,
                        'placeholder' => 1,
                        'description' => __('Used to count how many packages this shipment will use when dispatching.',
                            'multiparcels-shipping-for-woocommerce'),
                        'desc_tip'    => true,
                    ],
                    'maximum_items_per_package' => [
                        'title'       => __('Maximum items per package', 'multiparcels-shipping-for-woocommerce'),
                        'type'        => 'number',
                        'default'     => 0,
                        'placeholder' => 0,
                        'description' => __('How to count how many items in a single package this shipping method allows to use. For example if you allow 6 items per box - a cart of 8 products would be 2 packages. 0 - unlimited.',
                            'multiparcels-shipping-for-woocommerce'),
                        'desc_tip'    => true,
                    ],
                ]);

                $available_services = $this->courier_settings['services'];

	            if ( ! is_array( $available_services ) ) {
		            $available_services = [];
	            }

                if (count($available_services)) {
                    $available_services_options = [];

                    foreach ($available_services as $code) {
                        if ($code != 'b2c' && $code != 'cod') {
                            $available_services_options[$code] = $this->service_title($code);
                        }
                    }

                    if (count($available_services_options)) {
                        $this->instance_form_fields = array_merge($this->instance_form_fields, [
                                'default_services' => [
                                    'title'       => __('Services', 'multiparcels-shipping-for-woocommerce'),
                                    'type'        => 'multiselect',
                                    'default'     => null,
                                    'description' => __('Services that will be used when creating shipments with this shipping method',
                                        'multiparcels-shipping-for-woocommerce'),
                                    'desc_tip'    => true,
                                    'options'     => $available_services_options,
                                ],
                            ]
                        );
                    }
                }
            }

	        if ( substr( $this->id,
			        strlen( self::SUFFIX_COURIER ) * - 1 ) == self::SUFFIX_COURIER && $this->courier_settings['has_preferred_delivery_time'] ) {
		        $this->instance_form_fields = array_merge( $this->instance_form_fields, [
			        'allow_preferred_delivery_time' => [
				        'title'       => __( 'Allow preferred delivery time', 'multiparcels-shipping-for-woocommerce' ),
				        'type'        => 'select',
				        'default'     => 0,
				        'description' => __( 'Allow customers to select their preferred delivery time. The carrier will probably apply extra charges for this preference.',
					        'multiparcels-shipping-for-woocommerce' ),
				        'desc_tip'    => true,
				        'options'     => [
					        0 => __( 'No', 'multiparcels-shipping-for-woocommerce' ),
					        1 => __( 'Yes', 'multiparcels-shipping-for-woocommerce' ),
				        ],
			        ],
		        ] );
	        }

            $shipping_classes = $this->get_shipping_classes();

            if ( ! empty($shipping_classes)) {
                $prepared_shipping_classes = [];

                foreach ($shipping_classes as $shipping_class) {
                    if (isset($shipping_class->term_id)) {
                        $prepared_shipping_classes[$shipping_class->term_id] = $shipping_class->name;
                    }
                }

                $this->instance_form_fields = array_merge($this->instance_form_fields, [
                        'ignore_shipping_classes' => [
                            'title'       => __('Disable this method for these shipping classes', 'multiparcels-shipping-for-woocommerce'),
                            'type'        => 'multiselect',
                            'default'     => null,
                            'description' => __('If at least one has product has selected shipping classes it will disable this shipping method',
                                'multiparcels-shipping-for-woocommerce'),
                            'desc_tip'    => true,
                            'options'     => $prepared_shipping_classes,
                        ],
                    ]
                );
            }



            if ( $this->courier_settings['carrier_code'] == WC_MP_Shipping_Helper::CARRIER_VENIPAK && $this->delivery_type == self::SUFFIX_COURIER) {
                $this->instance_form_fields = array_merge( $this->instance_form_fields, [
                    self::OPTION_VENIPAK_DOOR_CODE => [
                        'title'       => __( 'Add door code field', 'multiparcels-shipping-for-woocommerce' ),
                        'type'        => 'select',
                        'default'     => 'yes',
                        'options' => [
                            'yes' => __('Yes', 'multiparcels-shipping-for-woocommerce'),
                            'no' => __('No', 'multiparcels-shipping-for-woocommerce'),
                        ],
                    ],
                ] );
            }

            $this->title = $this->get_option('title');
        }

        /**
         * @param array $package
         * @param array $rate
         */
        public function free_shipping_check($package, &$rate)
        {
            $order_cost                   = WC()->cart->get_displayed_subtotal();

            if ($this->get_option(self::OPTION_IGNORE_DISCOUNTS) == 'no') {
                $order_cost = $order_cost - WC()->cart->get_discount_total();
            }

            $min_amount_for_free_shipping = $this->get_option('min_amount_for_free_shipping');

            if ($min_amount_for_free_shipping > 0) {
                if ($order_cost >= $min_amount_for_free_shipping) {
                    $rate['cost'] = 0;
                }
            }

            if ($rate['cost'] != 0) {
                $applied_coupons = WC()->cart->get_applied_coupons();
                foreach ($applied_coupons as $coupon_code) {
                    $coupon = new WC_Coupon($coupon_code);

                    if ($coupon->get_free_shipping() && $this->get_option(self::OPTION_DISABLE_FREE_SHIPPING_COUPON) == 'no') {
                        $rate['cost'] = 0;
                    }
                }
            }
        }

        public function courier_name($courier_settings)
        {
            if ($courier_settings['carrier_code'] == 'omniva_lt') {
                return __('Omniva (Lithuania)', 'multiparcels-shipping-for-woocommerce');
            }

            if ($courier_settings['carrier_code'] == 'omniva_lv') {
                return __('Omniva (Latvia)', 'multiparcels-shipping-for-woocommerce');
            }

            if ($courier_settings['carrier_code'] == 'omniva_ee') {
                return __('Omniva (Estonia)', 'multiparcels-shipping-for-woocommerce');
            }

            if ($courier_settings['carrier_code'] == 'post_lt') {
                return __('Lithuanian POST', 'multiparcels-shipping-for-woocommerce');
            }

            return $courier_settings['name'];
        }

        public function service_title($code)
        {
            return MultiParcels()->services->title($code);
        }

        private function build_method_description()
        {
            $extraText = '';
            $services  = $this->get_option('default_services');

            if ($services != '' && count($services) > 0) {
                $extraText .= "";
                $extraText .= sprintf("<div style='margin-top: 10px'><strong>%s:</strong> ",
                    __('Services', 'multiparcels-shipping-for-woocommerce'));
                $lastKey   = count($services) - 1;

                foreach ($services as $key => $service) {
                    $extraText .= sprintf('%s', $this->service_title($service));
                    if ($key !== $lastKey) {
                        $extraText .= ', ';
                    }
                }
                $extraText .= "</div>";
            }

            $minimum_weight = (float)$this->get_option('minimum_weight');
            $maximum_weight = (float)$this->get_option('maximum_weight');

            if ($maximum_weight > 0) {
                $extraText .= sprintf("<div style='margin-top: 10px'><strong>%s</strong> %s-%skg </div>",
                    __('Allowed weight:', 'multiparcels-shipping-for-woocommerce'),
                    $minimum_weight,
                    $maximum_weight
                );
            }

            $free_shipping_amount = (float)$this->get_option('min_amount_for_free_shipping');

            if ($free_shipping_amount > 0) {
                $extraText .= sprintf("<div style='margin-top: 10px'><strong>%s:</strong> %s&euro; </div>",
                    __('Minimum Order Amount For Free Shipping', 'multiparcels-shipping-for-woocommerce'),
                    $free_shipping_amount);
            }

	        $from_time  = (int) $this->get_option( self::OPTION_DISPLAY_FROM_TIME );
	        $until_time = (int) $this->get_option( self::OPTION_DISPLAY_UNTIL_TIME );

	        if ( $from_time > 0 ) {
		        $extraText .= sprintf( "<div style='margin-top: 10px'><strong>%s:</strong> %s</div>",
			        __( 'Display only from the selected time', 'multiparcels-shipping-for-woocommerce' ),
			        $this->convert_minutes_to_time( $from_time ) );
	        }

	        if ( $until_time > 0 ) {
		        $extraText .= sprintf( "<div style='margin-top: 10px'><strong>%s:</strong> %s</div>",
			        __( 'Display only until the selected time', 'multiparcels-shipping-for-woocommerce' ),
			        $this->convert_minutes_to_time( $until_time ) );
	        }

            if ($method = $this->get_option(self::OPTION_SHIPPING_METHOD)) {
                if ($method != WC_MP_Shipping_Method::DELIVERY_METHOD_ECONOMY) {
                    $extraText .= sprintf("<div style='margin-top: 10px'><strong>%s:</strong> %s</div>",
                        __('Shipping method', 'multiparcels-shipping-for-woocommerce'),
                        MultiParcels()->carriers->delivery_method_name($method));
                }
            }

            if ($ziticity_only_city = $this->get_option(self::OPTION_ZITICITY_ONLY_CITY)) {
                if ($ziticity_only_city != 'all') {
                    $extraText .= sprintf("<div style='margin-top: 10px'><strong>%s:</strong> %s</div>",
                        __('ZITICITY delivery only to this city', 'multiparcels-shipping-for-woocommerce'),
                        $ziticity_only_city);
                }
            }

	        return $this->method_description() . $extraText;
        }

        public function fee( $atts ) {
            $atts = shortcode_atts(
                [
                    'percent' => '',
                    'min_fee' => '',
                    'max_fee' => '',
                ],
                $atts,
                'fee'
            );

            $calculated_fee = 0;

            if ( $atts['percent'] ) {
                $calculated_fee = $this->fee_cost * ( floatval( $atts['percent'] ) / 100 );
            }

            if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
                $calculated_fee = $atts['min_fee'];
            }

            if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
                $calculated_fee = $atts['max_fee'];
            }

            return $calculated_fee;
        }

        protected function evaluate_cost( $sum, $args = array() ) {
            include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

            $locale         = localeconv();
            $decimals       = [wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ','];
            $this->fee_cost = $args['cost'];

            // Expand shortcodes.
            add_shortcode( 'fee', [$this, 'fee']);

            $sum = do_shortcode(
                str_replace(
                    [
                        '[qty]',
                        '[cost]',
                    ],
                    [
                        $args['qty'],
                        $args['cost'],
                    ],
                    $sum
                )
            );

            remove_shortcode( 'fee');

            // Remove whitespace from string.
            $sum = preg_replace( '/\s+/', '', $sum );

            // Remove locale from string.
            $sum = str_replace( $decimals, '.', $sum );

            // Trim invalid start/end characters.
            $sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

            // Do the math.
            return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
        }

        public function get_package_item_qty( $package ) {
            $total_quantity = 0;
            foreach ( $package['contents'] as $item_id => $values ) {
                if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
                    $total_quantity += $values['quantity'];
                }
            }
            return $total_quantity;
        }

	    public function calculate_shipping($package = array())
	    {
		    $rate = [
			    'id'      => $this->get_rate_id(),
			    'label'   => $this->get_option('title'),
			    'cost'    => $this->get_option('fee'),
			    'package' => $package,
		    ];

            $rate['cost'] = $this->evaluate_cost(
                $this->get_option('fee'),
                array(
                    'qty'  => $this->get_package_item_qty( $package ),
                    'cost' => $package['contents_cost'],
                )
            );

            $this->tax_status = $this->get_option( 'tax_status' );

            // Add shipping class costs.
            $shipping_classes = $this->get_shipping_classes();

            if ( ! empty($shipping_classes)) {
                $ignored_classes = $this->get_option('ignore_shipping_classes',
                    []);

                if ( ! is_array($ignored_classes)) {
                    $ignored_classes = [];
                }

                $found_shipping_classes = $this->find_shipping_classes_for_package($package);

                foreach ($found_shipping_classes as $found_class) {
                    if (in_array($found_class, $ignored_classes)) {
                        return false;
                    }
                }
            }

            // Allow only specific cities for ZITICITY
            if ($this->carrier_code == WC_MP_Shipping_Helper::CARRIER_ZITICITY || $this->carrier_code == WC_MP_Shipping_Helper::CARRIER_SIUNTOS_AUTOBUSAIS) {
                $receiverCity         = MultiParcels()->helper->latin_characters(WC()->customer->get_billing_city());
                $receiverShippingCity = MultiParcels()->helper->latin_characters(WC()->customer->get_shipping_city());
                $receiverCountry      = strtoupper(WC()->customer->get_billing_country());

                if (WC()->customer->get_shipping_country()) {
                    $receiverCountry = strtoupper(WC()->customer->get_shipping_country());
                }

                if ($receiverShippingCity && $receiverCity != $receiverShippingCity) {
                    $receiverCity = $receiverShippingCity;
                }


                $option_siuntos_autobusais_only_city = MultiParcels()->helper->latin_characters($this->get_option(self::OPTION_SIUNTOS_AUTOBUSAIS_ONLY_CITY));
                if ($option_siuntos_autobusais_only_city && $receiverCity) {
                    if ($option_siuntos_autobusais_only_city == 'all') {
                        if ($receiverCountry == 'LT') {
                            $allowedCities = [
                                'Vilnius',
                                'Kaunas',
                                'Klaipėda',
                                'Alytus'
                            ];

                            foreach ($allowedCities as $key => $value) {
                                $allowedCities[$key] = MultiParcels()->helper->latin_characters($value);
                            }

                            if (!in_array($receiverCity, $allowedCities)) {
                                return false;
                            }
                        }
                    } elseif ($receiverCity != $option_siuntos_autobusais_only_city) {
                        return false;
                    }
                }
            }

            // forbidden products
            if ($this->check_if_has_forbidden_products($package)) {
                return false;
            }
            // end forbidden products

            // categories
            if ($this->check_if_has_forbidden_categories($package)) {
                return false;
            }
            // end categories

            // disable on specific time
		    $weekday = (int) $this->get_option( self::OPTION_DISPLAY_ON_WEEKDAY );

		    if ( $weekday > 0 ) {
			    if ( $weekday != current_time( 'N' ) ) {
			        return false;
			    }
		    }

		    $from_time = $this->get_option( self::OPTION_DISPLAY_FROM_TIME );

		    if ( $from_time > 0 ) {
			    $current_minutes = ( current_time( 'G' ) * 60 ) + current_time( 'i' );
			    $minimum_minutes = $from_time;

			    if ( $current_minutes < $minimum_minutes ) {
			        return false;
			    }

		    }

		    $until_time = $this->get_option( self::OPTION_DISPLAY_UNTIL_TIME );

		    if ( $until_time > 0 ) {
			    $current_minutes = ( current_time( 'G' ) * 60 ) + current_time( 'i' );
			    $maximum_minutes = $until_time;

			    if ( $current_minutes >= $maximum_minutes ) {
			        return false;
			    }
		    }
		    // end disable on specific time

		    $cart_total_weight = WC()->cart->cart_contents_weight;

		    // We always use KG
		    if (get_option('woocommerce_weight_unit') == 'g') {
			    $cart_total_weight /= 1000;
		    }

		    $minimum_weight    = $this->get_option('minimum_weight');
		    $maximum_weight    = $this->get_option('maximum_weight');

		    if ( $cart_total_weight <= $maximum_weight && $cart_total_weight >= $minimum_weight ) {
                $this->free_shipping_check( $package, $rate );

                $this->add_rate( $rate );
		    }
	    }

	    private function convert_minutes_to_time( $from_time ) {
        	$hours = floor( $from_time / 60 );
        	$minutes = $from_time % 60;

		    if ( $minutes < 10 ) {
			    $minutes = '0' . $minutes;
		    }

		    return sprintf('%s:%s', $hours,$minutes);
	    }

        /**
         * @return WP_Term[]
         */
        public function get_shipping_classes()
        {
            return get_terms(
                'product_shipping_class',
                [
                    'hide_empty' => '0',
                    'orderby'    => 'name',
                ]
            );
	    }

        public function find_shipping_classes_for_package( $package ) {
            $found_shipping_classes = array();

            foreach ( $package['contents'] as $item_id => $values ) {
                if ( $values['data']->needs_shipping() ) {
                    $found_class = $values['data']->get_shipping_class_id();

                    if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
                        $found_shipping_classes[ $found_class ] = array();
                    }

                    $found_shipping_classes[ $found_class ] = $found_class;
                }
            }

            return $found_shipping_classes;
        }

        private function check_if_has_forbidden_products($package)
        {
            if ( ! in_array($this->delivery_type,
                [self::SUFFIX_TERMINAL, self::SUFFIX_PICKUP_POINT])) {
                return false;
            }

            foreach ($package['contents'] as $item_id => $values) {
                /** @var \WC_Product $product */
                $product = $values['data'];

                if ($product->needs_shipping()) {
                    if ($product->is_type('variation')) {
                        // get parent product
                        $product = wc_get_product($product->get_parent_id());
                    }

                    if ($product->get_meta('multiparcels_does_not_fit')) {
                        return true;
                    }
                }
            }

            return false;
        }

        private function check_if_has_forbidden_categories($package)
        {
            if ( ! in_array($this->delivery_type,
                [self::SUFFIX_TERMINAL, self::SUFFIX_PICKUP_POINT])) {
                return false;
            }

            $loadedCategories = [];

            foreach ($package['contents'] as $item_id => $values) {
                /** @var \WC_Product $product */
                $product = $values['data'];

                if ($product->needs_shipping()) {
                    foreach ($product->get_category_ids() as $category_id) {
                        if (array_key_exists($category_id, $loadedCategories)) {
                            $value = $loadedCategories[$category_id];
                        } else {
                            $value = ! ! get_term_meta($category_id,
                                'multiparcels_does_not_fit', true);

                            $loadedCategories[$category_id] = $value;
                        }

                        if ($value) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Override n-a value to nothing
         *
         * @param  string  $key
         * @param  null  $empty_value
         * @return mixed|null
         */
        public function get_option($key, $empty_value = null)
        {
            $value = parent::get_option($key, $empty_value);

            if ($value == 'n-a') {
                return null;
            }

            return $value;
        }
    }


}
