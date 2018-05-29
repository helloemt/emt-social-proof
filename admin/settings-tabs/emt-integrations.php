<?php
//pre( Emt_Social_Proof::$integrations );
if ( is_array( Emt_Social_Proof::$integrations ) && count( Emt_Social_Proof::$integrations ) > 0 ) {
	?>
	<div class="emt-integrations-settings">
		<div class="emt_tab">
			<?php
			$count = 1;
			foreach ( Emt_Social_Proof::$integrations as $integration_slug => $integration_instance ) {
				$active_class = ( 1 == $count ) ? 'active' : '';
				if ( isset( $integration_instance->has_settings ) && 1 == $integration_instance->has_settings ) {
					echo '<a href="javascript:void(0);" data-section="emt_' . $integration_instance->slug . '" class="emt_tablinks ' . $active_class . '">' . $integration_instance->integration_name . '</a>';
					$count ++;
				}
			}
			?>
		</div>

		<?php
		$count = 1;
		foreach ( Emt_Social_Proof::$integrations as $integration_slug => $integration_instance ) {
			$active_class = ( 1 == $count ) ? 'active' : '';
			if ( isset( $integration_instance->has_settings ) && 1 == $integration_instance->has_settings ) {
				?>
				<div id="emt_<?php echo $integration_instance->slug; ?>" class="emt_tabcontent <?php echo $active_class; ?>">
					<h4><?php echo $integration_instance->integration_name . ' Settings'; ?></h4>
					<?php echo $integration_instance->get_settings_page(); ?>
				</div>
				<?php
				$count ++;
			}
		}
		?>
	</div>
	<?php
} else {
	echo '<h2>No Supported Integrations Found On This Website</h2>';
}
?>
