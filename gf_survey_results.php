<?php
/*
    Plugin Name: Gravity Forms Survey Results
    Plugin URI: http://webpresencepartners.com/2012/06/19/gravity-forms-add-on-survey-results/
    Description: A Gravity Forms add-on that aggregates entries into handy charts and tables. Uses Chart.js to render charts.
    Version: 0.1.2
    Author: Daniel Grundel (dgrundel) and Mahmoud Kassassir (mkassassir), Web Presence Partners.  New chart functionality by Gaelan Lloyd.
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
	
	<script type="text/javascript" src="<?php echo WP_PLUGIN_URL."/".basename(dirname(__FILE__))."/js/chart.js"; ?>"></script>

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

		td.count,
		td.percentage { font-family: monospace; }

		.chartjs-pie, .chartjs-bar-label { display: none; }

		canvas { margin: 1em; }

	</style>
	
	<?php
	$form_id = absint($_REQUEST['form_id'] ? $_REQUEST['form_id'] : $forms[0]->id);
	$form_meta = RGFormsModel::get_form_meta($form_id);

	// set up the pie chart colors
	$pieColor = array(
		"rgba(47,105,191,1)",
		"rgba(162,191,47,1)",
		"rgba(191,90,47,1)",
		"rgba(191,162,47,1)",
		"rgba(119,47,191,1)",
		"rgba(191,47,47,1)",
		"rgba(0,50,127,1)",
		"rgba(131,151,48,1)",
		"rgba(145,68,35,1)",
		"rgba(147,127,45,1)",		// 10
		"rgba(128,128,128,1)",		// use grey for values 10-15
		"rgba(128,128,128,1)",
		"rgba(128,128,128,1)",
		"rgba(128,128,128,1)",
		"rgba(128,128,128,1)"
	);

	$pieColorHighlight = array(
		"rgba(47,105,191,0.5)",
		"rgba(162,191,47,0.5)",
		"rgba(191,90,47,0.5)",
		"rgba(191,162,47,0.5)",
		"rgba(119,47,191,0.5)",
		"rgba(191,47,47,0.5)",
		"rgba(0,50,127,0.5)",
		"rgba(131,151,48,0.5)",
		"rgba(145,68,35,0.5)",
		"rgba(147,127,45,0.5)",			// 10
		"rgba(128,128,128,0.5)",		// use grey for values 10-15
		"rgba(128,128,128,0.5)",
		"rgba(128,128,128,0.5)",
		"rgba(128,128,128,0.5)",
		"rgba(128,128,128,0.5)"
	);
	
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
					case "html":
						continue(2);
				}

				switch(strtolower($field['label'])) {
					case "first name":
					case "last name":
					case "email":
					case "phone number":						
					case "phone":						
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

				// hide the graph when there are more than 15 values
				if(count($values) > 15) { $show_graph = false; }

				if($entry_count):
					echo "<div class=\"inside\">";
					$graph_id = "gf_survey_result_graph_{$field_id}";
				?>
					
					<?php if($show_graph) { ?>
						<p>Show: <a href="javascript:void(0)" class="showBarValue">Bar (sorted by value)</a> | <a href="javascript:void(0)" class="showBarLabel">Bar (sorted by label)</a> | <a href="javascript:void(0)" class="showPie">Pie</a></p>
						<canvas id="chartjs-bar-value-<?php echo $graph_id; ?>" class="chartjs-bar-value" width="750" height="400"></canvas>
						<canvas id="chartjs-bar-label-<?php echo $graph_id; ?>" class="chartjs-bar-label" width="750" height="400"></canvas>
						<canvas id="chartjs-pie-<?php echo $graph_id; ?>" class="chartjs-pie" width="400" height="400"></canvas>
					<?php } ?>

					<table class="gf_survey_result_value_table widefat">
						<thead>
							<tr>
								<th><?php _e( 'Value', 'gf_survey_results' ); ?></th>
								<th><?php _e( 'Count', 'gf_survey_results' ); ?></th>
								<th><?php _e( 'Percentage', 'gf_survey_results' ); ?></th>
							</tr>
						</thead>
					<tbody>
					<?php

					// empty the data storage arrays
					$graph_data = array();
					$graph_labels = array();
					$dataPie = array();
					$dataBarLabel = array();

					// populate the data storage arrays

					foreach ($values as $value) {

						// set up the reference variables
						$thisValue = $value->value_count;
						$thisLabel = addslashes(htmlspecialchars($value->value));

						// build the pie chart data structure
						$dataPie[] = array(
							"value"     => $thisValue,
							"label"     => $thisLabel
						);

						// build the bar chart (sorted by value) data structures
						$graph_data[] = $thisValue;
						$graph_labels[] = $thisLabel;

						// build the bar chart (sorted by label) data structure
						$dataBarLabel[] = array(
							'label' => $thisLabel,
							'value' => $thisValue
						);

						$percentage = number_format((($value->value_count/$entry_count) * 100.00),2);
					?>

						<tr>
							<td class="value">
								<?php
								if(array_key_exists($value->id, $long_values)) {
									echo nl2br(htmlspecialchars($long_values[$value->id]));
								} else {
									echo nl2br(htmlspecialchars($value->value));
								}
								?>
							</td>
							<td class="count"><?php echo $value->value_count; ?></td>
							<td class="percentage"><?php echo $percentage; ?>%</td>
						</tr>

					<?php

					}

					// sort the dataBarLabel array by the label key
					// use natural-order sorting to handle numeric and alphabetical data properly
					usort($dataBarLabel, 'gf_sort_natural');

					?>
					</tbody></table>
					
					<?php if($show_graph) { ?>

						<?php /* CHART.JS */ ?>
						<script>
							// Get the context of the canvas element we want to select
							var ctxBarValue = document.getElementById("chartjs-bar-value-<?php echo $graph_id; ?>").getContext("2d");
							var ctxBarLabel = document.getElementById("chartjs-bar-label-<?php echo $graph_id; ?>").getContext("2d");
							var ctxPie = document.getElementById("chartjs-pie-<?php echo $graph_id; ?>").getContext("2d");

							// expose the total number of values for charting
							var resultCount = <?php echo count($values); ?>;

							// write the bar chart data structure (sorted by value)
							var dataBarValue = {
								labels: ["<?php echo implode('","', $graph_labels); ?>"],
								datasets: [
									{
										label: "First range",
										fillColor: "rgba(0,102,170,0.5)",
										strokeColor: "rgba(0,102,170,0.8)",
							            highlightFill: "rgba(0,102,170,0.75)",
							            highlightStroke: "rgba(0,102,170,1)",
							            data: [<?php echo implode(',', $graph_data); ?>]
									}
								]
							};

							// write the bar chart data structure (sorted by label)
							var dataBarLabel = {
								labels: [<?php

									foreach( $dataBarLabel as $i=>$item ) {
										echo '"' . $item['label'] . '"';
										// add a comma, except on the last element
										if ( end( array_keys($dataBarLabel) ) != $i ) {
											echo ',';
										}
									}

								?>],
								datasets: [
									{
										label: "First range",
										fillColor: "rgba(0,102,170,0.5)",
										strokeColor: "rgba(0,102,170,0.8)",
							            highlightFill: "rgba(0,102,170,0.75)",
							            highlightStroke: "rgba(0,102,170,1)",
							            data: [<?php

											foreach( $dataBarLabel as $i=>$item ) {
												echo $item['value'];
												// add a comma, except on the last element
												if ( end( array_keys($dataBarLabel) ) != $i ) {
													echo ',';
												}
											}

							            ?>]
									}
								]
							};

							// build the data structure for the label-sorted array
							<?php
								/* DEBUG */
								/*
								echo '$graph_labels = [';
								print_r($graph_labels);
								echo ']';

								echo '$graph_data = [';
								print_r($graph_data);
								echo ']';

								echo '$dataBarLabel = [';
								print_r($dataBarLabel);
								echo ']';
								*/
							?>

							var dataPie = [];

							// empty the array
							dataPie.length = 0;

							<?php

								// write the javascript data structure
								for ($i = 0; $i < count($values); $i++) {
									echo "dataPie.push({";
										echo "value: "       . $dataPie[$i]['value']     . ",";
										echo "color: \""     . $pieColor[$i]             . "\",";
										echo "highlight: \"" . $pieColorHighlight[$i]    . "\",";
										echo "label: \""     . $dataPie[$i]['label']     . "\"";
									echo "});";
								}
							?>

							var optionsBar = {
								animation : false,
								scaleBeginAtZero : true,
								scaleShowGridLines : true,
								scaleGridLineColor : "rgba(0,0,0,.1)",
								scaleGridLineWidth : 1,
								scaleShowHorizontalLines: true,
								scaleShowVerticalLines: false,
								barShowStroke : true,
								barStrokeWidth : 2,
								barValueSpacing : 10,
								barDatasetSpacing : 1,
								legendTemplate : "<ul class=\"<%=name.toLowerCase()%>-legend\"><% for (var i=0; i<datasets.length; i++){%><li><span style=\"background-color:<%=datasets[i].fillColor%>\"></span><%if(datasets[i].label){%><%=datasets[i].label%><%}%></li><%}%></ul>"
							};

							var optionsPie = {
								animation : false,
								scaleBeginAtZero : true,
								scaleShowGridLines : true,
								scaleGridLineColor : "rgba(0,0,0,.1)",
								scaleGridLineWidth : 1,
								scaleShowHorizontalLines: true,
								scaleShowVerticalLines: false,
								barShowStroke : true,
								barStrokeWidth : 2,
								barValueSpacing : 10,
								barDatasetSpacing : 1,
								legendTemplate : "<ul class=\"<%=name.toLowerCase()%>-legend\"><% for (var i=0; i<datasets.length; i++){%><li><span style=\"background-color:<%=datasets[i].fillColor%>\"></span><%if(datasets[i].label){%><%=datasets[i].label%><%}%></li><%}%></ul>"
							};

							var myChartBarLabel = new Chart(ctxBarLabel).Bar(dataBarLabel, optionsBar);
							var myChartBarValue = new Chart(ctxBarValue).Bar(dataBarValue, optionsBar);
							var myChartPie = new Chart(ctxPie).Doughnut(dataPie, optionsPie);

						</script>

						<script>
							// Add chart togglers
							jQuery(document).ready(function(){

								jQuery(".showPie").click(function(){
									jQuery(this).closest('.inside').find('.chartjs-pie').show();
									jQuery(this).closest('.inside').find('.chartjs-bar-label').hide();
									jQuery(this).closest('.inside').find('.chartjs-bar-value').hide();
								});

								jQuery(".showBarLabel").click(function(){
									jQuery(this).closest('.inside').find('.chartjs-bar-label').show();
									jQuery(this).closest('.inside').find('.chartjs-pie').hide();
									jQuery(this).closest('.inside').find('.chartjs-bar-value').hide();
								});

								jQuery(".showBarValue").click(function(){
									jQuery(this).closest('.inside').find('.chartjs-bar-value').show();
									jQuery(this).closest('.inside').find('.chartjs-pie').hide();
									jQuery(this).closest('.inside').find('.chartjs-bar-label').hide();
								});

							});
						</script>
					<?php }
					
					echo "</div>"; // .inside
				endif;
				
				echo "<div class=\"gf_survey_result_field_clear\"></div>
					</div>"; // .gf_survey_result_field
			}
			
		}
		
	echo "</div>"; // .wrap
}


function gf_survey_results_admin_menu($menu_items){
	$menu_items[] = array(
		"name"        => "gf_survey_results",
		"label"       => __( 'Survey Results', 'gf_survey_results' ),
		"callback"    => "gf_survey_results_display",
		"permission"  => "edit_posts"
	);
	return $menu_items;
}
add_filter("gform_addon_navigation", "gf_survey_results_admin_menu");

// this function is used to sort the data for the bar graph label set
function gf_sort_natural($a, $b) {
	return strnatcmp($a['label'], $b['label']);
}
