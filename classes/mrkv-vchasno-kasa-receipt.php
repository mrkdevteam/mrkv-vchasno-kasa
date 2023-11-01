<?php
# Include all classes
include plugin_dir_path(__DIR__) . "classes/mrkv-vchasno-kasa-api.php"; 
include plugin_dir_path(__DIR__) . "classes/mrkv-logger-kasa.php"; 
include plugin_dir_path(__DIR__) . "classes/mrkv-shift.php"; 

# Check if class exist
if (!class_exists('MRKV_VCHASNO_KASA_RECEIPT')){
	/**
	 * Class for create receipt
	 */
	class MRKV_VCHASNO_KASA_RECEIPT
	{
		/**
		 * @var string Token number
		 * */
		private $api_vchasno_kasa;

		/**
		 * @var string Shift status 
		 * */
		private $shift_status;

		/**
		 * @var string All data connect 
		 * */
		private $data_connect;

		/**
		 * @param string Current order data
		 * */
		private $order;

		/**
		 * @param string Type creation
		 * */
		private $type_creation;

		/**
		 * @var string Url action
		 * */
		const ACTION_TYPE = 'fiscal/execute';

		/**
		 * @var string Url action notification
		 * */
		const ACTION_TYPE_CHECK = 'notifications/checks';

		/**
		 * Constructor for creator receipt
		 * @param Order data
		 * */
		function __construct($order, $type_creation){
			# Get current Shift data
			$shift = new MRKV_SHIFT();
			# Update status shift
			$shift->update_shift_status();
			# Get shift status
			$this->shift_status = get_option('mrkv_kasa_shift_status', '0');

			# Create data connect
			$connected = new MRKV_CONNECT_DATA();
			# Save data connect
			$this->data_connect = $connected->get_connect_data();

			# Save order data
			$this->order = $order;

			# Save type creation
			$this->type_creation = $type_creation;

			# Save data
			$this->api_vchasno_kasa = new MRKV_VCHASNO_KASA_API($connected->get_connect_data());
		}

		/**
		 * Check shift status
		 * @return boolean Shift status
		 * */
		private function check_shift_status(){
			# Return status 
			return ($this->shift_status == '0') ? 0 : 1;
		}

		/**
		 * Check token exist
		 * @return boolean Result of exist token
		 * */
		private function check_token_exist(){
			# Return answer
			return ($this->data_connect['token'] == '') ? 0 : 1;
		}

		/**
		 * Check if receipt is already created 
		 * */
		private function check_receipt_exist(){
			# Check field exist
			if (! empty(get_post_meta($this->order->get_id(), 'vchasno_kasa_receipt_id', true))) {
				# Return postive answer
				return true;
			}
			# Return negative answer
			return false;
		}

		/**
		 * Check order payment type on exist 
		 * @return boolean If has in list
		 * */
		private function check_payment_types(){
			# Get rules for create receipt
			$ppo_skip_receipt_creation = get_option('mrkv_kasa_receipt_creation');
			$mrkv_kasa_payment_order_statuses = get_option('mrkv_kasa_payment_order_statuses');

			# Get current order status
			$current_status = $this->order->get_status();

			# Check if order fits the rules
			if(isset($ppo_skip_receipt_creation[ $this->order->get_payment_method() ]) && is_array($mrkv_kasa_payment_order_statuses) 
				&& isset($mrkv_kasa_payment_order_statuses[$this->order->get_payment_method()]) 
				&& in_array($current_status, $mrkv_kasa_payment_order_statuses[ $this->order->get_payment_method()])){
				# Continue create
				 return false;
			}
			# Stop create
			return true;
		}

		/**
		 * Return payment type for current method
		 * @return integer Current payment type 
		 * */
		private function get_payment_type(){
			# Get settings payment 
			$ppo_payment_type = get_option('mrkv_kasa_code_type_payment');

			# Check settings exist
			if(isset($ppo_payment_type[ $this->order->get_payment_method() ])){
				# Return type of settings
				return $ppo_payment_type[ $this->order->get_payment_method() ];
			}

			# Return default value
			return 0;
		}

		/**
		 * Create receipt main function
		 * */
		public function create_receipt(){
			# Create log data object
			$log = new MRKV_LOGGER_KASA();

			# Check type creation
			if($this->type_creation != 'handle'){
				# Check order by rules
				if($this->check_payment_types()){
					# Show Error
					$log->save_log(__('Помилка при створені чека: Чек не пройшов по правилам формування чеків', 'mrkv-vchasno-kasa'));

					# Show error in order
					$this->order->add_order_note(__('Помилка при створені чека: Чек не пройшов по правилам формування чеків', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);

					# Stop create
					return;
				}
			}

			# Check shift status
			if(!$this->check_shift_status()){
				# Get current Shift data
				$shift = new MRKV_SHIFT();

				# Open shift
				$shift->open_shift();

				# Update status shift
				$shift->update_shift_status();
			}

			# Check token exist
			if(!$this->check_token_exist()){
				# Show Error
				$log->save_log(__('Помилка при створені чека: Порожній токен', 'mrkv-vchasno-kasa'));

				# Show error in order
				$this->order->add_order_note(__('Помилка при створені чека: Порожній токен', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);

				# Stop create
				return;
			}

			# Check if receipt is already created
			if($this->check_receipt_exist()){
				# Show Error
				$log->save_log(__('Помилка при створені чека: Чек вже було створено для данного замовлення', 'mrkv-vchasno-kasa'));

				# Show error in order
				$this->order->add_order_note(__('Помилка при створені чека: Чек вже було створено для данного замовлення', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);

				# Stop create
				return;
			}

			# Create array with all params
			$params = array();
			# Get main order data
			$order_data = $this->order->get_data();
			# Get admin user
			$user = wp_get_current_user();
			# All items in order
			$goods_items = $this->order->get_items();

			# Get order email
			$email = isset($order_data['billing']['email']) ? $order_data['billing']['email'] : $user->user_email;
			# Get order phone
			$phone = isset($order_data['billing']['phone']) ? $order_data['billing']['phone'] : '';

			# Check if phone enabled
			if(get_option('mrkv_kasa_phone')){

				$pattern = "/^\+380\d{3}\d{2}\d{2}\d{2}$/";

				if(!preg_match($pattern, $phone)){
					$simple_phone_format = substr($phone, -9);
					$phone = '+380' . $simple_phone_format;
				}

				# Set user info in params
				$params['userinfo'] = array(
					'email' => $email,
					'phone' => $phone
				);
			}
			else{
				# Set user info in params
				$params['userinfo'] = array(
					'email' => $email
				);
			}

			# Array of all products
			$goods = array();
			# Total price order
        	$total_price = 0;

        	# Loop all order items
        	foreach ($goods_items as $item) {
        		# Get product price
        		$price = ($item->get_total() / $item->get_quantity());

        		if(($price == 0) && get_option('mrkv_kasa_skip_zero_product', 1)){
        			continue;
        		}

        		# Save item
        		$goods[] = array(
        			'code' => "" . $item->get_id(),
        			'name' => $item->get_name(),
        			'cnt' => $item->get_quantity(),
        			'price' => 'bbb' . number_format($price, 2, '.', '') . 'bbb',
        			'disc' => 'bbb' . number_format(0.00, 2, '.', '') . 'bbb',
        			'taxgrp' => intval(get_option('mrkv_kasa_tax_group', 1))
        		);
	        }

	        # Check if order has delivery price
	        if(get_option('mrkv_kasa_shipping_price', 1) && $this->order->get_shipping_total()){
	        	# Save item
        		$goods[] = array(
        			'code' => "delivery",
        			'name' => __('Доставка', 'mrkv-vchasno-kasa'),
        			'cnt' => 1,
        			'price' => 'bbb' . number_format($this->order->get_shipping_total(), 2, '.', '') . 'bbb',
        			'disc' => 'bbb' . number_format(0.00, 2, '.', '') . 'bbb',
        			'taxgrp' => intval(get_option('mrkv_kasa_tax_group', 1))
        		);
	        }

	        # Comment check
	        $comment = "";
	        if($this->order->customer_message){
	        	$comment = $this->order->customer_message;
	        }

	       

	        # Add source
	        $params['source'] = 'VCD-1880';

			# Set fiscal params
			$params['fiscal'] = array(
				'task' => 1,
				'receipt' => array(
					'sum' => 'bbb' . number_format($this->order->get_total(), 2, '.', '') . 'bbb',
					'round' => 'bbb' . number_format((0.00), 2, '.', '') . 'bbb',
					'comment_up' => '',
					'comment_down' => '',
					'rows' => $goods,
					'pays' => array(
						array(
							'type' => intval($this->get_payment_type()),
							'sum' => 'bbb' . number_format($this->order->get_total(), 2, '.', '') . 'bbb',
							'change' => 'bbb' . number_format((0.00), 2, '.', '') . 'bbb',
							'comment' => $comment,
							'currency' => 'ГРН'
						)
					)
				)
			);

			# Check Cashier data
	        $cashier = get_option('mrkv_kasa_cashier');
	        if(isset($cashier) && $cashier != ''){
	        	$params['fiscal']['cashier'] = '' . $cashier;
	        }

			# Create json
			$json = json_encode($params);

			# Remove quotes with bbb
			$json = str_replace('"bbb', '', $json);
			$json = str_replace('bbb"', '', $json);

			# Show Error
			$log->save_log('<pre>' . print_r($json, 1) . '</pre>');

			# Create receipt (Send post query)
			$response = $this->api_vchasno_kasa->connect($params, self::ACTION_TYPE, $json);

			# Show Error
			$log->save_log('<pre>' . print_r($response, 1) . '</pre>');

			# Decode json response to StdClass
			$result = json_decode($response);

			# Check if result has error
			if($result->errortxt == ''){
				# Save qr link
				$receipt_url = $result->info->doccode;
				# Save id in order
				update_post_meta($this->order->get_id(), 'vchasno_kasa_receipt_id', $receipt_url);

				# Save id in order
				update_post_meta($this->order->get_id(), 'vchasno_kasa_receipt_url', 'https://kasa.vchasno.ua/check-viewer/' . $receipt_url);

				# Add message in log 
				$log->save_log(__('Чек створено для замовлення ' . $this->order->get_id(), 'mrkv-vchasno-kasa'));

				# Show in history
				$this->order->add_order_note(__('Чек створено <a href="https://kasa.vchasno.ua/check-viewer/' . $receipt_url . '" target="blanc">Відкрити</a>', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);

				# Check if send receipt enabled
				if(get_option('mrkv_kasa_receipt_send_user')){
					# Get sender type list
					$sender_type_list = get_option('mrkv_kasa_receipt_send_user');

					# Check selected sender type
					if(isset($sender_type_list) && $sender_type_list && is_array($sender_type_list)){
						# Loop all channel
						foreach($sender_type_list as $sender_type){
							# Set receipnt
							$recipient = $phone;
							# Check sender type 
							if($sender_type == 'email'){
								$recipient = $email;
							}

							# Create sender params
							$params_sender = array(
								'recipient' => $recipient,
								'channel' => $sender_type,
								'check' => $receipt_url
							);

							# Create json
							$json_sender = json_encode($params_sender);

							# Show Error
							$log->save_log('<pre>' . print_r($json_sender, 1) . '</pre>');

							# Create receipt (Send post query)
							$response_sender = $this->api_vchasno_kasa->send_receipt($params_sender, self::ACTION_TYPE_CHECK, $json_sender);

							# Show Error
							$log->save_log('<pre>' . print_r($response_sender, 1) . '</pre>');
						}
					}
				}
			}
			else{
				# Show Error
				$log->save_log(__('Помилка при створенні чеку: ', 'mrkv-vchasno-kasa'));
				$log->save_log($result->errortxt);

				# Show in history
				$this->order->add_order_note(__('Помилка при створенні чеку. Докладніше у логах плагіну', 'mrkv-vchasno-kasa'), $is_customer_note = 0, $added_by_user = false);
			}
		}
	}
}