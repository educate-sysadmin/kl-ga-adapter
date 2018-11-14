<?php
/*
KL GA Adapter
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

// create custom plugin settings menu
add_action('admin_menu', 'kl_ga_plugin_create_menu');

function kl_ga_plugin_create_menu() {
	//create options page
	add_options_page('KL GA Adapter', 'KL GA Adapter', 'manage_options', __FILE__, 'kl_ga_plugin_settings_page' , __FILE__ );
	//call register settings function
	add_action( 'admin_init', 'register_kl_ga_plugin_settings' );
}

function register_kl_ga_plugin_settings() {	
	register_setting( 'kl_ga-plugin-settings-group', 'kl_ga_use_bundled_gaapi' );
	register_setting( 'kl_ga-plugin-settings-group', 'kl_ga_ApplicationName' );
	//register_setting( 'kl_ga-plugin-settings-group', 'kl_ga_DeveloperKey' );
	register_setting( 'kl_ga-plugin-settings-group', 'kl_ga_DeveloperKeyFileLocation' );
	register_setting( 'kl_ga-plugin-settings-group', 'kl_ga_ViewId' );
}

function kl_ga_plugin_settings_page() {
?>
<div class="wrap">
<h1>KL GA Adapter</h1>

<form method="post" action="options.php">
    <?php settings_fields( 'kl_ga-plugin-settings-group' ); ?>
    <?php do_settings_sections( 'kl_ga-plugin-settings-group' ); ?>
    <table class="form-table">
            
		<tr valign="top">
        <th scope="row">Use bundled GA API</th>
        <td>
        	<input type="checkbox" name="kl_ga_use_bundled_gaapi" <?php if (get_option('kl_ga_use_bundled_gaapi')) echo ' checked '; ?> size="60" />
        	<br/>
        	<small>Try use bundled GA API files</small>
        </td>
        </tr>            
            
		<tr valign="top">
        <th scope="row">ApplicationName</th>
        <td>
        	<input type="input" name="kl_ga_ApplicationName" value="<?php echo get_option('kl_ga_ApplicationName'); ?>" size="60" />
        	<br/>
        	<small>ApplicationName for GA API</small>
        </td>
        </tr>
        
		<tr valign="top">
        <th scope="row">DeveloperKey</th>
        <td>
        	<input type="input" name="kl_ga_DeveloperKeyFileLocation" value="<?php echo get_option('kl_ga_DeveloperKeyFileLocation'); ?>" size="60" />
        	<br/>
        	<small>DeveloperKeyFile location for GA API. If no preceeding slash, relative to plugin folder.</small>
        </td>
        </tr>
        
		<tr valign="top">
        <th scope="row">ViewId</th>
        <td>
        	<input type="input" name="kl_ga_ViewId" value="<?php echo get_option('kl_ga_ViewId'); ?>" size="60" />
        	<br/>
        	<small>GA View Id. </small>
        </td>
        </tr>        
        
	</table>

    <?php submit_button(); ?>    
    
</form>

<?php /* can't test without settings already entered
<div id = "kl-ga-test">
	<h1>Status/Test</h1>
	<?php
		echo KLGA::test()?"Ok":"Problem";
	?>
</div>
* */ ?>

</div>
<?php } ?>
