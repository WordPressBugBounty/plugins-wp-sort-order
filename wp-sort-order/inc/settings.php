<?php if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Unauthorized.', 'wpso-sort-order' ), '', array( 'response' => 403 ) );
}
global $wpdb, $wpso_data, $wpso_pro, $wpso_premium_link, $premium_click, $wpso_allowed_pages, $premium_tags_list;
$wpso_pro      = wpso_is_pro();
$premium_click = $wpso_pro ? '' : '<small class="premium">(' . esc_html__( 'Premium Feature', 'wpso-sort-order' ) . ')</small>';

$wpso_options = get_option( 'wpso_options' );
$wpso_options = is_array( $wpso_options ) ? $wpso_options : array();
$wpso_objects = ( isset( $wpso_options['objects'] ) && is_array( $wpso_options['objects'] ) ) ? $wpso_options['objects'] : array();
$wpso_tags = ( isset( $wpso_options['tags'] ) && is_array( $wpso_options['tags'] ) ) ? $wpso_options['tags'] : array();
$wpso_extras = ( isset( $wpso_options['extras'] ) && is_array( $wpso_options['extras'] ) ) ? $wpso_options['extras'] : array();
$term_order_exists = $wpdb->query( "DESCRIBE $wpdb->terms `term_order`" );
?>

<div class="wrap wpso">

<?php //screen_icon( 'plugins' ); ?>

<h2><?php echo esc_html( $wpso_data['Name'] ) . ' (' . esc_html( $wpso_data['Version'] ) . ( $wpso_pro ? ') ' . esc_html__( 'Pro', 'wpso-sort-order' ) : ')' ); ?> - <?php echo esc_html__( 'Settings', 'wpso-sort-order' ); ?> <?php if ( ! $wpso_pro ) : ?><a href="<?php echo esc_url( $wpso_premium_link ); ?>" target="_blank" rel="noopener noreferrer" class="premium"><?php echo esc_html__( 'Go Premium', 'wpso-sort-order' ); ?></a><?php endif; ?></h2>

    <h2 class="nav-tab-wrapper">

        <a class="nav-tab nav-tab-active"><?php echo esc_html__( 'General', 'wpso-sort-order' ); ?> <i class="far fa-check-square"></i></a>
        <a class="nav-tab"><?php echo esc_html__( 'Premium', 'wpso-sort-order' ); ?> <i class="far fa-star"></i></a>
        <a class="nav-tab" data-tab="help" data-type="free" style="float:right"><i class="far fa-question-circle"></i>&nbsp;<?php echo esc_html__( 'Help', 'wpso-sort-order' ); ?></a>

    </h2>
    
<?php if ( 'update' === wpso_get( 'msg' ) ) : ?>
<div id="message" class="updated below-h2">
		<p><?php echo esc_html__( 'Settings saved.', 'wpso-sort-order' ); ?></p>
</div>
<?php endif; ?>

<div class="nav-tab-content">
<form method="post">

<?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'nonce_wpso' ); ?>

<div class="more_features">

</div>
<div id="wpso_select_extras">
<label class="clickable"><input type="checkbox" id="wpso_allcheck_extras" class="hide" /> <?php echo esc_html__( 'Check/Uncheck', 'wpso-sort-order' ); ?></label>
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row"><h4><?php echo esc_html__( 'Pages', 'wpso-sort-order' ); ?></h4><small>menu_order</small></th>
			<td class="options1">
            <ul>
<?php
	if(!empty($wpso_allowed_pages)){
		foreach($wpso_allowed_pages as $page=>$ptitle){
?>			            

                    <li>
                    <label><input type="checkbox" name="extras[]" value="<?php echo esc_attr( $page ); ?>" <?php if ( isset( $wpso_extras ) && is_array( $wpso_extras ) ) { if ( in_array( $page, $wpso_extras ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo esc_html( $ptitle ); ?></label>
                    </li>
<?php
		}
	}
?>	
					
            </ul>
			</td>
		</tr>
	</tbody>
</table>
</div>
<div id="wpso_select_objects">
<label class="clickable"><input type="checkbox" id="wpso_allcheck_objects" class="hide" /> <?php echo esc_html__( 'Check/Uncheck', 'wpso-sort-order' ); ?></label>
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row"><h4><?php echo esc_html__( 'Post Types', 'wpso-sort-order' ); ?></h4><small>menu_order</small></th>
			<td class="options1">
            <ul>
			<?php
				$post_types = get_post_types( array (
					'show_ui' => true,
					'show_in_menu' => true,
				), 'objects' );
				
				$post_array = array();
				
				foreach ( $post_types  as $post_type ) {
					if ( $post_type->name == 'attachment' ) continue;
					$post_array[$post_type->name] = $post_type;
				}
		
            
				if(!empty($post_array)){
					ksort($post_array);
					foreach($post_array as $post_type){
				
			?>
            					<li>
                    
                    <label><input type="checkbox" name="objects[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php if ( isset( $wpso_objects ) && is_array( $wpso_objects ) ) { if ( in_array( $post_type->name, $wpso_objects ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo esc_html( $post_type->label ); ?></label>
                    
                    
                    </li>			<?php
					}
				}
			
			?>                
            </ul>
			</td>
		</tr>
	</tbody>
</table>

</div>



<div id="wpso_select_tags">
<label class="clickable"><input class="hide" type="checkbox" id="wpso_allcheck_tags"> <?php echo esc_html__( 'Check/Uncheck', 'wpso-sort-order' ); ?></label>
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row"><h4><?php echo esc_html__( 'Taxonomies', 'wpso-sort-order' ); ?></h4><small>term_order<?php if(!empty($term_order_exists)){ ?> <i title=\"<?php echo esc_attr__( 'term_order field added to terms database table correctly!', 'wpso-sort-order' ); ?>\" class="fas fa-check-circle"></i><?php } ?></small></th>
			<td class="options2">
            <ul>
			<?php
				$taxonomies = get_taxonomies( array(
					'show_ui' => true,
				), 'objects' );
				
				$tax_array = array();
				foreach( $taxonomies as $taxonomy ) {
					if ( $taxonomy->name == 'post_format' ) continue;
					$tax_array[$taxonomy->name] = $taxonomy;
				}
				
				if(!empty($tax_array)){
					ksort($tax_array);
					foreach($tax_array as $taxonomy){
				
			?>
                                <li><label><input type="checkbox" name="tags[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php if ( isset( $wpso_tags ) && is_array( $wpso_tags ) ) { if ( in_array( $taxonomy->name, $wpso_tags ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo esc_html( $taxonomy->label ); ?></label></li>
			<?php
					}
				}
			
			?>                                


            	<?php if(!$wpso_pro): ?>
                <li><label><input disabled="disabled" type="checkbox">&nbsp;<?php echo esc_html__( 'User Categories', 'wpso-sort-order' ); ?></label>&nbsp;<?php //echo $premium_click; ?></li>
                <?php endif; ?>
            </ul>
			</td>
		</tr>
        <tr>
        <th></th>
        <td><p class="submit">
	<input type="submit" class="button-primary" name="wpso_submit" value="<?php echo esc_html__( 'Save Changes', 'wpso-sort-order' ); ?>">
</p></td>
        </tr>
	</tbody>
</table>

</div>
</form>
</div>




<div class="nav-tab-content hide premium_content">



        <form method="post">

            <?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'nonce_wpso' ); ?>
            <input type="hidden" name="wpso_tn" value="<?php echo esc_attr( absint( wpso_get( 't', '0' ) ) ); ?>"/>

            <div class="accordion_wrapper">

                <button class="accordion first"><?php echo esc_html__( 'Users List Shortcode', 'wpso-sort-order' ); ?> <span class="dashicons dashicons-plus plus"></span><span class="dashicons dashicons-minus minus"></span></button>
                <div class="panel first">
                    <div>

                        <div class="more_features">
                            <ul>
                                <?php if(!$wpso_pro): ?>
                                    <li>
                                        <label><?php echo esc_html__( 'User categories required?', 'wpso-sort-order' ); ?></label><?php echo $premium_click; ?>
                                        <br />
                                        <br />
                                    </li>
                                <?php endif; ?>
                                <li>
                                    <label><?php echo esc_html__( 'A shortcode to list users under taxonomy, terms and children.', 'wpso-sort-order' ); ?> <span>[</span>WPSO_USERS <span>slug="</span><?php echo __('taxonomy or term slug', 'wpso-sort-order').'<span>"</span> <span>id="</span>'.__('taxonomy or term id', 'wpso-sort-order'); ?><span>"]</span><?php echo $premium_click; ?></label>
                                </li>
                            </ul>
                        </div>

                        <br />
                        <br />
                        <br />
                        <br />

                    </div>
                </div>

                <button class="accordion"><?php echo esc_html__( 'User Categories', 'wpso-sort-order' ); ?> <span class="dashicons dashicons-plus plus"></span><span class="dashicons dashicons-minus minus"></span></button>
                <div class="panel">


                    <div id="wpso_select_premium_tags">
                        <label class="clickable"><input class="hide" type="checkbox" id="wpso_allcheck_premium_tags"> <?php echo esc_html__( 'Check/Uncheck', 'wpso-sort-order' ); ?></label>
                        <table class="form-table">
                            <tbody>
                            <tr valign="top">
                                <th scope="row"><h4><?php echo esc_html__( 'Taxonomies', 'wpso-sort-order' ); ?></h4><small>term_order</small></th>
                                <td class="options2">
                                    <ul>
                                        <?php
                                        $taxonomies = get_taxonomies( array(
                                            'show_ui' => true,
                                        ), 'objects' );

                                        $tax_array = array();
                                        foreach( $taxonomies as $taxonomy ) {
                                            if ( $taxonomy->name == 'post_format' || !in_array($taxonomy->name , $premium_tags_list)) continue;
                                            $tax_array[$taxonomy->name] = $taxonomy;
                                        }


                                        if(!empty($tax_array)){
                                            ksort($tax_array);
                                            foreach($tax_array as $taxonomy){

                                                ?>
                                                <li><label><input type="checkbox" name="tags[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php if ( isset( $wpso_tags ) && is_array( $wpso_tags ) ) { if ( in_array( $taxonomy->name, $wpso_tags ) ) { echo 'checked="checked"'; } } ?>>&nbsp;<?php echo esc_html( $taxonomy->label ); ?></label></li>
                                                <?php
                                            }
                                        }

                                        ?>


                                        <?php if(!$wpso_pro): ?>
                                            <li><label><input disabled="disabled" type="checkbox">&nbsp;<?php echo esc_html__( 'User Categories', 'wpso-sort-order' ); ?></label>&nbsp;<?php echo $premium_click; ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th></th>
                                <td><p class="submit">
                                        <input type="submit" class="button-primary" name="wpso_premium_submit" value="<?php echo esc_html__( 'Save Changes', 'wpso-sort-order' ); ?>">
                                    </p></td>
                            </tr>
                            </tbody>
                        </table>

                    </div>

                    <br />
                    <br />
                    <br />
                    <br />

                </div>


            </div>







        </form>
        <?php
        if ( $wpso_pro ) {
            /**
             * Pro add-on renders its own accordion panels here (button.accordion + div.panel).
             */
            do_action( 'wpso_premium_panels' );
        } else {
            ?>
            <div class="accordion_wrapper wpso-premium-teaser">
                <button class="accordion" type="button"><?php echo esc_html__( 'Premium features', 'wpso-sort-order' ); ?> <span class="dashicons dashicons-plus plus"></span><span class="dashicons dashicons-minus minus"></span></button>
                <div class="panel">
                    <ul style="list-style:disc;margin:18px 0 18px 24px;">
                        <li><?php echo esc_html__( 'Order history with one-click undo', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'Backup, export and import of every sort order (JSON)', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'Quick reset: alphabetical, newest, oldest or reversed', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'Let editors and other roles drag & drop what they can edit', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'Order column in post and term list tables', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'Shortcodes to list posts and terms in your sorted order', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'REST API endpoints for headless sites', 'wpso-sort-order' ); ?></li>
                        <li><?php echo esc_html__( 'User Categories and the WPSO_USERS shortcode', 'wpso-sort-order' ); ?></li>
                    </ul>
                    <p><a class="button button-primary" href="<?php echo esc_url( $wpso_premium_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Go Premium', 'wpso-sort-order' ); ?></a></p>
                    <br />
                </div>
            </div>
            <?php
        }
        ?>


    </div>

	
<div class="nav-tab-content container-fluid hide mt-2" data-content="help">

        <div class="row mt-3">
        	<ul class="position-relative">
            	<li class="mb-4"><a class="btn btn-sm btn-info" href="https://wordpress.org/support/plugin/wp-sort-order/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open a Ticket on Support Forums', 'wpso-sort-order' ); ?></a></li>
                <li class="mb-4"><a class="btn btn-sm btn-warning mr-2" href="https://demo.androidbubble.com/contact/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Contact Developer', 'wpso-sort-order' ); ?></a><i class="fas fa-headset"></i></li>
                <li><iframe width="560" height="315" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" title="WP Sort Order" src="https://www.youtube-nocookie.com/embed/4ZiHUSBDJwY" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></li>
			</ul>                
		</div>

    </div>

</div>

<script type="text/javascript" language="javascript">
jQuery(document).ready(function($){



<?php if ( '' !== wpso_get( 't' ) ) :


	$wpso_tn = absint( wpso_get( 't', '0' ) );

	?>
		setTimeout(function(){

			$('.nav-tab-wrapper .nav-tab:nth-child(<?php echo $wpso_tn + 1; ?>)').click();

		}, 1);

<?php endif; ?>

});


</script>
<style type="text/css">
.update-nag, #wpfooter,
#message{
	display:none;
}
</style>