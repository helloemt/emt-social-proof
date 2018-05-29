<?php
$excluded_products_option_key = 'emt_excluded_' . $this->slug;
$exluded_products             = get_option( $excluded_products_option_key );
if ( is_array( $exluded_products ) && count( $exluded_products ) > 0 ) {
	$exluded_products = Emt_Common::get_excluded_products( null, $this->search_posttype, $exluded_products );
}
?>
<div class="emt-form-gp">
	<label for="emt-exc-products-<?php echo $this->slug; ?>" class="emt-form-label">Exclude Products</label>
	<select id="emt-exc-products-<?php echo $this->slug; ?>" class="js-example-basic-multiple emt-select" name="products[]" multiple="multiple">
		<?php
		if ( is_array( $exluded_products ) && count( $exluded_products ) > 0 ) {
			foreach ( $exluded_products['results'] as $product_details ) {
				echo '<option value="' . $product_details['id'] . '" selected="selected">' . $product_details['text'] . '</option>';
			}
		}
		?>
	</select>
</div>
<div class="emt-form-gp emt-pos-abs">
	<input type="hidden" name="emt_integration_slug" value="<?php echo $this->slug; ?>" class="emt_integration_slug"/>
	<input type="hidden" name="emt_setting_type" value="integrations_settings" class="emt_setting_type"/>
	<input type="hidden" name="emt_posttype" value="<?php echo $this->search_posttype; ?>" class="emt_posttype"/>
	<input type="submit" value="Save" name="emt_save_button" class="button button-primary">
    <span class="spinner"></span>
</div>

