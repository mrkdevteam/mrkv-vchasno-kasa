<?php
# Check empty payment
$all_payment_for_test = array_filter(WC()->payment_gateways->payment_gateways(), function ($gateway) {
	                                return 'yes' === $gateway->enabled;
	                            });
$all_payment_order_statuses = get_option('mrkv_kasa_payment_order_statuses');
$error_message = '';
foreach($all_payment_for_test as $id => $gateway){
	if($all_payment_order_statuses && !array_key_exists($id, $all_payment_order_statuses)){
		$ppo_skip_receipt_creation = get_option('mrkv_kasa_receipt_creation');
		if($ppo_skip_receipt_creation && array_key_exists($id, $ppo_skip_receipt_creation)){
			unset($ppo_skip_receipt_creation[$id]);
			$result = update_option('mrkv_kasa_receipt_creation', $ppo_skip_receipt_creation);
			$error_message = __('Помилка активації способу доставки. Не обрано жодгого статусу замовлення', 'mrkv-vchasno-kasa');
		}
	}
}

# List of all payment types
$payment_types = array(__('Готівка', 'mrkv-vchasno-kasa'), 
					   __('Безготівка', 'mrkv-vchasno-kasa'), 
					   __('Картка', 'mrkv-vchasno-kasa'),
					   __('Передплата', 'mrkv-vchasno-kasa'),
					   __('Післяоплата', 'mrkv-vchasno-kasa'),
					   __('Кредит', 'mrkv-vchasno-kasa'),
					   __('Сертифікат', 'mrkv-vchasno-kasa'),
					   __('Чек', 'mrkv-vchasno-kasa'));
# List tax groups
$tax_groupes = array(__('ПДВ 20% (А)', 'mrkv-vchasno-kasa'),
					 __('Без ПДВ (Б)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 20% + акциз 5% (ГД)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 7% (В)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 0% (Е)', 'mrkv-vchasno-kasa'), 
					 __('Без ПДВ + акциз 5% (Ж)', 'mrkv-vchasno-kasa'), 
					 __('Не є об\'єктом ПДВ (З)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 20% + ПФ 7.5% (ИК)', 'mrkv-vchasno-kasa'), 
					 __('ПДВ 14% (Л)', 'mrkv-vchasno-kasa'), 
					 __('ПДФО 18% Військовий збір 1.5% (М)', 'mrkv-vchasno-kasa'));

# Current  tax group 
$current_tax_group = get_option('mrkv_kasa_tax_group', '1');
# Test enabled
$test_is_active = (get_option('mrkv_kasa_test_enabled') == '') ? false : get_option('mrkv_kasa_test_enabled');

# Phone enabled
$mrkv_kasa_phone = (get_option('mrkv_kasa_phone') == '') ? false : get_option('mrkv_kasa_phone');

# Shipping price enabled
$mrkv_kasa_shipping_price = (get_option('mrkv_kasa_shipping_price') == '') ? false : get_option('mrkv_kasa_shipping_price');

# Zero price product skip enabled
$mrkv_kasa_skip_zero_product = (get_option('mrkv_kasa_skip_zero_product') == '') ? false : get_option('mrkv_kasa_skip_zero_product');

# Log file open
$debug_log = file_get_contents(__DIR__ . '/../logs/debug.log');
?>
<div class="vchasno-kasa-main">
	<div class="wrap">
		<h1><span><?php echo get_admin_page_title(); ?></span><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/imgs/logo-kasa-circle-purple.svg'; ?>" /></h1>
		<hr/>
		<p><?php echo __('Плагін інтеграції WooCommerce з Kasa.vchasno.com.ua, сервісом програмної реєстрації розрахункових операцій (пРРО).', 'mrkv-vchasno-kasa'); ?></p>
		<?php settings_errors(); ?>
		<?php 
			if($error_message){
				?>
					<div id="setting-error-settings_updated" class="notice notice-error settings-error is-dismissible"> 
					<p><strong><?php echo esc_html($error_message); ?></strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Приховати це сповіщення.</span></button></div>
				<?php
			}
		?>
		
		<form method="post" action="options.php">
			<?php settings_fields('mrkv_kasa-settings-group'); ?>
			<div class="columns-half">
				<div class="columns-half__column">
					<h2><?php echo __('Загальні налаштування', 'mrkv-vchasno-kasa'); ?></h2>
					<hr>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Токен каси', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="text" name="mrkv_kasa_token" value="<?php echo esc_html(get_option('mrkv_kasa_token')); ?>" />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Іноформація про касира', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="text" name="mrkv_kasa_cashier" value="<?php echo esc_html(get_option('mrkv_kasa_cashier')); ?>" />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Код податкової групи', 'mrkv-vchasno-kasa'); ?></p>
						<select name="mrkv_kasa_tax_group" id="mrkv_kasa_tax_group">
							<?php 
								
								$index = 1;
								foreach($tax_groupes as $group){
									$selected = ($current_tax_group == $index) ? 'selected' : '';
									echo '<option ' . esc_html($selected) . ' value="'. esc_html($index) . '">' . esc_html($group) . '</option>';
									++$index;
								}
							?>
							
						</select>
					</div>
					<h2 class="mt-40"><?php echo __('Правила автоматичного формування чеків', 'mrkv-vchasno-kasa'); ?></h2>
					<hr>
					<p><?php echo __('Налаштуйте для яких саме способів оплати створювати чеки автоматично. Ви завжди зможете створити чек вручну зі сторінки замовлення.', 'mrkv-vchasno-kasa'); ?></p>
					<div class="mrkv_table-payment">
						<div class="mrkv_table-payment__header">
							<p><?php echo __('Спосіб оплати', 'mrkv-vchasno-kasa'); ?></p>
							<p><?php echo __('Тип оплати', 'mrkv-vchasno-kasa'); ?></p>
							<p><?php echo __('Статуси замовлення', 'mrkv-vchasno-kasa'); ?></p>
						</div>
						<hr>
						<div class="mrkv_table-payment__body">
								<?php
								$enabled_gateways = array_filter(WC()->payment_gateways->payment_gateways(), function ($gateway) {
	                                return 'yes' === $gateway->enabled;
	                            });
	                            $ppo_payment_type          = get_option('mrkv_kasa_code_type_payment');
                            	$ppo_skip_receipt_creation = get_option('mrkv_kasa_receipt_creation');
                            	$mrkv_kasa_payment_order_statuses = get_option('mrkv_kasa_payment_order_statuses');
                            	$all_order_statuses = wc_get_order_statuses();

								foreach($enabled_gateways as $id => $gateway){
									?>
									<div class="mrkv_table-payment__body__line">
										<div class="mrkv_table-payment__body__checkbox">
											<input name="mrkv_kasa_receipt_creation[<?php echo esc_html($id); ?>]" id="mrkv_kasa_receipt_creation_<?php echo esc_html($id); ?>" type="checkbox" <?php 
												if(isset($ppo_skip_receipt_creation[$id]) && $ppo_skip_receipt_creation[$id] == 'on'){
													echo esc_html('checked');
												} ?>>
											<label for="mrkv_kasa_receipt_creation_<?php echo esc_html($id); ?>">
												<div class="mrkv_table-payment__body__checkbox__input">
													<span class="mrkv_kasa_slider"></span>
												</div>
												<?php echo esc_html($gateway->get_title()); ?></label>
										</div>
										<div class="mrkv_table-payment__body__type">
											<select name="mrkv_kasa_code_type_payment[<?php echo esc_html($id); ?>]" id="mrkv_kasa_code_type_payment_<?php echo esc_html($id); ?>" style="<?php 
												if(!isset($ppo_skip_receipt_creation[$id])){
													echo esc_html('opacity: .6;');
												} ?>">
												<?php 
													$index = 0;
													foreach($payment_types as $type){
														$selected = ( isset($ppo_payment_type[$id]) && ($index == $ppo_payment_type[$id]) ) ? 'selected' : '';
														echo '<option ' . esc_html($selected) . ' value="'. esc_html($index) . '">' . esc_html($type) . '</option>';
														++$index;
													}
												?>
												
											</select>
										</div>
										<div class="mrkv_table-payment__body__statuses" style="<?php 
												if(!isset($ppo_skip_receipt_creation[$id])){
													echo esc_html('opacity: .6;');
												} ?>">
											<select class="chosen chosen-select order-statuses" name="mrkv_kasa_payment_order_statuses[<?php echo esc_html($id); ?>][]" data-placeholder="<?php _e('Оберіть статуси замовлення', 'checkbox') ?>" multiple>
	                                            <?php
	                                            if (! empty($all_order_statuses)) :
	                                                foreach ($all_order_statuses as $k => $v) :
	                                                    $k = str_replace('wc-', '', $k);
	                                                    ?>
	                                                <option value="<?php echo esc_html($k); ?>" <?php echo ( isset($mrkv_kasa_payment_order_statuses[$id]) && in_array($k, $mrkv_kasa_payment_order_statuses[$id]) ) ? esc_html('selected') : ''; ?>><?php echo esc_html($v); ?></option>
	                                                    <?php
	                                                endforeach;
	                                            else :
	                                                printf('<option value="">%s</option>', __('None'));
	                                            endif;
	                                            ?>
	                                        </select>
										</div>
									</div>
									<hr>
									<?php
								}
							?>
						</div>
					</div>
					<h2 class="mt-40"><?php echo __('Додаткові налаштування', 'mrkv-vchasno-kasa'); ?></h2>
					<hr>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Надсилати чеки по SMS (платна опція у Вчасно)', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_phone" <?php echo ($mrkv_kasa_phone) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Включати вартість доставки у чеку', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_shipping_price" <?php echo ($mrkv_kasa_shipping_price) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Пропускати товари з нульовою ціною', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_skip_zero_product" <?php echo ($mrkv_kasa_skip_zero_product) ? esc_html('checked') : ''; ?> />
					</div>
					<?php echo submit_button(__('Зберегти', 'mrkv-vchasno-kasa')); ?>
				</div>
				<div class="columns-half__column">
					<h2><?php echo __('Тестовий режим', 'mrkv-vchasno-kasa'); ?></h2>
					<hr>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Увімкнути тестовий режим', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="checkbox" name="mrkv_kasa_test_enabled" <?php echo ($test_is_active) ? esc_html('checked') : ''; ?> />
					</div>
					<div class="line-form">
						<p class="line-form__title"><?php esc_html_e('Тестовий токен', 'mrkv-vchasno-kasa'); ?></p>
						<input class="table_input" type="text" name="mrkv_kasa_test_token" value="<?php echo esc_html(get_option('mrkv_kasa_test_token')); ?>" />
					</div>
					<div class="line-form">
						<h2 class="line-form__title mt-40"><?php esc_html_e('Лог', 'mrkv-vchasno-kasa'); ?></h2>
						<hr>
						<pre class="mrkv_full-log"><?php echo print_r($debug_log, 1); ?></pre>
					</div>
					<div class="line-form clear-line">
						<div class="mrkv_clean-all-log"><?php echo __('Очистити Лог', 'mrkv-vchasno-kasa');?></div>
						<span><?php echo __('*Файл логу автоматично очищується кожні 30 днів', 'mrkv-vchasno-kasa'); ?></span>
					</div>
				</div>
				
			</div>
			
		</form>
		<div class="plugin-development mt-40">
			<span><?php echo __('Веб студія', 'mrkv-vchasno-kasa'); ?></span>
			<a href="https://morkva.co.ua/" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/imgs/morkva-logo.svg'; ?>" alt="Morkva" title="Morkva"></a>
		</div>
	</div>
</div>

<script>
	jQuery(document).ready( function($){
		jQuery('.mrkv_clean-all-log').click(function(){
			jQuery.ajax({
		    url: '<?php echo admin_url('admin-ajax.php'); ?>',
		    type: 'POST',
		    data:{ 
		      action: 'clearlog',
		    },
		    success: function( data ){
		      jQuery('.mrkv_full-log').text('');
		    }
		  });
		});
		jQuery(".chosen-select").chosen({no_results_text: "Не задано статуси!"}); 
		jQuery('.mrkv_table-payment__body__checkbox input').change(function(){
			if(this.checked) {
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__type select').css('opacity', '1');
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__statuses').css('opacity', '1');
			}
			else{
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__type select').css('opacity', '.6');
				jQuery(this).closest('.mrkv_table-payment__body__line').find('.mrkv_table-payment__body__statuses').css('opacity', '.6');
			}
		});
	});
</script>

