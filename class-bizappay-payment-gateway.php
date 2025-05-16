<?php
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use SejoliSA\Admin\Product as AdminProduct;
use SejoliSA\JSON\Product;
use SejoliSA\Model\Affiliate;

final class SejoliBizappay extends \SejoliSA\Payment{

    /**
     * Prevent double method calling
     * @since   1.0.0
     * @access  protected
     * @var     boolean
     */
    protected $is_called = false;

    /**
     * Request urls
     * @since   1.0.0
     * @var     array
     */
    public $request_url = array(
        'sandbox' => 'https://bizappay.my/api/v3/bill/create',
        'live'    => 'https://bizappay.my/api/v3/bill/create'
    );

    /**
     * Order price
     * @since 1.0.0
     * @var float
     */
    protected $order_price = 0.0;

    /**
     * Method options
     * @since   1.0.0
     * @var     array
     */
    protected $method_options = array();

    /**
     * Table name
     * @since 1.0.0
     * @var string
     */
    protected $table = 'sejolisa_bizappay_transaction';

    /**
     * Construction
     */
    public function __construct() {
        
        global $wpdb;

        $this->id          = 'bizappay';
        $this->name        = __( 'Bizappay', 'sejoli-bizappay' );
        $this->title       = __( 'Bizappay', 'sejoli-bizappay' );
        $this->description = __( 'Transaksi via Bizappay Payment Gateway.', 'sejoli-bizappay' );
        $this->table       = $wpdb->prefix . $this->table;

        $this->method_options = array(
            'ABB0233'  => __('Affin Bank', 'sejoli-bizappay'),
            'AGRO01'   => __('AGRONet', 'sejoli-bizappay'),
            'ABMB0212' => __('Alliance Bank (Personal)', 'sejoli-bizappay'),
            'AMBB0209' => __('AmBank', 'sejoli-bizappay'),
            'BIMB0340' => __('Bank Islam', 'sejoli-bizappay'),
            'BMMB0341' => __('Bank Muamalat', 'sejoli-bizappay'),
            'BKRM0602' => __('Bank Rakyat', 'sejoli-bizappay'),
            'BSN0601'  => __('BSN', 'sejoli-bizappay'),
            'BCBB0235' => __('CIMB Clicks', 'sejoli-bizappay'),
            'HLB0224'  => __('Hong Leong Bank', 'sejoli-bizappay'),
            'HSBC0223' => __('HSBC Bank', 'sejoli-bizappay'),
            'KFH0346'  => __('KFH', 'sejoli-bizappay'),
            'MBB0228'  => __('Maybank2E', 'sejoli-bizappay'),
            'MB2U0227' => __('Maybank2U', 'sejoli-bizappay'),
            'OCBC0229' => __('OCBC Bank', 'sejoli-bizappay'),
            'PBB0233'  => __('Public Bank', 'sejoli-bizappay'),
            'RHB0218'  => __('RHB Bank', 'sejoli-bizappay'),
            'SCB0216'  => __('Standard Chartered', 'sejoli-bizappay'),
            'UOB0226'  => __('UOB Bank', 'sejoli-bizappay')
        );

        add_action('admin_init',                     [$this, 'register_trx_table'],  1);
        add_filter('sejoli/payment/payment-options', [$this, 'add_payment_options']);
        add_filter('query_vars',                     [$this, 'set_query_vars'],     999);
        add_action('sejoli/thank-you/render',        [$this, 'check_for_redirect'], 1);
        add_action('init',                           [$this, 'set_endpoint'],       1);
        add_action('parse_query',                    [$this, 'check_parse_query'],  100);

    }

    /**
     * Register transaction table
     * Hooked via action admin_init, priority 1
     * @since   1.0.0
     * @return  void
     */
    public function register_trx_table() {

        global $wpdb;

        $table_name = $this->table;

        if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {

            $query = "CREATE TABLE $table_name (
                ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at datetime NOT NULL,
                last_check datetime DEFAULT '0000-00-00 00:00:00',
                order_id bigint(20) NOT NULL,
                status varchar(255) NOT NULL,
                detail text DEFAULT NULL,
                PRIMARY KEY (ID)
            ) CHARACTER SET utf8 COLLATE utf8_general_ci;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $query );
            
        }

    }

    /**
     * Get duitku order data
     * @since   1.0.0
     * @param   int $order_id
     * @return  false|object
     */
    protected function check_data_table( int $order_id ) {
     
        global $wpdb;

        $table_name = $this->table;

        $query = $wpdb->prepare( 
            "SELECT * FROM $table_name WHERE order_id = %d LIMIT 1", 
            $order_id 
        );

        return $wpdb->get_row( $query );

    }

    /**
     * Add transaction data
     * @since   1.0.0
     * @param   integer $order_id Order ID
     * @return  void
     */
    protected function add_to_table( int $order_id ) {
     
        global $wpdb;

        $table_name = $this->table;

        $data = array(
            'created_at' => current_time('mysql'),
            'last_check' => '0000-00-00 00:00:00',
            'order_id'   => $order_id,
            'status'     => 'pending',
        );

        $wpdb->insert( $table_name, $data );

    }

    /**
     * Update data status
     * @since   1.0.0
     * @param   integer $order_id [description]
     * @param   string $status [description]
     * @return  void
     */
    protected function update_status( $order_id, $status ) {
     
        global $wpdb;

        $table_name = $this->table;

        $data = array(
            'status'     => $status,
            'last_check' => current_time('mysql'),
        );

        $where = array(
            'order_id' => $order_id,
        );

        $wpdb->update( $table_name, $data, $where );

    }

    /**
     * Update data detail payload
     * @since   1.0.0
     * @param   integer $order_id [description]
     * @param   array $detail [description]
     * @return  void
     */
    protected function update_detail( $order_id, $detail ) {
     
        global $wpdb;

        $table_name = $this->table;

        $data = array(
            'detail' => serialize($detail),
        );

        $where = array(
            'order_id' => $order_id,
        );

        $wpdb->update( $table_name, $data, $where );

    }

    /**
     *  Set end point custom menu
     *  Hooked via action init, priority 999
     *  @since   1.0.0
     *  @access  public
     *  @return  void
     */
    public function set_endpoint() {
        
        add_rewrite_rule( '^bizappay/([^/]*)/?', 'index.php?bizappay-method=1&action=$matches[1]', 'top' );

        flush_rewrite_rules();
    
    }

    /**
     * Set custom query vars
     * Hooked via filter query_vars, priority 100
     * @since   1.0.0
     * @access  public
     * @param   array $vars
     * @return  array
     */
    public function set_query_vars( $vars ) {

        $vars[] = 'bizappay-method';

        return $vars;
    
    }

    /**
     * Check parse query and if duitku-method exists and process
     * Hooked via action parse_query, priority 999
     * @since 1.0.0
     * @access public
     * @return void
     */
    public function check_parse_query() {

        global $wp_query;

        if( is_admin() || $this->is_called ) :

            return;

        endif;

        if(
            isset( $wp_query->query_vars['bizappay-method'] ) &&
            isset( $wp_query->query_vars['action'] ) && !empty( $wp_query->query_vars['action'] )
        ) :

            if( 'process' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->process_callback();

            elseif( 'return' === $wp_query->query_vars['action'] ) :

                $this->is_called = true;
                $this->receive_return();

            endif;

        endif;

    }

    /**
     * Get Payment Methods
     * @since   1.0.0
     * @return  array
     */
    public function get_payment_methods() {

        $mode = carbon_get_theme_option( 'bizappay_mode' );
            
        if ( $mode === 'live' ) {

            $apiUri       = 'https://bizappay.my/api/v3';
            $apikey       = carbon_get_theme_option( 'bizappay_api_key_live' );
        
            $token        = get_option( 'bizappay_live_token' );
            $tokenExpiry  = get_option( 'bizappay_live_token_expiration' );
            $baseAppUrl   = 'https://bizappay.my';
 
        } else {

            $apiUri       = 'https://bizappay.my/api/v3';
            $apikey       = carbon_get_theme_option( 'bizappay_api_key_sandbox' );
            $token_option = 'bizappay_sandbox_token';
            $token        = get_option( 'bizappay_sandbox_token' );
        
        }
    }

    /**
     * Set option in Sejoli payment options, we use CARBONFIELDS for plugin options
     * Called from parent method
     * @since   1.0.0
     * @return  array
     */
    public function get_setup_fields() {

        return array(

            Field::make('separator', 'sep_bizappay_tranaction_setting', __('Pengaturan Bizappay', 'sejoli-bizappay')),

            Field::make('checkbox', 'bizappay_active', __('Aktifkan pembayaran melalui Bizappay', 'sejoli-bizappay')),
            
            Field::make('select', 'bizappay_mode', __('Payment Mode', 'sejoli-bizappay'))
            ->set_options(array(
                'sandbox' => 'Sandbox',
                'live'    => 'live'
            )),

            Field::make('text', 'bizappay_api_key_sandbox', __('API Key Sandbox', 'sejoli-bizappay'))
            ->set_required(true)
            ->set_conditional_logic(array(
                array(
                    'field' => 'bizappay_active',
                    'value' => true
                ),array(
                    'field' => 'bizappay_mode',
                    'value' => 'sandbox'
                )
            )),

            Field::make('text', 'bizappay_api_key_live', __('API Key Live', 'sejoli-bizappay'))
            ->set_required(true)
            ->set_conditional_logic(array(
                array(
                    'field' => 'bizappay_active',
                    'value' => true
                ),array(
                    'field' => 'bizappay_mode',
                    'value' => 'live'
                )
            )),

            Field::make('text', 'bizappay_inv_prefix', __('Invoice Prefix', 'sejoli-bizappay'))
            ->set_required(true)
            ->set_default_value('sjl1')
            ->set_help_text(__('Maksimal 6 Karakter', 'sejoli-bizappay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'bizappay_active',
                    'value' => true
                )
            )),

            Field::make('separator', 'sep_bizappay_payment_method',   __('Pilih Metode Pembayaran', 'sejoli-bizappay'))
            ->set_conditional_logic(array(
                array(
                    'field' => 'bizappay_active',
                    'value' => true
                )
            )),

            Field::make('set', 'bizappay_payment_method', __('Metode Pembayaran', 'sejoli-bizappay'))
            ->set_required(true)
            ->set_options($this->method_options)
            ->set_help_text(
                __('Wajib memilih minimal satu metode pembayaran dan PASTIKAN metode tersebut sudah aktif di pengaturan project bizappay.com', 'sejoli-bizappay')
            )
            ->set_conditional_logic(array(
                array(
                    'field' => 'bizappay_active',
                    'value' => true
                )
            ))

        );

    }

    /**
     * Display bizappay payment options in checkout page
     * Hooked via filter sejoli/payment/payment-options, priority 100
     * @since   1.0.0
     * @param   array $options
     * @return  array
     */
    public function add_payment_options( array $options ) {
        
        $active = boolval( carbon_get_theme_option('bizappay_active') );

        if(true === $active) :

            $methods          = carbon_get_theme_option('bizappay_payment_method');
            $image_source_url = plugin_dir_url(__FILE__);

            foreach( (array) $methods as $_method ) :

                $key = 'bizappay:::'.$_method;

                switch($_method) :

                    case 'ABB0233' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/ABB0233.png'
                        ];
                        break;

                    case 'AGRO01' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/AGRO01.png'
                        ];
                        break;

                    case 'ABMB0212' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/ABMB0212.png'
                        ];
                        break;

                    case 'AMBB0209' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/AMBB0209.png'
                        ];
                        break;

                    case 'BIMB0340' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/BIMB0340.png'
                        ];
                        break;

                    case 'BMMB0341' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/BMMB0341.png'
                        ];
                        break;

                    case 'BKRM0602' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/BKRM0602.png'
                        ];
                        break;

                    case 'BSN0601' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/BSN0601.png'
                        ];
                        break;

                    case 'BCBB0235' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/BCBB0235.png'
                        ];
                        break;

                    case 'HLB0224' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/HLB0224.png'
                        ];
                        break;

                    case 'HSBC0223' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/HSBC0223.png'
                        ];
                        break;

                    case 'KFH0346' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/KFH0346.png'
                        ];
                        break;

                    case 'MBB0228' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/MBB0228.png'
                        ];
                        break;

                    case 'MB2U0227' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/MB2U0227.png'
                        ];
                        break;

                    case 'OCBC0229' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/OCBC0229.png'
                        ];
                        break;

                    case 'PBB0233' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/PBB0233.png'
                        ];
                        break;

                    case 'RHB0218' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/RHB0218.png'
                        ];
                        break;

                    case 'SCB0216' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/SCB0216.png'
                        ];
                        break;

                    case 'UOB0226' :
                        $options[$key] = [
                            'label' => $this->method_options[$_method],
                            'image' => $image_source_url.'img/UOB0226.png'
                        ];
                        break;

                endswitch;

            endforeach;

        endif;

        return $options;

    }

    /**
     * Set order price if there is any fee need to be added
     * @since   1.0.0
     * @param   float $price
     * @param   array $order_data
     * @return  float
     */
    public function set_price( float $price, array $order_data ) {

        if( 0.0 !== $price ) :

            $this->order_price = $price;

            return floatval( $this->order_price );

        endif;

        return $price;

    }

    /**
     * Get setup values
     * @return array
     */
    protected function get_setup_values() {

        $mode    = carbon_get_theme_option('bizappay_mode');
        $api_key = trim( carbon_get_theme_option('bizappay_api_key_'.$mode) );

        $request_url = $this->request_url[$mode];

        return array(
            'mode'        => $mode,
            'api_key'     => $api_key,
            'request_url' => $request_url
        );

    }

    /**
     * Set order meta data
     * @since   1.0.0
     * @param   array $meta_data
     * @param   array $order_data
     * @param   array $payment_subtype
     * @return  array
     */
    public function set_meta_data( array $meta_data, array $order_data, $payment_subtype ) {

        $meta_data['bizappay'] = [
            'trans_id'   => '',
            'unique_key' => substr( md5( rand( 0,1000 ) ), 0, 16 ),
            'method'     => $payment_subtype
        ];

        return $meta_data;

    }

    /**
     * Prepare Paypal Data
     * @since   1.0.0
     * @return  array
     */
    public function prepare_bizappay_data( array $order ) {

        extract( $this->get_setup_values() );

        $redirect_link       = '';
        $request_to_bizappay = false;
        $data_order          = $this->check_data_table( $order['ID'] );

        if(NULL === $data_order) :
            
            $request_to_bizappay = true;
        
        else :

            $detail = unserialize( $data_order->detail );

            if( !isset( $detail['url'] ) || empty( $detail['url'] ) ) :
                $request_to_bizappay = true;
            else :
                $redirect_link = 'https://www.bizappay.my/'.$detail['billCode'].'?redirect=true';
            endif;

        endif;

        if( true === $request_to_bizappay ) :

            if ( $mode === 'live' ) {

                $apiUri       = 'https://bizappay.my/api/v3';
                $apikey       = carbon_get_theme_option( 'bizappay_api_key_live' );
                $token_option = 'bizappay_live_token';
                $token        = get_option( 'bizappay_live_token' );
                $tokenExpiry  = get_option( 'bizappay_live_token_expiration' );
                $baseAppUrl   = 'https://bizappay.my';
     
            } else {

                $apiUri       = 'https://bizappay.my/api/v3';
                $apikey       = carbon_get_theme_option( 'bizappay_api_key_sandbox' );
                $token_option = 'bizappay_sandbox_token';
                $token        = get_option( 'bizappay_sandbox_token' );
                $tokenExpiry  = get_option( 'bizappay_sandbox_token_expiration' );
                $baseAppUrl   = 'https://bizappay.my';
            
            }

            if ( !empty( $token ) && !empty( $tokenExpiry ) && $tokenExpiry > time() ) {
                // use last token
            } else {
                $token = $this->bizappay_renew_token( $apiUri, $apikey );
            }

            $this->add_to_table( $order['ID'] );

            if ( !empty( $token ) ) {

                $payment_amount    = (int) $order['grand_total'];
                $merchant_order_ID = $order['ID'];
                $signature         = md5( $order['ID'] . $merchant_order_ID . $payment_amount . $api_key );

                $params = array(
                    'apiKey'        => $api_key,
                    'category'      => 'aqm2zR2I35', // Sejoli E-Commerce
                    'name'          => get_bloginfo( 'name' ),
                    'amount'        => number_format( ( $order['grand_total'] / 100 ), 2, '.', '' ),
                    'payer_name'    => $order['user']->display_name,
                    'payer_email'   => $order['user']->user_email,
                    'payer_phone'   => $order['user']->meta->phone,
                    'webreturn_url' => add_query_arg(array(
                                            'order_id'   => $order['ID'],
                                            'unique_key' => $order['meta_data']['bizappay']['unique_key']
                                        ), site_url('/bizappay/return')),
                    'callback_url'  => add_query_arg(array(
                                            'order_id'   => $order['ID'],
                                            'unique_key' => $order['meta_data']['bizappay']['unique_key']
                                        ), site_url('/bizappay/process')),
                    'ext_reference' => $signature,
                    'bank_code'     => $order['meta_data']['bizappay']['method']
        
                );

                $executeTransaction = $this->executeTransaction( $request_url, $token, $params );

                if ( $executeTransaction['status'] === "ok" ) {
                    $http_code = 200;
                } else {
                    $http_code = 400;
                }

                if( 200 === $http_code ) :

                    do_action( 'sejoli/log/write', 'success-bizappay', $executeTransaction );

                    $this->update_detail( $order['ID'], $executeTransaction );
                    $redirect_link = 'https://www.bizappay.my/'.$executeTransaction['billCode'].'?redirect=true';

                else :

                    do_action( 'sejoli/log/write', 'error-bizappay', array( $executeTransaction, $http_code, $params ) );

                    wp_die(
                        __('Terjadi kesalahan saat request ke bizappay.com. Silahkan kontak pemilik website ini.', 'sejoli-bizappay'),
                        __('Terjadi kesalahan', 'sejoli-bizappay')
                    );

                    exit;
            
                endif;

            }

        endif;

        wp_redirect( $redirect_link );

        exit;

    }

    /**
     * Receive return process
     * @since   1.0.0
     * @return  void
     */
    protected function receive_return() {

        $args = wp_parse_args($_GET, array(
            'order_id'    => NULL,
            'billcode'    => NULL,
            'billstatus'  => NULL,
            'billinvoice' => NULL,
            'billtrans'   => NULL
        ));

        if(
            !empty( $args['order_id'] ) &&
            !empty( $args['billcode'] ) &&
            !empty( $args['billstatus'] ) &&
            !empty( $args['billinvoice'] ) &&
            !empty( $args['billtrans'] )
        ) :

            $order_id = intval( $args['order_id'] );

            sejolisa_update_order_meta_data($order_id, array(
                'bizappay' => array(
                    'trans_id'  => $args['billinvoice']
                )
            ));

            wp_redirect(add_query_arg(array(
                'order_id' => $order_id
            ), site_url('checkout/thank-you')));

        endif;

        exit;

    }

    /**
     * Process callback from bizappay
     * @since   1.0.0
     * @return  void
     */
    protected function process_callback() {

        extract( $this->get_setup_values() );

        $setup = $this->get_setup_values();

        $args = wp_parse_args($_GET, array(
            'order_id'    => NULL,
            'billcode'    => NULL,
            'billamount'  => NULL,
            'billstatus'  => NULL,
            'billinvoice' => NULL,
            'billtrans'   => NULL
        ));
        
        if(
            !empty( $args['order_id'] ) &&
            !empty( $args['billcode'] ) &&
            !empty( $args['billamount'] ) &&
            !empty( $args['billstatus'] ) &&
            !empty( $args['billinvoice'] ) &&
            !empty( $args['billtrans'] )
        ) :

            if( "success" === $args['billstatus'] ) :

                $order_id = intval( $args['order_id'] );
                $response = sejolisa_get_order( array( 'ID' => $order_id ) );

                if( false !== $response['valid'] ) :

                    $order   = $response['orders'];
                    $product = $order['product'];

                    // if product is need of shipment
                    if( false !== $product->shipping['active'] ) :
                        $status = 'in-progress';
                    else :
                        $status = 'completed';
                    endif;

                    $this->update_order_status( $order['ID'] );

                    $args['status'] = $status;

                    do_action( 'sejoli/log/write', 'bizappay-update-order', $args );

                else :

                    do_action( 'sejoli/log/write', 'bizappay-wrong-order', $args );
                
                endif;

            endif;
            
        else :

            wp_die(
                __('You don\'t have permission to access this page', 'sejoli-bizappay'),
                __('Forbidden access by SEJOLI', 'sejoli-bizappay')
            );
        
        endif;

        exit;

    }

    /**
     * Check if current order is using bizappay and will be redirected to bizappay payment channel options
     * Hooked via action sejoli/thank-you/render, priority 100
     * @since   1.0.0
     * @param   array  $order Order data
     * @return  void
     */
    public function check_for_redirect( array $order ) {

        if(
            isset( $order['payment_info']['bank'] ) &&
            'BIZAPPAY' === strtoupper( $order['payment_info']['bank'] )
        ) :

            if( 'on-hold' === $order['status'] ) :
                
                $this->prepare_bizappay_data( $order );

            elseif( in_array( $order['status'], array( 'refunded', 'cancelled' ) ) ) :

                $title = __('Order telah dibatalkan', 'sejoli-bizappay');
                require 'template/checkout/order-cancelled.php';

            else :

                $title = __('Order sudah diproses', 'sejoli-bizappay');
                require 'template/checkout/order-processed.php';

            endif;

            exit;

        endif;
    
    }

    /**
     * Display payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media email,whatsapp,sms
     * @return  string
     */
    public function display_payment_instruction( $invoice_data, $media = 'email' ) {
        
        if( 'on-hold' !== $invoice_data['order_data']['status'] ) :
            return;
        endif;

        $content = sejoli_get_notification_content(
                        'bizappay',
                        $media,
                        array(
                            'order' => $invoice_data['order_data']
                        )
                    );

        return $content;
    
    }

    /**
     * Display simple payment instruction in notification
     * @since   1.0.0
     * @param   array    $invoice_data
     * @param   string   $media
     * @return  string
     */
    public function display_simple_payment_instruction( $invoice_data, $media = 'email' ) {

        if( 'on-hold' !== $invoice_data['order_data']['status'] ) :
            return;
        endif;

        $content = __('via Bizappay', 'sejoli-bizappay');

        return $content;

    }

    /**
     * Set payment info to order data
     * @since   1.0.0
     * @param   array $order_data
     * @return  array
     */
    public function set_payment_info( array $order_data ) {

        $trans_data = [
            'bank' => 'Bizappay'
        ];

        return $trans_data;

    }

    /**
     * Bizappay Renew Token
     * @since   1.0.0
     * @param   array $api_uri, $apikey
     * @return  array
     */
    private function bizappay_renew_token( $api_uri, $apikey ) {
        
        $postData = [
            'apiKey' => $apikey
        ];

        $result = wp_remote_post($api_uri.'/token', array(
            'body'    => $postData,
            'timeout' => 300
        ));
    
        $resBody = wp_remote_retrieve_body( $result );
        $resBody = json_decode( ( $resBody ), true );

        if ( isset( $resBody['token'] ) && !empty( $resBody['token'] ) ) {
            
            $mode = carbon_get_theme_option( 'paypal_mode' );
            add_option( 'bizappay_'.$mode.'_token', $resBody['token'] );
            add_option( 'bizappay_'.$mode.'_token_expiration', time() + 3600 );

            return $resBody['token'];
        
        } else {
        
            return 0;
        
        }

    }

    /**
     * Excecute Transaction
     * @since   1.0.0
     * @return  array
     */
    private function executeTransaction( $request_url, $token, $params ) {
        
        $result = wp_remote_post($request_url, array(
            'headers' => array(
                            "Content-type"   => "application/x-www-form-urlencoded;charset=UTF-8",
                            "Authentication" => $token
                        ),
            'body'    => $params,
            'timeout' => 300
        ));

        if( is_wp_error( $result ) ){
            
            return [
                'success' => 0
            ];
        
        }

        $resBody = wp_remote_retrieve_body( $result );

        $resBody = json_decode( ( $resBody ), true );

        return $resBody;

    }

    /**
     * Paypal Generate Iso Time
     * @since   1.0.0
     * @return  time
     */
    private function bizappay_generate_isotime() {
        
        $fmt  = date( 'Y-m-d\TH:i:s' );
        $time = sprintf( "$fmt.%s%s", substr( microtime(), 2, 3 ), date( 'P' ) );

        return $time;

    }

}
