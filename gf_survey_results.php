<?php
/*
    Plugin Name: Gravity Forms Survey Results
    Plugin URI: http://webpresencepartners.com/2012/06/19/gravity-forms-add-on-survey-results/
    Description: A Gravity Forms add-on that aggregates entries into handy charts and tables. Uses Raphael to render charts.
    Version: 0.1.1
    Author: Daniel Grundel (dgrundel) and Mahmoud Kassassir (mkassassir), Web Presence Partners
    Author URI: http://www.webpresencepartners.com
    Text Domain: gf_survey_results
    Domain Path: /languages/
*/

add_action( 'init', 'gf_survey_results_translations' );
/**
 * Load translations for the plugin. Text Domain: 'gf_survey_results'
 */
function gf_survey_results_translations() {
	
	load_plugin_textdomain( 'gf_survey_results', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	
}


	function gf_survey_results_display() {
	    global $wpdb;
		
		if(!class_exists("GFEntryDetail"))
			require_once(dirname(__FILE__)."/../gravityforms/entry_detail.php");
		if(!class_exists("RGFormsModel"))
			require_once(dirname(__FILE__)."/../gravityforms/forms_model.php");
		if(!class_exists("GFCommon"))
			require_once(dirname(__FILE__)."/../gravityforms/common.php");
		
		$forms = RGFormsModel::get_forms(null, "title");
		
		?>
		
		<script type="text/javascript" src="<?php echo WP_PLUGIN_URL."/".basename(dirname(__FILE__))."/js/raphael.js"; ?>"></script>
		<script type="text/javascript" src="<?php echo WP_PLUGIN_URL."/".basename(dirname(__FILE__))."/js/g.raphael-min.js"; ?>"></script>
		<script type="text/javascript" src="<?php echo WP_PLUGIN_URL."/".basename(dirname(__FILE__))."/js/g.pie-min.js"; ?>"></script>
		<style type="text/css">
			.gf_survey_result_form_select {
				padding: 6px 10px;
				border: 1px solid #DFDFDF;
				background-color: #EFEFEF;
				margin: 10px 0;
				-webkit-border-radius: 3px;
				-moz-border-radius: 3px;
				border-radius: 3px;
			}
			.gf_survey_result_field .hndle {
				font-size: 15px;
				font-weight: normal;
				padding: 7px 10px;
				margin: 0;
				line-height: 1;
				cursor: default !important;
			}
			.gf_survey_result_value_table {
				/*border-collapse: collapse;*/
				width: auto !important;
			}
			.gf_survey_result_value_table td,
			.gf_survey_result_value_table th {
				padding: 4px 12px !important;
				/*border: 1px solid #666;*/
			}
			.gf_survey_result_value_table .count,
			.gf_survey_result_value_table .percentage { text-align: right; }
			.gf_survey_result_graph {
				display: block;
				width: 600px;
				height: 240px;
			}
			.gf_survey_result_field_clear { clear: both; }
		</style>
		
		<?php
		$form_id = absint($_REQUEST['form_id'] ? $_REQUEST['form_id'] : $forms[0]->id);
		$form_meta = RGFormsModel::get_form_meta($form_id);
		
		//$q = "SELECT * FROM ".$wpdb->prefix."rg_lead_detail_long WHERE lead_detail_id IN ( SELECT id FROM ".$wpdb->prefix."rg_lead_detail WHERE form_id = {$form_id} )";
		$q = "SELECT * FROM {$wpdb->prefix}rg_lead_detail_long
		    INNER JOIN {$wpdb->prefix}rg_lead_detail
			ON {$wpdb->prefix}rg_lead_detail_long.lead_detail_id = {$wpdb->prefix}rg_lead_detail.id
		    INNER JOIN {$wpdb->prefix}rg_lead
			ON {$wpdb->prefix}rg_lead_detail.lead_id = {$wpdb->prefix}rg_lead.id
		    WHERE {$wpdb->prefix}rg_lead.status = 'active'
		    AND {$wpdb->prefix}rg_lead_detail.form_id = {$form_id}";
		$values = $wpdb->get_results($q);
		$long_values = array();
		foreach ($values as $value){ $long_values[$value->lead_detail_id] = $value->value; }
		
		?>
		
		<div class="wrap">
			
			<?php if(sizeof($forms)): ?>
			
				<h2><?php echo $form_meta['title']; ?></h2>
			
				<form class="gf_survey_result_form_select">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>" />
					<label for="form_id"><?php _e( 'Select a Form', 'gf_survey_results' ); ?>:</label>
					<select name="form_id" id="form_id" onchange="javascript:form.submit();">
						<?php foreach($forms as $form): ?>
							<option value="<?php echo $form->id; ?>" <?php if($_REQUEST['form_id'] == $form->id) echo 'selected="selected"'; ?>><?php echo $form->title; ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit"><?php _e( 'Select', 'gf_survey_results' ); ?></button>
				</form>
			
			<?php else: ?>
				
				<div style="margin:50px 0 0 10px;">
					<?php echo sprintf( __( 'You don\'t have any active forms. Let\'s go %screate one%s', 'gf_survey_results' ), '<a href="?page=gf_new_form">', '</a>' ); ?>
				</div>
				
			<?php endif; ?>
			
			<?php
			
			if(is_array($form_meta["fields"])) {
			
				foreach($form_meta["fields"] as $field){
					//var_dump($field["type"]);
					$field_id = $field["id"];
					
					//hide these field types
					switch($field['type']) {
						case "name":
						case "phone":
						case "email":
						case "page":
							continue(2);
					}
					
					echo "<div class=\"gf_survey_result_field postbox\">";
					
					//set options and display headers
					switch($field['type']) {
						case "section":
							echo "<h3 class=\"hndle\">".$field["label"]."</h3>";
							$show_graph = false;
							break;
						case "textarea":
							echo "<h3 class=\"hndle\">".$field["label"]."</h3>";
							$show_graph = false;
							break;
						default:
							echo "<h3 class=\"hndle\">".$field["label"]."</h3>";
							$show_graph = true;
					}
					
					//$q = "SELECT id, value, count(*) as value_count
					//	FROM ".$wpdb->prefix."rg_lead_detail WHERE form_id = {$form_id} and FLOOR(field_number) = {$field_id}
					//	GROUP BY value
					//	ORDER BY count(*) DESC";
					$q = "SELECT {$wpdb->prefix}rg_lead_detail.id, {$wpdb->prefix}rg_lead_detail.value as value, count(*) as value_count
					    FROM {$wpdb->prefix}rg_lead_detail
					    INNER JOIN {$wpdb->prefix}rg_lead
						ON {$wpdb->prefix}rg_lead_detail.lead_id = {$wpdb->prefix}rg_lead.id
					    WHERE {$wpdb->prefix}rg_lead.status = 'active'
					    AND {$wpdb->prefix}rg_lead_detail.form_id = {$form_id}
					    AND FLOOR(field_number) = {$field_id}
					    GROUP BY value
					    ORDER BY count(*) DESC";
					$values = $wpdb->get_results($q);
					
					$entry_count = 0;
					foreach ($values as $value) { $entry_count += (int) $value->value_count; }
					
					if($entry_count):
						echo "<div class=\"inside\">";
						
						$graph_id = "gf_survey_result_graph_{$field_id}"; ?>
						
						<?php if($show_graph): ?><div class="gf_survey_result_graph" id="<?php echo $graph_id; ?>"></div><?php endif; ?>
						<table class="gf_survey_result_value_table widefat">
						<thead><tr><th><?php _e( 'Value', 'gf_survey_results' ); ?></th><th><?php _e( 'Count', 'gf_survey_results' ); ?></th><th><?php _e( 'Percentage', 'gf_survey_results' ); ?></th></tr></thead>
						<tbody>
						<?php
						$graph_data = array();
						$graph_labels = array();
						foreach ($values as $value):
							$graph_data[] = $value->value_count;
							$graph_labels[] = addslashes(htmlspecialchars($value->value));
							$percentage = number_format((($value->value_count/$entry_count) * 100.00),2); ?>
							<tr>
								<td class="value"><?php
									if(array_key_exists($value->id, $long_values)) {
										echo nl2br(htmlspecialchars($long_values[$value->id]));
									} else {
										echo nl2br(htmlspecialchars($value->value));
									}
								?></td>
								<td class="count"><?php echo $value->value_count; ?></td>
								<td class="percentage"><?php echo $percentage; ?>%</td>
							</tr>
						<?php endforeach; ?>
						</tbody></table>
						
						<?php if($show_graph): ?>
							<script type="text/javascript">
								var r = Raphael("<?php echo $graph_id; ?>"),
								pie = r.piechart(120, 120, 100,
									[<?php echo implode(',', $graph_data); ?>],
									{
										legend: ["<?php echo implode('","', $graph_labels); ?>"], legendpos: "east"
									}
								);
								pie.hover(function () {
									this.sector.stop();
									this.sector.scale(1.1, 1.1, this.cx, this.cy);
				
									if (this.label) {
										this.label[0].stop();
										this.label[0].attr({ r: 7.5 });
										this.label[1].attr({ "font-weight": 800 });
									}
								}, function () {
									this.sector.animate({ transform: 's1 1 ' + this.cx + ' ' + this.cy }, 500, "bounce");
				
									if (this.label) {
										this.label[0].animate({ r: 5 }, 500, "bounce");
										this.label[1].attr({ "font-weight": 400 });
									}
								});
							</script>
						<?php endif;
						
						echo "</div>"; // .inside
					endif;
					
					echo "<div class=\"gf_survey_result_field_clear\"></div>
						</div>"; // .gf_survey_result_field
				}
				
			}
			
		echo "</div>"; // .wrap
	}
	
	function gf_survey_results_admin_menu($menu_items){
		$menu_items[] = array("name" => "gf_survey_results", "label" => __( 'Survey Results', 'gf_survey_results' ), "callback" => "gf_survey_results_display", "permission" => "edit_posts");
		return $menu_items;
	}
	add_filter("gform_addon_navigation", "gf_survey_results_admin_menu");