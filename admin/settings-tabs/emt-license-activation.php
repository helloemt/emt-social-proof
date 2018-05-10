<?php
wp_enqueue_script( 'media-upload' );
wp_enqueue_script( 'thickbox' );
wp_enqueue_style( 'thickbox' );
$all_domains       = get_option( 'emt_all_domains' );
$all_domains_count = ( is_array( $all_domains ) && count( $all_domains ) > 0 ) ? count( $all_domains ) : '1';
?>

<div class="emt-activation-section" data-count="<?php echo $all_domains_count; ?>">
	<?php
	if ( is_array( $all_domains ) && count( $all_domains ) > 0 ) {
		foreach ( $all_domains as $api_key => $api_secret_key ) {
			?>
			<div class="emt-repeator-element">
				<div class="emt-body-name emt-full-width">
					<div class="emt-width-30 emt-float-left">
						<label class="emt-label">API KEY <span class="emt-req">*</span>
							<input type="text" name="api_key" class="api_key emt-input" placeholder="Enter API Key" value="<?php echo $api_key; ?>">
						</label>
					</div>
					<div class="emt-width-30 emt-float-left">
						<label class="emt-label">API SECRET KEY <span class="emt-req">*</span>
							<input type="text" name="api_secret_key" class="api_secret_key emt-input" required placeholder="Enter API Secret Key" value="<?php echo $api_secret_key; ?>">
						</label>
					</div>
					<div class="emt-width-40 emt-float-left body-name-actions">
						<input type="button" name="sync" class="button button-primary emt-button emt-sync-site" value="Sync">
						<input type="button" name="disconnect" class="button button-primary emt-button emt-disconnect-site" value="Disconnect">
						<input type="button" name="remove" class="button emt-button emt-remove-site emt-minus" value="Remove Site">
						<span class="spinner"></span>
					</div>
				</div>
			</div>
			<?php
		}
	} else {
	?>
	<div class="emt-repeator-element">
		<div class="emt-body-name emt-full-width">
			<div class="emt-width-30 emt-float-left">
				<label class="emt-label">API KEY <span class="emt-req">*</span>
					<input type="text" name="api_key" class="api_key emt-input" placeholder="Enter API Key">
				</label>
			</div>
			<div class="emt-width-30 emt-float-left">
				<label class="emt-label">API SECRET KEY <span class="emt-req">*</span>
					<input type="text" name="api_secret_key" class="api_secret_key emt-input" required placeholder="Enter API Secret Key">
				</label>
			</div>
			<div class="emt-width-40 emt-float-left body-name-actions">
				<input type="button" name="connect" class="button button-primary emt-button emt-connect-site" value="Connect">
				<input type="button" name="remove" class="button emt-button emt-remove-site emt-minus" value="Remove Site">
				<span class="spinner"></span>
			</div>
		</div>
	</div>
</div>
<?php
	}
?>
</div>


<div class="emt-add-new-site-section">
	<input type="button" name="add_new_site" class="button button-primary emt-button add_new_site emt-plus" value="+ Add New Site">
</div>

<div class="emt-display-none emt-repeator-div">
	<div class="emt-repeator-element">
		<div class="emt-body-name emt-full-width">
			<div class="emt-width-30 emt-float-left">
				<label class="emt-label">API KEY <span class="emt-req">*</span>
					<input type="text" name="api_key" class="api_key emt-input" placeholder="Enter API Key">
				</label>
			</div>
			<div class="emt-width-30 emt-float-left">
				<label class="emt-label">API SECRET KEY <span class="emt-req">*</span>
					<input type="text" name="api_secret_key" class="api_secret_key emt-input" required placeholder="Enter API Secret Key">
				</label>
			</div>
			<div class="emt-width-40 emt-float-left body-name-actions">
				<input type="button" name="connect" class="button button-primary emt-button emt-connect-site" value="Connect">
				<input type="button" name="remove" class="button emt-button emt-remove-site emt-minus" value="Remove Site">
				<span class="spinner"></span>
			</div>
		</div>
	</div>
</div>
</div>
