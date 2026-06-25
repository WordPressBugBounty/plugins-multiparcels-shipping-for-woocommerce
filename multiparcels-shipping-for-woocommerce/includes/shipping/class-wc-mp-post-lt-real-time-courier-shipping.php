<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_MP_Real_Time_Shipping_Method')) {
    class WC_MP_Post_Lt_Real_Time_Courier_Shipping extends WC_MP_Real_Time_Shipping_Method
    {
        public $carrier_code = 'post_lt';
        public $carrier_name = 'Lietuvos paštas';
    }
}
