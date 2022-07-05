<?php
/**
 * Rokpay Payment Gateway
 *
 * @class   Rokapy_Gateway_Transfer
 * @extends	WC_Payment_Gateway
 */

class Rokapy_Gateway_Transfer extends WC_Payment_Gateway
{
	/**
	 * Array of locales
	 *
	 * @var array
	 */
	public $locale;
	public $domain;
	public $api_error =false;
    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
		$this->domain             = 'custom_payment';
        $this->id                 = 'offline_gateway';
        $this->icon               = apply_filters('woocommerce_offline_icon', '');
        $this->has_fields         = false;
        $this->method_title       = __('Rokpay', $this->domain);
        $this->method_description = __('Make payment using Rokpay paymentgateway', $this->domain);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title            = $this->get_option('title');
        $this->description      = $this->get_option('description');
        $this->instructions     = $this->get_option('instructions');
        
        // BACS account fields shown on the checkout page and in admin configuration tab.
		$this->account_details = get_option(
			'woocommerce_bacs_accountss',
			array(
				array(
					'shop'   => $this->get_option( 'shop' ),
					'key' => $this->get_option( 'key' ),
					
				),
			)
		);

        // Actions
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Customer Emails
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }


    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields()
    {

        $this->form_fields = apply_filters('wc_offline_form_fields', array(

            'enabled' => array(
                'title'   => __('Enable/Disable', $this->domain),
                'type'    => 'checkbox',
                'label'   => __('Enable Payment', $this->domain),
                'default' => 'yes'
            ),

            'title' => array(
                'title'       => __('Title', $this->domain),
                'type'        => 'text',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', $this->domain),
                'default'     => __('Rokpay Payment', $this->domain),
                'desc_tip'    => true,
            ),
  		'environment' => array(
                'title'         => __('Environment'), $this->id,
                'type'          => 'select',
                'custom_attributes' => array( 'required' => 'required' ),
                'options'       => array("https://staging.rokpay.cloud:8081/rokpay/order/verify-shop" => "Staging", "https://rokpay.cloud:8081/rokpay/order/verify-shop" => "Production"),
                'description'   => __('Select environment.', $this->id),
                'default'       => '0'
            ),
            'description' => array(
                'title'       => __('Description', $this->domain),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', $this->domain),
                'default'     => __('Make payment using rokpay.', $this->domain),
                'desc_tip'    => true,
            ),

            'instructions' => array(
                'title'       => __('Instructions', $this->domain),
                'type'        => 'textarea',
                'description' => __('Instructions', $this->domain),
                'default'     => '',
                'desc_tip'    => true,
            ),

            'account_details' => array(
				'type' => __('account_details', $this->domain),
			),
        ));
    }
     public function testAPI($shopnumber, $apikey,$url)
    {
        $digestHash = $this->Rp_digest_hash($apikey, $shopnumber);
        $data = array(
            "amount" => "21",
            "cancellationUrl" => "",
            "currency" => "USD",
            "failureUrl" => "",
            "products" => array(
                array(
                    "name" => "abc",
                    "price" => 10,
                    "quantity" => 1
                ) ,
                array(
                    "name" => "abc1",
                    "price" => 11,
                    "quantity" => 1
                )
            ) ,
            "shopNumber" => $shopnumber,
            "shopOrderId" => 1124293,
            "shopTransactionId" => "Txnid122",
            "successUrl" => "",
            "digest" => $digestHash,
             "shippingAmount"=>10,
            "discounts"=>array(array("name"=>"test","type"=>"fixed","value"=>20)),
            "shopOrderNumber"=>112429311
        );

        $response = $this->makeCurlPost($data, $url);
      
        return $response;
    }
   public  function Rp_digest_hash($apiKey, $shopNumber = "")
{
    $digest = $shopNumber . $apiKey;
    $digestHash = hash('sha512', $digest);
    return $digestHash;
}
public function makeCurlPost($data, $url)
{
	$api_success = false;
    $ch = curl_init($url);
    $payload = json_encode($data);
    // Attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    // Set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type:application/json'
    ));
    // Return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Execute the POST request
    $result = curl_exec($ch);
    $response = json_decode($result);
  
    curl_close($ch);
	if (array_key_exists("status", get_object_vars($response)))
	{
	    $api_success = false;
	}
	elseif (array_key_exists("orderRequest", get_object_vars($response)))
	{
	    $api_success = true;

	}  
   
    return $api_success;
}
	/**
	 * Save account details table.
	 */
	public function save_account_details() {
		
		$accounts = array();

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce verification already handled in WC_Admin_Settings::save()
		if ( isset( $_POST['shop'] ) && isset( $_POST['shop'] ) ) {
			 
			$shop   = wc_clean( wp_unslash( $_POST['shop'] ) );
			$key = wc_clean( wp_unslash( $_POST['key'] ) );
			
			$settings = get_option( "woocommerce_offline_gateway_settings");

			$url = $settings['environment'];
			$response = $this->testAPI($shop[0], $key[0],$url);

			if($response){
				foreach ( $shop as $i => $name ) {
				if ( ! isset( $shop[ $i ] ) ) {
					continue;
				}

				$accounts[] = array(
					'shop'   => $shop[ $i ],
					'key' => $key[ $i ],
					
				);
			}
			update_option( 'woocommerce_bacs_accountss', $accounts );
			}else{
					foreach ( $shop as $i => $name ) {
				if ( ! isset( $shop[ $i ] ) ) {
					continue;
				}

				$accounts[] = array(
					'shop'   => $shop[ $i ],
					'key' => $key[ $i ],
					
				);
			}
			//update_option( 'woocommerce_bacs_accountss', $accounts );
			
			WC_Admin_Settings::add_error("API details not correct." ); 

			}
			
			
		}
		
		
	}

    /**
	 * Get country locale if localized.
	 *
	 * @return array
	 */
	public function get_country_locale() {

		if ( empty( $this->locale ) ) {

			// Locale information to be used - only those that are not 'Sort Code'.
			$this->locale = apply_filters(
				'woocommerce_get_bacs_locale',
				array(
					'AU' => array(
						'sortcode' => array(
							'label' => __( 'BSB', 'wc-gateway-offline' ),
						),
					),
					'CA' => array(
						'sortcode' => array(
							'label' => __( 'Bank transit number', 'wc-gateway-offline' ),
						),
					),
					'IN' => array(
						'sortcode' => array(
							'label' => __( 'IFSC', 'wc-gateway-offline' ),
						),
					),
					'IT' => array(
						'sortcode' => array(
							'label' => __( 'Branch sort', 'wc-gateway-offline' ),
						),
					),
					'NZ' => array(
						'sortcode' => array(
							'label' => __( 'Bank code', 'wc-gateway-offline' ),
						),
					),
					'SE' => array(
						'sortcode' => array(
							'label' => __( 'Bank code', 'wc-gateway-offline' ),
						),
					),
					'US' => array(
						'sortcode' => array(
							'label' => __( 'Routing number', 'wc-gateway-offline' ),
						),
					),
					'ZA' => array(
						'sortcode' => array(
							'label' => __( 'Branch code', 'wc-gateway-offline' ),
						),
					),
				)
			);

		}

		return $this->locale;

	}

    /**
	 * Generate account details html.
	 *
	 * @return string
	 */
	public function generate_account_details_html() {

		ob_start();

		$country = WC()->countries->get_base_country();
		$locale  = $this->get_country_locale();

		// Get sortcode label in the $locale array and use appropriate one.
		$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'wc-gateway-offline' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Account details:', 'wc-gateway-offline' ); ?></th>
			<td class="forminp" id="bacs_accounts">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php esc_html_e( 'Shop number', 'wc-gateway-offline' ); ?></th>
								<th><?php esc_html_e( 'API key', 'wc-gateway-offline' ); ?></th>
								
							</tr>
						</thead>
						<tbody class="accounts">
							<?php
							$i = -1;
							if ( $this->account_details ) {
								foreach ( $this->account_details as $account ) {
									$i++;

									echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['shop'] ) ) . '" name="shop[' . esc_attr( $i ) . ']" /></td>
										<td><input type="password" value="' . esc_attr( $account['key'] ) . '" name="key[' . esc_attr( $i ) . ']" /></td>
										
									</tr>';
								}
							}
							?>
						</tbody>
						<!-- <tfoot>
							<tr>
								<th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', 'wc-gateway-offline' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', 'wc-gateway-offline' ); ?></a></th>
							</tr>
						</tfoot> -->
					</table>
				</div>
				<script type="text/javascript">
					jQuery(function() {
						jQuery('#bacs_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#bacs_accounts').find('tbody .account').length;

							jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="shop[' + size + ']" /></td>\
									<td><input type="text" name="key[' + size + ']" /></td>\
									</tr>').appendTo('#bacs_accounts table tbody');

							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();

	}

	/**
	 * Get bank details and place into a list format.
	 *
	 * @param int $order_id Order ID.
	 */
	private function bank_details( $order_id = '' ) {

		if ( empty( $this->account_details ) ) {
			return;
		}

		// Get order and store in $order.
		$order = wc_get_order( $order_id );

		$bacs_accounts = apply_filters( 'woocommerce_bacs_accountss', $this->account_details );

		if ( ! empty( $bacs_accounts ) ) {
			$account_html = '';
			$has_details  = false;

			foreach ( $bacs_accounts as $bacs_account ) {
				$bacs_account = (object) $bacs_account;

				if ( $bacs_account->shop ) {
					$account_html .= '<p class="wc-bacs-bank-details-account-name"><u>' . wp_kses_post( wp_unslash( $bacs_account->shop ) ) . '</u>:</p>' . PHP_EOL;
				}

				$account_html .= '<ul class="wc-bacs-bank-details order_details bacs_details">' . PHP_EOL;

				// BACS account fields shown on the Checkout page.
				$account_fields = apply_filters(
					'woocommerce_bacs_account_fields',
					array(
						'shop'      => array(
							'label' => __( 'Bank', 'woocommerce' ),
							'value' => $bacs_account->shop,
						),
						'key' => array(
							'label' => __( 'key', 'woocommerce' ),
							'value' => $bacs_account->key,
						),
						
					),
					$order_id
				);

				foreach ( $account_fields as $field_key => $field ) {
					if ( ! empty( $field['value'] ) ) {
						$account_html .= '<li class="' . esc_attr( $field_key ) . '">' . wp_kses_post( $field['label'] ) . ': <strong>' . wp_kses_post( wptexturize( $field['value'] ) ) . '</strong></li>' . PHP_EOL;
						$has_details   = true;
					}
				}

				$account_html .= '</ul>';
			}

			// if ( $has_details ) {
			// 	echo '<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">' . esc_html__( 'Our bank details', 'woocommerce' ) . '</h2>' . wp_kses_post( PHP_EOL . $account_html ) . '</section>';
			// }
		}

	}

	public function payment_fields(){

		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		
		$this->bank_details();
		?>
		<br>
	
		<?php
	}

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
    }


    /**
     * Add content to the WC emails.
     *
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {

        if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }


    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
    	$order = wc_get_order($order_id);  

     
    	//get_cancel_order_url
    	//get_checkout_order_received_url
    	//echo $order->get_checkout_payment_url(true);
    	//exit;
        //add_query_arg('key', $order_key, $order->get_checkout_payment_url(true))

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Waiting rokpay ', $this->domain));

        // Reduce stock levels
       $order->reduce_order_stock();

        // Remove cart
      // WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'     => 'success',
            'redirect'    => $this->get_return_url($order)
        );
    }
}

/**
 * Update the order meta with field value
 */
add_action( 'woocommerce_checkout_update_order_meta', 'abpt_custom_payment_update_order_meta' );
function abpt_custom_payment_update_order_meta( $order_id ) {
    if($_POST['payment_method'] != 'offline_gateway')
        return;

    update_post_meta( $order_id, 'attach_id', sanitize_text_field( $_POST['attach_id'] ) );
}


/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_order_details', 'abpt_custom_checkout_field_display_admin_order_meta', 10, 1 );
function abpt_custom_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'offline_gateway')
        return;

    $attach_id = get_post_meta( $order->id, 'attach_id', true );
	$src=wp_get_attachment_url($attach_id, 'full');
    echo '<p><strong>'.__( 'Bank Payment Invoice' ).':</strong> <a href="'.$src.'"><img src="'.$src.'" height="50"/></a></p>';
}
