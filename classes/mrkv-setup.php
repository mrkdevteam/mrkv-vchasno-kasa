<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

# Check if class exist
if (!class_exists('MRKV_SETUP')){
	/**
	 * Class for setup plugin
	 */
	class MRKV_SETUP
	{
		/**
		 * @var string Path to plugin
		 * */
		private $file_path;
		
		/**
		 * Constructore create object
		 * @var string Path to plugin directory
		 * */
		function __construct($file_path)
		{
			# Get main file directory
			$this->file_path = $file_path;

			# Register settings data
			add_action('admin_init', array($this, 'mrkv_vchasno_kasa_register_mysettings'));
			# Create settings page
			add_action('admin_menu', array($this, 'mrkv_vchasno_kasa_register_plugin_page'));

			# Add action to order details actions
			add_action('woocommerce_order_actions', array($this, 'mrkv_vchasno_kasa_wc_add_order_meta_box_action'));
			# Create bill process
			add_action('woocommerce_order_action_create_bill_vchasno_kasa_action', array($this, 'mrkv_vchasno_kasa_wc_process_order_meta_box_action'));

			# Check HPOS
			if(class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()){
				# Add order admin column
				add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'mrkv_vchasno_kasa_wc_new_order_column'), 20);
				# Add data to custom order list column
				add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content_hpos'), 20, 2);
			}
			else{
				# Add order admin column
				add_filter('manage_edit-shop_order_columns', array($this, 'mrkv_vchasno_kasa_wc_new_order_column'));
				# Add data to custom order list column
				add_action('manage_shop_order_posts_custom_column', array($this, 'mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content'));
			}

			# Add widget to dashboard
			add_action('wp_dashboard_setup', array($this, 'mrkv_vchasno_kasa_ppo_status_dashboard_widget'));

			# Ajax Functions
			add_action( 'wp_ajax_clearlog', array($this, 'clear_all_log') );
			add_action( 'wp_ajax_nopriv_clearlog', array($this, 'clear_all_log') );

			# Add monthly time in cron
			add_filter( 'cron_schedules', array($this, 'monthly_cron_job_recurrence') );

			# Add function in cron hook 	
			add_action('clear_all_log_plugin_event_hook', array($this,'clear_all_log'));

			# Add function autocreate order receipt
			add_action('woocommerce_order_status_changed', array($this,'mrkv_vchasno_kasa_auto_create_receipt'), 99, 3);

			# Add metabox to order edit
			add_action( 'add_meta_boxes', array($this, 'mrkv_vchasno_kasa_wc_add_metabox'));

			# Add save metabox to order edit
			add_action( 'wp_ajax_submit_morkva_vchasno_kasa', array($this, 'mrkv_vchasno_kasa_wc_do_metabox_action') );
			add_action( 'wp_ajax_nopriv_submit_morkva_vchasno_kasa', array($this, 'mrkv_vchasno_kasa_wc_do_metabox_action') );

			# Cron for clean log every month
			if( ! wp_next_scheduled( 'clear_all_log_plugin_event_hook' ) ) {
				# Add event
				wp_schedule_event( time(), 'monthly', 'clear_all_log_plugin_event_hook');
			}
		}

		/**
		 * Register all settings data
		 * */
		public function mrkv_vchasno_kasa_register_mysettings(){
			# List setting options
			$options = array(
				'mrkv_kasa_token',
				'mrkv_kasa_code_type_payment',
				'mrkv_kasa_tax_group',
				'mrkv_kasa_test_token',
				'mrkv_kasa_test_enabled',
				'mrkv_kasa_shift_status',
				'mrkv_kasa_cashier',
				'mrkv_kasa_receipt_creation',
				'mrkv_kasa_payment_order_statuses',
				'mrkv_kasa_phone',
				'mrkv_kasa_shipping_price',
				'mrkv_kasa_skip_zero_product',
				'mrkv_kasa_receipt_send_user',
				'mrkv_kasa_receipt_send_type'
	        );

	        # Register all options
	        foreach ($options as $option) {
	            register_setting('mrkv_kasa-settings-group', $option);
	        }
		}

		/**
		 * Register setting page
		 * */
		public function mrkv_vchasno_kasa_register_plugin_page(){
			# Add settings page
			$menu = add_menu_page(__('Налаштування пРРО Вчасно Каса', 'mrkv-vchasno-kasa'), __('Вчасно Каса', 'mrkv-vchasno-kasa'), 'manage_woocommerce', 'vchasno_kasa_settings', array($this, 'mrkv_vchasno_kasa_show_plugin_admin_page'), plugin_dir_url($this->file_path) . 'assets/imgs/logo-kasa-circle-purple.svg');

			# Add styles to setting page
			add_action( 'load-' . $menu, array($this, 'setting_admin_enqueue_scripts'), 100);
		}

		/**
		 * Add Styles to setting page
		 * */
		public function setting_admin_enqueue_scripts(){
			# Include Css
			wp_enqueue_style( 'chosen-css', plugin_dir_url(__FILE__) . '../assets/css/chosen.min.css' );
			wp_enqueue_style( 'setting-css', plugin_dir_url(__FILE__) . '../assets/css/settings.css' );
		}

		/**
		 * Get template of settings page
		 * */
		public function mrkv_vchasno_kasa_show_plugin_admin_page(){
			# Include js file
			wp_enqueue_script( 'chosen-js', plugin_dir_url(__FILE__) . '../assets/js/chosen.jquery.min.js', array( 'jquery' ), '3.0' );
			
			# Show Settings Page template
			include plugin_dir_path($this->file_path) . "templates/mrkv-setting-page-template.php"; 
		}

		/**
		 * Add action to woo order
		 * @param array All actions for order
		 * @return array All actions for order with new
		 * */
		public function mrkv_vchasno_kasa_wc_add_order_meta_box_action($actions){
			# Add action
			$actions['create_bill_vchasno_kasa_action'] = __('Створити чек Вчасно Каса', 'mrkv-vchasno-kasa');
			# Return all list
        	return $actions;
		}

		/**
		 * Create bill process function
		 * @param object Order data
		 * */
		public function mrkv_vchasno_kasa_wc_process_order_meta_box_action($order){
			# Include Create receipt class
			include plugin_dir_path($this->file_path) . "classes/mrkv-vchasno-kasa-receipt.php";

			# Set type creation
			$type_creation = 'handle';

			# Creator recaipt
			$creator = new MRKV_VCHASNO_KASA_RECEIPT($order, $type_creation);

			# Create Receipt
			$creator->create_receipt();
		}

		/**
		 * Add order admin column
		 * @param array All columns in order list table
		 * @param array All columns in order list table with new
		 * */
		public function mrkv_vchasno_kasa_wc_new_order_column($columns){
			# Add column
			$columns['receipt_vchasno_kasa_column'] = __('ID Чека Вчасно Каса', 'mrkv-vchasno-kasa');
			# Return list
        	return $columns;
		}

		/**
		 * Fill ID Receipt column
		 * @param array All column with content
		 * */
		public function mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content($column){
			# Get global current order data
	        global $the_order;

	        # If this our column
	        if ('receipt_vchasno_kasa_column' === $column) {
	        	# Get Recaipt ID
	            $receipt_url = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_url', true);
	            $receipt_id = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_id', true);

	            # Check if exist
	            if($receipt_url && $receipt_id){
	            	# Print link to Vchasno Kasa Receipt 
 	            	printf('<style>.vchasno-link:hover{opacity:.7;}</style><a class="vchasno-link" style="background: #EAB5F7 !important;background-color: #EAB5F7 !important;border-color: #cec2d1 !important;color: #010101 !important;font-weight: 600;font-size: 15px;padding: 7px 13px;border: 1px solid;border-radius: 5px;" href="%s" target="_blank">%s</a>', "{$receipt_url}", $receipt_id);
	            }
	        }
	    }

	    /**
		 * Fill ID Receipt column
		 * @param array All column with content
		 * */
		public function mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content_hpos($column, $the_order){
	        # If this our column
	        if ('receipt_vchasno_kasa_column' === $column) {
	        	# Get Recaipt ID
	            $receipt_url = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_url', true);
	            $receipt_id = get_post_meta($the_order->get_id(), 'vchasno_kasa_receipt_id', true);

	            # Check if exist
	            if($receipt_url && $receipt_id){
	            	# Print link to Vchasno Kasa Receipt 
 	            	printf('<style>.vchasno-link:hover{opacity:.7;}</style><a class="vchasno-link" style="background: #EAB5F7 !important;background-color: #EAB5F7 !important;border-color: #cec2d1 !important;color: #010101 !important;font-weight: 600;font-size: 15px;padding: 7px 13px;border: 1px solid;border-radius: 5px;" href="%s" target="_blank">%s</a>', "{$receipt_url}", $receipt_id);
	            }
	        }
	    }

	    /**
	     * Register plugin dashboard widget only for admin role
	     * */
	    public function mrkv_vchasno_kasa_ppo_status_dashboard_widget(){
	    	# Check user role
	    	if (current_user_can('activate_plugins')) {
	    		# Add widget
	            wp_add_dashboard_widget('status_widget_vchasno', __('Вчасно Каса', 'mrkv-vchasno-kasa') . '<img src="' .  plugin_dir_url(__FILE__) . '../assets/imgs/logo-kasa-circle-purple.svg' . '" />', array($this, 'mrkv_vchasno_kasa_status_widget_form'));
	        }
	    }

	    /**
	     * Plugin dashboard widget functionality
	     * */
	    public function mrkv_vchasno_kasa_status_widget_form(){
	    	# Show Widget Page template
			include plugin_dir_path($this->file_path) . "templates/mrkv-widget-template.php"; 
	    }

	    /**
	     * Claer all text in log file 
	     * */
	    public function clear_all_log(){
	    	# Include Create receipt class
			include plugin_dir_path($this->file_path) . "classes/mrkv-logger-kasa.php";

			# Get logger class
			$logger = new MRKV_LOGGER_KASA();

			# Clean log file
			$logger->clear_file_log();

			# Close Ajax query
			wp_die();
	    }

	    /**
	     * Add monthly time
	     * @param array All schedules in wp site
	     * @return array All schedules with montly
	     * */
	    public function monthly_cron_job_recurrence($schedules){
	    	# Create monthly scedules
	    	$schedules['monthly'] = array(
				'display' => __( 'Once monthly', 'mrkv-vchasno-kasa' ),
				'interval' => 2635200,
			);

			# Return array with monthly schedules
			return $schedules;
	    }

	    /**
	     * Automatic receipt creation
	     *
	     * @param string $order_id Order ID
	     * @param string $old_status old order status
	     * @param string $new_status new order status
	     */
	    public function mrkv_vchasno_kasa_auto_create_receipt($order_id, $old_status, $new_status){
	    	# Include Create receipt class
			include plugin_dir_path($this->file_path) . "classes/mrkv-vchasno-kasa-receipt.php";

			# Get order data
			$order = wc_get_order( $order_id );

			# Set type creation
			$type_creation = 'auto';

			# Creator recaipt
			$creator = new MRKV_VCHASNO_KASA_RECEIPT($order, $type_creation);

			# Create Receipt
			$creator->create_receipt();
	    }

	    /**
	     * Add metabox
	     * 
	     * */
	    public function mrkv_vchasno_kasa_wc_add_metabox()
	    {	
	    	# Check hpos
	    	if(class_exists( CustomOrdersTableController::class )){
	            $screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
	            ? wc_get_page_screen_id( 'shop-order' )
	            : 'shop_order';
	        }
	        else{
	            $screen = 'shop_order';
	        }

	    	# Add metabox to admin page
	        add_meta_box( 'morkva_vchasno_kasa_metabox', __('Вчасно Каса','woocommerce'), array($this, 'mrkv_vchasno_kasa_wc_add_metabox_content'), $screen, 'side', 'core' );
	    }

	    /**
	     * Add content to metabox
	     * 
	     * */
	    public function mrkv_vchasno_kasa_wc_add_metabox_content()
	    {
	    	# Get order data
	        if (isset($_GET["post"]) || isset($_GET["id"])){
	        	# Create order id variable
	        	$order_id = '';

	        	# Set order id
	            if(isset($_GET["post"])){
	            	# Set
	                $order_id = $_GET["post"];    
	            }
	            else{
	            	# Set
	                $order_id = $_GET["id"];
	            }

	            # Get receipt url
		        $receipt_url = get_post_meta($order_id, 'vchasno_kasa_receipt_url', true);
		        # Get receipt id
	            $receipt_id = get_post_meta($order_id, 'vchasno_kasa_receipt_id', true);

	            # Check receipt id
	            if($receipt_id)
	            {
	            	# Show receipt link
	            	printf('Чек: ' . '<a href="%s" target="_blank">%s</a>', $receipt_url, $receipt_id);
	            }
	            else
	            {
	        	    $button_text = __( 'Створити чек', 'woocommerce' );

	            	echo '<div class="mrkv_vchasno_create_receipt">
	            		<div class="mrkv_vchasno_create_receipt_btn button button-primary">' . $button_text . '</div>
	            		<svg style="display: none;" version="1.1" id="L9" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="30px" height="30px" x="0px" y="0px"
						  viewBox="0 0 100 100" enable-background="new 0 0 0 0" xml:space="preserve">
						    <path fill="#000" d="M73,50c0-12.7-10.3-23-23-23S27,37.3,27,50 M30.9,50c0-10.5,8.5-19.1,19.1-19.1S69.1,39.5,69.1,50">
						      <animateTransform 
						         attributeName="transform" 
						         attributeType="XML" 
						         type="rotate"
						         dur="1s" 
						         from="0 50 50"
						         to="360 50 50" 
						         repeatCount="indefinite" />
						  </path>
						</svg></div>';

					echo "<script>
	            		 jQuery('.mrkv_vchasno_create_receipt_btn').click(function(){
					        jQuery.ajax({
					            url: '" .  admin_url( "admin-ajax.php" ) . "',
					            type: 'POST',
					            data: 'action=submit_morkva_vchasno_kasa&order_id=" . $order_id . "', 
					            beforeSend: function( xhr ) {
					                jQuery('.mrkv_vchasno_create_receipt svg').show();
					            },
					            success: function( data ) {
					                location.reload();
					            }
					        });
					    });
	            	</script>";
	            }
	        }
	    }

	    /**
	     * Create receipt by button vchasno kasa
	     * 
	     * @var Order ID
	     * */
	    public function mrkv_vchasno_kasa_wc_do_metabox_action()
	    {
	    	# Check order id
	    	if(isset($_POST[ 'order_id' ])){
	    		# Get order data
	    		$order = wc_get_order( $_POST[ 'order_id' ] );
	    		# Create order vchasno receipt
		        $this->mrkv_vchasno_kasa_wc_process_order_meta_box_action($order);
	    	}
	    }
	}
}