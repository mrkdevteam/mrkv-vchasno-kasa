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
				'mrkv_kasa_payment_order_statuses'
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

			# Creator recaipt
			$creator = new MRKV_VCHASNO_KASA_RECEIPT($order);

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
	}
}