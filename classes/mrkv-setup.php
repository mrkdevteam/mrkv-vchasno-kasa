<?php
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
			# Add order admin column
			add_filter('manage_edit-shop_order_columns', array($this, 'mrkv_vchasno_kasa_wc_new_order_column'));
			# Add data to custom order list column
			add_action('manage_shop_order_posts_custom_column', array($this, 'mrkv_vchasno_kasa_wc_cogs_add_order_receipt_column_content'));

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
			add_action( 'save_post', array($this, 'mrkv_vchasno_kasa_wc_do_metabox_action'));

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
				'mrkv_kasa_skip_zero_product'
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
	    	# Add metabox to admin page
	        add_meta_box( 'morkva_vchasno_kasa_metabox', __('Вчасно Каса','woocommerce'), array($this, 'mrkv_vchasno_kasa_wc_add_metabox_content'), 'shop_order', 'side', 'core' );
	    }

	    /**
	     * Add content to metabox
	     * 
	     * */
	    public function mrkv_vchasno_kasa_wc_add_metabox_content()
	    {
	    	# Get order data
	        global $post;

	        # Get receipt url
	        $receipt_url = get_post_meta($post->ID, 'vchasno_kasa_receipt_url', true);
	        # Get receipt id
            $receipt_id = get_post_meta($post->ID, 'vchasno_kasa_receipt_id', true);

            # Check receipt id
            if($receipt_id)
            {
            	# Show receipt link
            	printf('Чек: ' . '<a href="%s" target="_blank">%s</a>', $receipt_url, $receipt_id);
            }
            else
            {
        	    $button_text = __( 'Створити чек', 'woocommerce' );

            	echo '<form method="post" action="">
			        <input type="submit" name="submit_morkva_vchasno_kasa_action" class="button button-primary" value="' . $button_text . '"/>
			        <input type="hidden" name="morkva_vchasno_kasa_action_nonce" value="' . wp_create_nonce() . '">
			    </form>';
            }
	    }

	    /**
	     * Create receipt by button vchasno kasa
	     * 
	     * @var Order ID
	     * */
	    public function mrkv_vchasno_kasa_wc_do_metabox_action($post_id)
	    {
	    	// Check type
	    	if(isset($_POST[ 'post_type' ])){
	    		// Only for shop order
			    if ( 'shop_order' != $_POST[ 'post_type' ] )
			        return $post_id;

			    // Check if our nonce is set (and our cutom field)
			    if ( ! isset( $_POST[ 'morkva_vchasno_kasa_action_nonce' ] ) && isset( $_POST['submit_morkva_vchasno_kasa_action'] ) )
			        return $post_id;

			    $nonce = $_POST[ 'morkva_vchasno_kasa_action_nonce' ];

			    // Verify that the nonce is valid.
			    if ( ! wp_verify_nonce( $nonce ) )
			        return $post_id;

			    // Checking that is not an autosave
			    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			        return $post_id;

			    // Check the user’s permissions (for 'shop_manager' and 'administrator' user roles)
			    if ( ! current_user_can( 'edit_shop_order', $post_id ) && ! current_user_can( 'edit_shop_orders', $post_id ) )
			        return $post_id;

			    // Action to make or (saving data)
			    if( isset( $_POST['submit_morkva_vchasno_kasa_action'] ) ) 
			    {
			    	$order = wc_get_order( $post_id );
			        $this->mrkv_vchasno_kasa_wc_process_order_meta_box_action($order);
			    }
	    	}
	    	
	    }
	}
}