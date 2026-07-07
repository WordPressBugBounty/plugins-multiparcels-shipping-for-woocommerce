<?php



if (!defined('ABSPATH')) {

    exit;

}



if (class_exists('WC_MP_Real_Time_Shipping_Method')) {

    class WC_MP_Fedex_Real_Time_Courier_Shipping extends WC_MP_Real_Time_Shipping_Method

    {

        public $carrier_code = 'fedex';

        public $carrier_name = 'FedEx';

    }

}

