<?php
// Include & setup custom metabox and fields

add_filter( 'cmb_meta_boxes', 'calendar_metaboxes' );
function calendar_metaboxes( $meta_boxes ) {
	$prefix = '_cmb_'; // start with an underscore to hide fields from custom fields list
	$meta_boxes[] = array(
		'id' => 'event_options',
		'title' => 'Event Options',
		'pages' => array('event'), // post type
		'context' => 'normal',
		'priority' => 'high',
		'show_names' => true, // Show field names on the left
		'fields' => array(
			array(
				'name' => 'Start Date',
				'desc' => '<strong>Required</strong>',
				'id' => $prefix . 'start_date',
				'type' => 'text_date_timestamp'
			),
			array(
	            'name' => 'Start Time',
	            'desc' => '(optional) - All-day event if left blank',
	            'id' => $prefix . 'start_time',
	            'type' => 'text_time'
	        ),	
			array(
				'name' => 'End Date',
				'desc' => '<strong>Required</strong>',
				'id' => $prefix . 'end_date',
				'type' => 'text_date_timestamp'
			),
			array(
	            'name' => 'End Time',
	            'desc' => '(optional) - Ignored if no Start Time',
	            'id' => $prefix . 'end_time',
	            'type' => 'text_time'
	        ),
			array(
				'name'    => 'Frequency',
				'desc'	  => 'Based on <strong>Start Date</strong>',
				'id'      => $prefix . 'frequency',
				'type'    => 'select',
				'options' => array(
					array( 'name' => 'Once', 'value' => '', ),
					array( 'name' => 'Custom Weekly (choose below)', 'value' => 'custom', ),
					array( 'name' => 'Weekly: Same day each week', 'value' => 'weekly_1', ),
					array( 'name' => 'Weekly 2: Same day every 2 weeks', 'value' => 'weekly_2', ),
					array( 'name' => 'Weekly 3: Same day every 3 weeks', 'value' => 'weekly_3', ),
					array( 'name' => 'Weekly 4: Same day every 4 weeks', 'value' => 'weekly_4', ),
					array( 'name' => 'Monthly: Same date every month', 'value' => 'monthly_1', ),
					array( 'name' => 'Montly 2: Same date every 2 months', 'value' => 'monthly_2', ),
					array( 'name' => 'Monthly 3: Same date every 3 months', 'value' => 'monthly_3', ),
					array( 'name' => 'Monthly First: First __ of the month', 'value' => 'monthly_first', ),
					array( 'name' => 'Monthly Second: Second __ of the month', 'value' => 'monthly_second', ),
					array( 'name' => 'Monthly Third: Third __ of the month', 'value' => 'monthly_third', ),
					array( 'name' => 'Monthly Fourth: Fourth __ of the month', 'value' => 'monthly_fourth', ),
					array( 'name' => 'Monthly Last: Last __ of the month', 'value' => 'monthly_last', ),
				),
			),
			array(
				'name'    => 'Custom Weekly',
				'desc'    => 'If "Custom Weekly" chosen above',
				'id'      => $prefix . 'custom_weekly',
				'type'    => 'multicheck',
				'options' => array(
					'0' => 'Sunday',
					'1' => 'Monday',
					'2' => 'Tuesday',
					'3' => 'Wednesday',
					'4' => 'Thursday',
					'5' => 'Friday',
					'6' => 'Saturday',
				),
			),
			array(
				'name' => 'Exception Dates',
				'desc' => 'Will override recurrences for holidays. <br />
					Use following format: <em>MM/DD/YYYY</em>, comma separated.<br />
					Example: <em>01/01/2014, 12/25/2013</em><br />
					To set sitewide exceptions, click <a href="'.get_bloginfo('url').'/wp-admin/edit.php?post_type=event&page=event_options/">here</a>',
				'id'   => $prefix . 'exception_dates',
				'type' => 'textarea_code',
			),
		)
	);
	return $meta_boxes;
}


// Initialize the metabox class
add_action( 'init', 'calendar_initialize_cmb_meta_boxes', 9999 );
function calendar_initialize_cmb_meta_boxes() {
	if ( !class_exists( 'cmb_Meta_Box' ) ) {
		require_once( 'init.php' );
	}
}