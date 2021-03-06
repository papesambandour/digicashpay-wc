<?php

/*
   Plugin Name: API de paiement en ligne DIGICASH
   Description: DIGICASH vous offre un API de paiement en ligne hautement securisé facile d'utilisation.
   Version: 1.0
   Author: DIGICASH
   Author URI: https://digitcash.bgdigit-all.com
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}




add_action('plugins_loaded', 'woocommerce_digicashpay_init', 0);

function woocommerce_digicashpay_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Digicashpay extends WC_Payment_Gateway {

        public function __construct() {
            $this->digicashpay_errors = new WP_Error();
            /**
             * IPN
             */

            //id de la passerelle
            $this->id = 'digicashpay';
            $this->medthod_title = 'DIGICASHPAY';
            $this->icon = apply_filters('woocommerce_digicashpay_icon', plugins_url('assets/images/digicashpay.svg', __FILE__));
            $this->has_fields = false;
            //charger les champs pour paramètres de la passerelle.
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            //Mes parametres
            $this->app_key =$this->settings['app_key'];
            $this->secrete_key =$this->settings['secrete_key'];
            $this->public_key =$this->settings['public_key'];
            $this->env  = $this->settings['env'];
            //$this->fee  = $this->settings['fee'];
            //   $this->color = $this->settings['color'];
            //  $this->color2 = $this->settings['color2 '];
            //  $this->devise  = $this->settings['devise'];
            $this->digicashpay_host= 'https://digicashpay-proxy.herokuapp.com/';
            $this->posturl = $this->digicashpay_host.'api/payment/request';
            $this->submit_id = 0;

            //Transaction annulée
            $annulation =$success= $complet =-1;
            if(isset($_GET['cancel']) && $_GET['cancel']==1 )
                $annulation=1;
            if(isset($_GET['success']) && $_GET['success']==1 )
                $success = 1;


            if($annulation===1)
            {
                $message_type='error';
                $message='La transaction a été annulée';
                $wc_order_id = WC()->session->get('digicashpay_wc_oder_id');
                $order = new WC_Order($wc_order_id);
                $order->add_order_note($message);
                $rdi_url =$order->get_cancel_order_url();
            }
            if($success==1){
                $message="Paiement effectué avec succès.Merci d'avoir choisi Digicashpay";
                $message_type='success';
                $wc_order_id = WC()->session->get('digicashpay_wc_oder_id');
                $order = new WC_Order($wc_order_id);
                $order->add_order_note($message);
                $order->payment_complete();
                $rdi_url=$this->get_return_url($order);
            }

            if($success == 1|| $annulation == 1){
                $notification_message = array(
                    'message' => $message,
                    'message_type' => $message_type
                );
                if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                    $hash = WC()->session->get('digicashpay_wc_hash_key');
                    add_post_meta($wc_order_id, '_digicashpay_hash', $hash, true);
                }
                update_post_meta($wc_order_id, '_digicashpay_wc_message', $notification_message);

                WC()->session->__unset('digicashpay_wc_hash_key');
                WC()->session->__unset('digicashpay_wc_order_id');

                wp_redirect($rdi_url);
                exit;
            }


            //fi dame

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }





        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activer/Désactiver', 'digicashpay'),
                    'type' => 'checkbox',
                    'label' => __('Activation du module de paiement DIGICASHPAY.', 'digicashpay'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Titre:', 'digicashpay'),
                    'type' => 'text',
                    'description' => __('Texte que verra le client lors du paiement de sa commande.', 'digicashpay'),
                    'default' => __('Paiement avec Digicash', 'digicashpay')),
                'description' => array(
                    'title' => __('Description:', 'digicashpay'),
                    'type' => 'textarea',
                    'description' => __('Description que verra le client lors du paiement de sa commande.', 'digicashpay'),
                    'default' => __('Digicashpay est une passerelle de paiement en ligne developper par la societe B&G Digital .', 'digicashpay')),

                'app_key' => array(
                    'title' => __("Clé de l'api", 'digicashpay'),
                    'type' => 'text',
                    'description' => __("Identifiant de l'application lié au boutique en ligne")),
                'secrete_key' => array(
                    'title' => __("Clé secrete de l'api", 'digicashpay'),
                    'type' => 'text',
                    'description' => __('Clé secrete de securité de l\'application a voir dans l\'espace client partie application')),
                'public_key' => array(
                    'title' => __("Clé public de l'application", 'digicashpay'),
                    'type' => 'text',
                    'description' => __('Clé secrete de public de l\'application a voir dans l\'espace client partie application')),
                'env' => array(
                    'title' => __("Environnement", 'digicashpay'),
                    'description' => __('L\'environement de l\'application. mode teste ou production'),
                    'css'=>'padding:0%;',
                    'type' => 'select',
                    'options'=>array('test'=>'Test','prod' => 'Production'),
                ),
                'devise' => array(
                    'title' => __("Dévise", 'digicashpay'),
                    'description' => __('Choisir une dévise.'),
                    'css'=>'padding:0%;',
                    'type' => 'select',
                    'options'=>$this->getcurren(),
                ),
                /* 'fee' => array(
                     'title' => __("Commissions", 'digicashpay'),
                     'description' => __('Choisir qui paye les commissions.'),
                     'css'=>'padding:0%;',
                     'type' => 'select',
                     'options'=>array('1' => 'Commissions payées par Votre structure', '0'=>'Commissions payées par le client'),
                 )
                 ,*/
                /*  'color' => array(
                      'title' => 'Couleur 1',
                      'description' => __('Choississez la couleur de colonne à gauche.'),
                      'css'=>'padding:5px;',
                      'type' => 'color',
                  )
                  ,
                'color2' => array(
                    'title' => 'Couleur 2',
                    'description' => __('Choississez la couleur de colonne à droite.'),
                    'css'=>'padding:5px;',
                    'type' => 'color',
                )
    */


            );
        }
        public function getcurren(){

            $rest =  json_decode("[{\"XOF\":\"XOF\"},{\"EUR\":\"EUR\"},{\"USD\":\"USD\"},{\"XDR\":\"XDR\"},{\"AED\":\"AED\"},{\"AFN\":\"AFN\"},{\"ALL\":\"ALL\"},{\"AMD\":\"AMD\"},{\"ANG\":\"ANG\"},{\"AOA\":\"AOA\"},{\"ARS\":\"ARS\"},{\"AUD\":\"AUD\"},{\"AWG\":\"AWG\"},{\"AZN\":\"AZN\"},{\"BAM\":\"BAM\"},{\"BBD\":\"BBD\"},{\"BDT\":\"BDT\"},{\"BGN\":\"BGN\"},{\"BHD\":\"BHD\"},{\"BIF\":\"BIF\"},{\"BMD\":\"BMD\"},{\"BND\":\"BND\"},{\"BOB\":\"BOB\"},{\"BRL\":\"BRL\"},{\"BSD\":\"BSD\"},{\"BTC\":\"BTC\"},{\"BTN\":\"BTN\"},{\"BWP\":\"BWP\"},{\"BYN\":\"BYN\"},{\"BZD\":\"BZD\"},{\"CAD\":\"CAD\"},{\"CDF\":\"CDF\"},{\"CHF\":\"CHF\"},{\"CLF\":\"CLF\"},{\"CLP\":\"CLP\"},{\"CNH\":\"CNH\"},{\"CNY\":\"CNY\"},{\"COP\":\"COP\"},{\"CRC\":\"CRC\"},{\"CUC\":\"CUC\"},{\"CUP\":\"CUP\"},{\"CVE\":\"CVE\"},{\"CZK\":\"CZK\"},{\"DJF\":\"DJF\"},{\"DKK\":\"DKK\"},{\"DOP\":\"DOP\"},{\"DZD\":\"DZD\"},{\"EGP\":\"EGP\"},{\"ERN\":\"ERN\"},{\"ETB\":\"ETB\"},{\"FJD\":\"FJD\"},{\"FKP\":\"FKP\"},{\"GBP\":\"GBP\"},{\"GEL\":\"GEL\"},{\"GGP\":\"GGP\"},{\"GHS\":\"GHS\"},{\"GIP\":\"GIP\"},{\"GMD\":\"GMD\"},{\"GNF\":\"GNF\"},{\"GTQ\":\"GTQ\"},{\"GYD\":\"GYD\"},{\"HKD\":\"HKD\"},{\"HNL\":\"HNL\"},{\"HRK\":\"HRK\"},{\"HTG\":\"HTG\"},{\"HUF\":\"HUF\"},{\"IDR\":\"IDR\"},{\"ILS\":\"ILS\"},{\"IMP\":\"IMP\"},{\"INR\":\"INR\"},{\"IQD\":\"IQD\"},{\"IRR\":\"IRR\"},{\"ISK\":\"ISK\"},{\"JEP\":\"JEP\"},{\"JMD\":\"JMD\"},{\"JOD\":\"JOD\"},{\"JPY\":\"JPY\"},{\"KES\":\"KES\"},{\"KGS\":\"KGS\"},{\"KHR\":\"KHR\"},{\"KMF\":\"KMF\"},{\"KPW\":\"KPW\"},{\"KRW\":\"KRW\"},{\"KWD\":\"KWD\"},{\"KYD\":\"KYD\"},{\"KZT\":\"KZT\"},{\"LAK\":\"LAK\"},{\"LBP\":\"LBP\"},{\"LKR\":\"LKR\"},{\"LRD\":\"LRD\"},{\"LSL\":\"LSL\"},{\"LYD\":\"LYD\"},{\"MAD\":\"MAD\"},{\"MDL\":\"MDL\"},{\"MGA\":\"MGA\"},{\"MKD\":\"MKD\"},{\"MMK\":\"MMK\"},{\"MNT\":\"MNT\"},{\"MOP\":\"MOP\"},{\"MRO\":\"MRO\"},{\"MRU\":\"MRU\"},{\"MUR\":\"MUR\"},{\"MVR\":\"MVR\"},{\"MWK\":\"MWK\"},{\"MXN\":\"MXN\"},{\"MYR\":\"MYR\"},{\"MZN\":\"MZN\"},{\"NAD\":\"NAD\"},{\"NGN\":\"NGN\"},{\"NIO\":\"NIO\"},{\"NOK\":\"NOK\"},{\"NPR\":\"NPR\"},{\"NZD\":\"NZD\"},{\"OMR\":\"OMR\"},{\"PAB\":\"PAB\"},{\"PEN\":\"PEN\"},{\"PGK\":\"PGK\"},{\"PHP\":\"PHP\"},{\"PKR\":\"PKR\"},{\"PLN\":\"PLN\"},{\"PYG\":\"PYG\"},{\"QAR\":\"QAR\"},{\"RON\":\"RON\"},{\"RSD\":\"RSD\"},{\"RUB\":\"RUB\"},{\"RWF\":\"RWF\"},{\"SAR\":\"SAR\"},{\"SBD\":\"SBD\"},{\"SCR\":\"SCR\"},{\"SDG\":\"SDG\"},{\"SEK\":\"SEK\"},{\"SGD\":\"SGD\"},{\"SHP\":\"SHP\"},{\"SLL\":\"SLL\"},{\"SOS\":\"SOS\"},{\"SRD\":\"SRD\"},{\"SSP\":\"SSP\"},{\"STD\":\"STD\"},{\"STN\":\"STN\"},{\"SVC\":\"SVC\"},{\"SYP\":\"SYP\"},{\"SZL\":\"SZL\"},{\"THB\":\"THB\"},{\"TJS\":\"TJS\"},{\"TMT\":\"TMT\"},{\"TND\":\"TND\"},{\"TOP\":\"TOP\"},{\"TRY\":\"TRY\"},{\"TTD\":\"TTD\"},{\"TWD\":\"TWD\"},{\"TZS\":\"TZS\"},{\"UAH\":\"UAH\"},{\"UGX\":\"UGX\"},{\"UYU\":\"UYU\"},{\"UZS\":\"UZS\"},{\"VEF\":\"VEF\"},{\"VES\":\"VES\"},{\"VND\":\"VND\"},{\"VUV\":\"VUV\"},{\"WST\":\"WST\"},{\"XAF\":\"XAF\"},{\"XAG\":\"XAG\"},{\"XAU\":\"XAU\"},{\"XCD\":\"XCD\"},{\"XPD\":\"XPD\"},{\"XPF\":\"XPF\"},{\"XPT\":\"XPT\"},{\"YER\":\"YER\"},{\"ZAR\":\"ZAR\"},{\"ZMW\":\"ZMW\"},{\"ZWL\":\"ZWL\"}]",true);
            $return=[];
            foreach ($rest as $one){

                foreach($one as $key){
                    $return[$key] =   $key;break;
                }
            }
            return $return ;
        }

        public function admin_options() {
            echo '<h3>' . __('Passerelle de paiement DIGICASHPAY', 'digicashpay') . '</h3>';
            echo '<p>' . __('DIGICASHPAY est la meilleure plateforme de paiement en ligne.') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            //   wp_enqueue_script('digicashpay_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
        }

        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        protected function get_digicashpay_args($order, $order_id) {

            global $woocommerce;

            //$order = new WC_Order($order_id);
            $txnid = $order->id . '_' . date("ymds");

            $redirect_url = $woocommerce->cart->get_checkout_url();

            $productinfo = "Commande: " . $order->id;

            $str = "$this->merchant_id|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->salt";
            $hash = hash('sha512', $str);

            WC()->session->set('digicashpay_wc_hash_key', $hash);

            $items = $woocommerce->cart->get_cart();
            //  $digicashpay_items = array();
            $produit="";
            foreach ($items as $item) {
                $produit=$produit.$item["data"]->post->post_title." ";

            }


            $itemsId = [];
            foreach ($order->get_items() as $item_id => $item) {
                array_push($itemsId, $item_id);
            }

            $opt = get_settings('woocommerce_digicashpay_settings', array() );

            //dame arguments

            $cancel_url=  strpos($redirect_url,"?") === false ? $redirect_url.'?cancel=1' : $redirect_url.'&cancel=1' ;
            $success_url=  strpos($redirect_url,"?") === false ? $redirect_url.'?success=1' : $redirect_url.'&success=1' ;
            $postfields = array(
                "amount"   => $order->order_total,
                "currency"       => $opt['devise'],  //"xof",
                "app_key"       => $opt['app_key'],  //"xof",
                "secrete_key"       => $opt['secrete_key'],  //"xof",
                "public_key"       => $opt['public_key'],  //"xof",
                //  "no_calculate_fee" => $opt['fee'],
                "ref_commande"  =>$order->id.'_'.time(),
                "commande_name" =>"Achat " . $order->order_total . " ".$opt['devise']." pour article(s) achetés sur " . get_bloginfo("name"),
                "mode"          => $opt['env'],
                "success_url" => $success_url,
                "ipn_url"=>  get_site_url(null,'','https')  ."/digicashpay/ipn",
                "cancel_url"   => $cancel_url,
                "failed_url"   =>$cancel_url,
                "data_transactions"=> json_encode([
                    'order' => $order_id,
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number()
                ]));
            //fin dame arguments

            apply_filters('woocommerce_digicashpay_args', $postfields, $order);

            return $postfields;
        }


        function post($url, $data, $order_id,$header = [])
        {

            $strPostField = json_encode($data);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $strPostField);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($header, [
                'Content-Type: application/json'
            ]));

            $response = curl_exec($ch);


            $jsonResponse = json_decode($response, true);
//             echo "<pre>";
//             var_dump($data);die;

            WC()->session->set('digicashpay_wc_oder_id', $order_id);

            if(array_key_exists('error', $jsonResponse) && $jsonResponse['error']===false)
            {
                $url = $jsonResponse['data']['url_payment'] /*. $this->getTheme()*/;
                return $url ;


            }
            else {
                if(array_key_exists('error', $jsonResponse) && $jsonResponse['error']===true){
                    wc_add_notice($jsonResponse['msg'], "error");
                    foreach ($jsonResponse['data'] as $message){
                        wc_add_notice($message, "error");

                    }

                }


                return '';
            }


        }

        //fin mon post dame

        function process_payment($order_id) {
            $order = new WC_Order($order_id);


            return array(
                'result' => 'success',
                'redirect' => $this->post($this->posturl, $this->get_digicashpay_args($order, $order_id), $order_id,[

                ])
            );
        }



        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }


        static function add_digicashpay_fcfa_currency($currencies) {
            $currencies['FCFA'] = __('BCEAO XOF', 'woocommerce');
            return $currencies;
        }

        static function add_digicashpay_fcfa_currency_symbol($currency_symbol, $currency) {
            switch (
            $currency) {
                case 'FCFA': $currency_symbol = 'FCFA';
                    break;
            }
            return $currency_symbol;
        }

        static function woocommerce_add_digicashpay_gateway($methods) {
            $methods[] = 'WC_Digicashpay';
            return $methods;
        }

        // Add settings link on plugin page
        static function woocommerce_add_digicashpay_settings_link($links) {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_digicashpay">Paramètres</a>';
            array_unshift($links, $settings_link);
            return $links;
        }
        function getTheme(){
            $theme = json_decode('{"pageBackgroundRadianStart":"#0178bc","pageBackgroundRadianEnd":"#00bdda","pageTextPrimaryColor":"#333","paymentFormBackground":"#fff","navControlNextBackgroundRadianStart":"#608d93","navControlNextBackgroundRadianEnd":"#28314e","navControlCancelBackgroundRadianStar":"#28314e","navControlCancelBackgroundRadianEnd":"#608d93","navControlTextColor":"#fff","paymentListItemTextColor":"#555","paymentListItemSelectedBackground":"#eee","commingIconBackgroundRadianStart":"#0178bc","commingIconBackgroundRadianEnd":"#00bdda","commingIconTextColor":"#fff","formInputBackgroundColor":"#eff1f2","formInputBorderTopColor":"#e3e7eb","formInputBorderLeftColor":"#7c7c7c","totalIconBackgroundRadianStart":"#0178bc","totalIconBackgroundRadianEnd":"#00bdda","formLabelTextColor":"#292b2c","alertDialogTextColor":"#333","alertDialogConfirmButtonBackgroundColor":"#0178bc","alertDialogConfirmButtonTextColor":"#fff"}');
            // $theme->pageBackgroundRadianStart = $this->color  ;
            //  $theme->pageBackgroundRadianEnd = $this->color2 ;
            //  $theme64 = base64_encode(json_encode($theme));
            //  return $query = "?t=".$theme64 ;
        }

    }



    $plugin = plugin_basename(__FILE__);

    add_filter('woocommerce_currencies', array('WC_Digicashpay', 'add_digicashpay_fcfa_currency'));
    add_filter('woocommerce_currency_symbol', array('WC_Digicashpay', 'add_digicashpay_fcfa_currency_symbol'), 10, 2);

    add_filter("plugin_action_links_$plugin", array('WC_Digicashpay', 'woocommerce_add_digicashpay_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_Digicashpay', 'woocommerce_add_digicashpay_gateway'));


    $digipay_ipn = function (){


        if($_SERVER[ 'REQUEST_URI'] === "/digicashpay/ipn" ){

            $options = get_settings('woocommerce_digicashpay_settings', array() );
            if(isset($_POST['type_event']))
            {
                $res = $_POST['type_event'];
                if($res === 'sale_complete' && hash('sha256', $options['public_key']) === $_POST['public_key_sha256'] && hash('sha256', $options['secrete_key']) === $_POST['secrete_key_sha256'])
                {
                    global $woocommerce;

                    ini_set('display_errors', 1);
                    error_reporting(E_ALL);
                    $custom = json_decode($_POST['custom_field'], true);

                    $order_id = $custom['order_id'];
                    global $wpdb;

                    $prefix = $wpdb->base_prefix;
                    $query = "UPDATE ".$prefix."posts SET `post_status` = REPLACE(post_status, 'pending','completed') WHERE ID =".$order_id;

                    $wpdb->query($query);
                    die('OK');
                }
            }

            die('Echec validation');
        }

    };

    $digipay_ipn();


}
