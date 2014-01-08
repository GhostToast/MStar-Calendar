<?php
/*
Plugin Name: MStar Calendar
Plugin URI: http://morningstarmediagroup.com
Description: Calendar with recurring events support
Version: 1.0
Author: Gustave F. Gerhardt, Daniel J. Rivera
Author URI: http://morningstarmediagroup.com
License: GPL2
*/

// initialization and registration
function mstar_calendar_rewrite_flush() {
    mstar_calendar_create_post_type();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mstar_calendar_rewrite_flush' );

add_action( 'init', 'mstar_calendar_create_post_type', 10, 1);
function mstar_calendar_create_post_type() {

	$cpt = array('Event', 'Events', 'event', array( 'title', 'editor', 'thumbnail', 'revisions' )); 
	
	register_post_type( $cpt[2],				   
		array(
			'labels' => array(
			'name' => $cpt[1],
			'singular_name' => $cpt[0],
			'add_new' => 'Add New ' .$cpt[0],
			'add_new_item' => 'Add New ' .$cpt[0],
			'edit_item' => 'Edit ' .$cpt[0],
			'new_item' => 'New ' .$cpt[0],
			'view_item' => 'View ' .$cpt[0],
			'search_items' => 'Search ' .$cpt[1],
			'not_found' => 'No ' .$cpt[1]. ' found',
			),
		'public' => true,
		'has_archive' => true,
		'supports' => $cpt[3],
		)
	);	
	
	// meta boxes
	require(plugin_dir_path(__FILE__).'/cmb/meta-tate.php');

	// add css
	add_action( 'wp_enqueue_scripts', 'mstar_calendar_register_css' );
	function mstar_calendar_register_css() {
		if ( file_exists( get_stylesheet_directory()."/mstar-calendar.css" ) ) {
			wp_enqueue_style( 'mstar_calendar_css', get_stylesheet_directory_uri() . '/mstar-calendar.css', array(), '1.0' );
		}
	
		elseif ( file_exists( get_template_directory()."/mstar-calendar.css" ) ) {
			wp_enqueue_style( 'mstar_calendar_css', get_template_directory_uri() . '/mstar-calendar.css', array(), '1.0' );
		}
	
		else {
			wp_enqueue_style( 'mstar_calendar_css', plugins_url('/mstar-calendar.css', __FILE__), array(), '1.0' );
		}
	}
}

// main class function, controls everything else
class mstar_calendar
{
	function __construct($before = NULL, $after = NULL, $calendar_mode = true, $expire_time = 12){
		
		// seconds till expire
		$this->expire_time = $expire_time;
		
		// get cache results if exist and not expired
		$transient = get_transient('mstar_event_cache');
		
		if(!empty($transient)){
			$this->output_array = $transient;
		}
        
        // otherwise must be built
        if(empty($this->output_array)){
        
        	$this->taxonomies = get_object_taxonomies('event');
	
			// gather $before, $after months surrounding this month
			if(is_null($before)){
				$this->before = 12;
			} else {
				$this->before = $before;
			}
			
			if(is_null($after)){
				$this->after = $this->before + 1;
			} else {
				$this->after = $after + 1;
			}
			
			$this->months_end = array();
			$this->current_month = (int)date('m');
			for($i = $this->current_month - $this->before; $i < $this->current_month + $this->after; $i++) {
				$this->months[] = mktime(23, 59, 0, $i+1, 0); // last moment of month
			}
			
			// get master exceptions
			$this->master_exceptions = get_option('event_master_exceptions');
			if(!empty($this->master_exceptions)){
				$this->master_exceptions = explode(',', preg_replace('/\s+/', '', $this->master_exceptions));
				foreach($this->master_exceptions as &$exception){
					$exception = strtotime($exception);
				}
			} else {
				$this->master_exceptions = array();
			}
			
			// draw !!
			$this->event_collector($this->months);
			$this->draw_calendar($this->events, $this->months);
			$this->draw_form();
			
			// compile output
			$this->output_array = array();
			
			$this->output_array['calendar_dump'] = '';
			foreach($this->calendars as $v){
				$this->output_array['calendar_dump'] .= $v;
			}
			
			$this->output_array['form'] = $this->form;
			$this->output_array['events'] = $this->events;
			
			$this->output_array['event_html'] = '';
			foreach($this->event_output as $v){
				$this->output_array['event_html'] .= $v;
			}
			
			// store cache!
			$mod_cacheable = date('U');
			
			$cacheable = serialize($this->output_array);
			
			set_transient('mstar_event_cache', $this->output_array, $this->expire_time * HOUR_IN_SECONDS);

		}
		
		$this->calendar_mode = $calendar_mode;
		// drawvascript !!
		if(isset($this->calendar_mode)){
        	$this->calendar_script();
        }
	}
	
	public function output_calendar(){
		$return  = $this->output_array['calendar_dump'];
		$return .= $this->output_array['form'];
		$return .= $this->output_array['event_html'];
		return $return;
	}
	
	// returns raw events
	public function output_events(){
		return $this->output_array['events'];
	}
	
	/**
	 * Shortens an UTF-8 encoded string without breaking words.
	 */
	public function utf8_truncate( $string, $max_chars = 200, $append = "..." )
	{
	    $string = strip_tags( $string );
	    $string = html_entity_decode( $string, ENT_QUOTES, 'utf-8' );
	    // \xC2\xA0 is the no-break space
	    $string = trim( $string, "\n\r\t .-;–,—\xC2\xA0" );
	    $length = strlen( utf8_decode( $string ) );
	
	    // Nothing to do.
	    if ( $length < $max_chars )
	    {
	        return $string;
	    }
	
	    // mb_substr() is in /wp-includes/compat.php as a fallback if
	    // your the current PHP installation doesn’t have it.
	    $string = mb_substr( $string, 0, $max_chars, 'utf-8' );
	
	    // No white space. One long word or chinese/korean/japanese text.
	    if ( FALSE === strpos( $string, ' ' ) )
	    {
	        return $string . $append;
	    }
	
	    // Avoid breaks within words. Find the last white space.
	    if ( extension_loaded( 'mbstring' ) )
	    {
	        $pos   = mb_strrpos( $string, ' ', 'utf-8' );
	        $short = mb_substr( $string, 0, $pos, 'utf-8' );
	    }
		else
	    {
	        // Workaround. May be slow on long strings.
	        $words = explode( ' ', $string );
	        // Drop the last word.
	        array_pop( $words );
	        $short = implode( ' ', $words );
	    }
	
	    return $short . $append;
	}
	
	// array_multisort variant
	public function mstar_calendar_sort($array, $cols) {
	    $colarr = array();
	    foreach ($cols as $col => $order) {
	        $colarr[$col] = array();
	        foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
	    }
	    $eval = 'array_multisort(';
	    foreach ($cols as $col => $order) {
	        $eval .= '$colarr[\''.$col.'\'],'.$order.',';
	    }
	    $eval = substr($eval,0,-1).');';
	    eval($eval);
	    $return = array();
	    foreach ($colarr as $col => $arr) {
	        foreach ($arr as $k => $v) {
	            $k = substr($k,1);
	            if (!isset($return[$k])) $return[$k] = $array[$k];
	            $return[$k][$col] = $array[$k][$col];
	        }
	    }
	    return $return;
	}
	
	private function event_collector($months){
		global $post;
		global $the_events;

		$args = array(
			'post_type' => 'event',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'meta_query' => array(
				array(
					'key' => '_cmb_end_date',
					'value' => strtotime('-'.$this->before .' months'),
					'compare' => '>=',
					'type' => 'NUMERIC',
				),
			),
		);
				
		$loop = new WP_Query($args);

		$the_events = array();
		
		if($loop->have_posts()){
			while($loop->have_posts()) : $loop->the_post();
			
				$post->frequency 		= get_post_meta($post->ID, '_cmb_frequency', true);
				$post->custom_weekly	= get_post_meta($post->ID, '_cmb_custom_weekly', false);
				$post->exceptions		= get_post_meta($post->ID, '_cmb_exception_dates', true);
				$post->start_date 		= get_post_meta($post->ID, '_cmb_start_date', true);
				$post->start_time 		= $this->second_counter(get_post_meta($post->ID, '_cmb_start_time', true));
				$post->end_date			= get_post_meta($post->ID, '_cmb_end_date', true);
				$post->end_time			= $this->second_counter(get_post_meta($post->ID, '_cmb_end_time', true));
				
				$exceptions = array();
				if(!empty($post->exceptions)){
					$exceptions = explode(',', preg_replace('/\s+/', '', $post->exceptions));
					foreach($exceptions as &$exception){
						// turn into timestamp
						$exception = strtotime($exception);
					}
				} else {
					$post->exceptions = array();
				}
				
				$recurrences = array();
				$end_months = end($months);
				if(!empty($post->frequency)){
	
					// determine last moment recurrence will run
					if($end_months > $post->end_date){
						$end = intval($post->end_date + $post->end_time);
					} else {
						$end = $end_months;
					}
					
					//build recurrences based on temporal key
					switch ($post->frequency){
						case 'custom':
							$interval = 1;
							$i = 0;
							while(strtotime('+'.$i.' days', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' days', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									if(in_array(date('w', $start_date), $post->custom_weekly)){
										$the_events[] = array(
											'start_date'	=> $start_date,
											'start_time' 	=> $post->start_time,
											'end_date'		=> false,
											'end_time'		=> $post->end_time,
											'post_id'		=> $post->ID,
										);
									}
								}
								$i = $interval + $i;
							}
						break;
						
						case 'weekly_1':
							$interval = 7;
							$i = 0;
							while(strtotime('+'.$i.' days', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' days', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
							
						case 'weekly_2':
							$interval = 14;
							$i = 0;
							while(strtotime('+'.$i.' days', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' days', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
							
						case 'weekly_3':
							$interval = 21;
							$i = 0;
							while(strtotime('+'.$i.' days', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' days', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
							
						case 'weekly_4':
							$interval = 28;
							$i = 0;
							while(strtotime('+'.$i.' days', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' days', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
						
						case 'monthly_1':
							$interval = 1;
							$i = 0;
							while(strtotime('+'.$i.' months', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' months', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
						
						case 'monthly_2':
							$interval = 2;
							$i = 0;
							while(strtotime('+'.$i.' months', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' months', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
						
						case 'monthly_3':
							$interval = 3;
							$i = 0;
							while(strtotime('+'.$i.' months', $post->start_date) < $end){
								$start_date = strtotime('+'.$i.' months', $post->start_date);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$i = $interval + $i;
							}
						break;
						
						case 'monthly_first':
							$i = 1;
							$month_year = date('F Y', $post->start_date);
							$day = date('l', $post->start_date);
							
							while(strtotime('First '.$day.' of '.$month_year) < $end){
								$start_date = strtotime('First '.$day.' of '.$month_year);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$month_year = date('F Y', strtotime('+'.$i.' months', $post->start_date)); 
								$i++;
							}
						break;
						
						case 'monthly_second':
							$i = 1;
							$month_year = date('F Y', $post->start_date);
							$day = date('l', $post->start_date);
							
							while(strtotime('Second '.$day.' of '.$month_year) < $end){
								$start_date = strtotime('Second '.$day.' of '.$month_year);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$month_year = date('F Y', strtotime('+'.$i.' months', $post->start_date)); 
								$i++;
							}
						break;
						
						case 'monthly_third':
							$i = 1;
							$month_year = date('F Y', $post->start_date);
							$day = date('l', $post->start_date);
							
							while(strtotime('Third '.$day.' of '.$month_year) < $end){
								$start_date = strtotime('Third '.$day.' of '.$month_year);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$month_year = date('F Y', strtotime('+'.$i.' months', $post->start_date)); 
								$i++;
							}
						break;
						
						case 'monthly_fourth':
							$i = 1;
							$month_year = date('F Y', $post->start_date);
							$day = date('l', $post->start_date);
							
							while(strtotime('Fourth '.$day.' of '.$month_year) < $end){
								$start_date = strtotime('Fourth '.$day.' of '.$month_year);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$month_year = date('F Y', strtotime('+'.$i.' months', $post->start_date)); 
								$i++;
							}
						break;
							
						case 'monthly_last':
							$i = 1;
							$month_year = date('F Y', $post->start_date);
							$day = date('l', $post->start_date);
							
							while(strtotime('Last '.$day.' of '.$month_year) < $end){
								$start_date = strtotime('Last '.$day.' of '.$month_year);
								if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
									$the_events[] = array(
										'start_date'	=> $start_date,
										'start_time' 	=> $post->start_time,
										'end_date'		=> false,
										'end_time'		=> $post->end_time,
										'post_id'		=> $post->ID,
									);
								}
								$month_year = date('F Y', strtotime('+'.$i.' months', $post->start_date)); 
								$i++;
							}
						break;
					}
				} elseif(!empty($post->exceptions)) {
					$interval = 1;
					$i = 0;
					while(strtotime('+'.$i.' days', $post->start_date) <= $post->end_date){
						$start_date = strtotime('+'.$i.' days', $post->start_date);
						if(!in_array($start_date, $exceptions) && !in_array($start_date, $this->master_exceptions)){
							$the_events[] = array(
								'start_date'	=> $start_date,
								'start_time' 	=> $post->start_time,
								'end_date'		=> false,
								'end_time'		=> $post->end_time,
								'post_id'		=> $post->ID,
							);
						}
						$i = $interval + $i;
					}
				} else {
					$the_events[] = array(
						'start_date'	=> $post->start_date,
						'start_time' 	=> $post->start_time,
						'end_date'		=> (($post->start_date != $post->end_date) ? $post->end_date : '' ),
						'end_time'		=> $post->end_time,
						'post_id'		=> $post->ID,
					);
				}
				
			endwhile;
			
			// sort events by start date, start time
			$this->events = $this->mstar_calendar_sort($the_events, array('start_date' => SORT_ASC, 'start_time' => SORT_ASC));
		}
		wp_reset_query();
	}
	
	// count number of seconds since midnight, expects "00:00 AM" or similar 
	private function second_counter($time){
		if(empty($time)){
			return;
		}
		$parsed_time = date_parse_from_format('g:i a', $time);
		return ($parsed_time['hour'] * 3600) + ($parsed_time['minute'] * 60);
	}
	
	private function draw_calendar($events, $months){
		
		$event_output = array();
		$event_output[] = '<div class="no-events">No events found</div>';
		
		$calendars = array();
		
		foreach($months as $key => $one_month){
			
			$calendar = '';
			
			$month_name = date('F', $one_month);
			$month = date('m', $one_month);
			$year = date('Y', $one_month);
			if(isset($months[$key - 1])) $prev_month = $months[$key - 1];
			if(isset($months[$key + 1])) $next_month = $months[$key + 1];
			
			// draw table
			$calendar .= '<table cellpadding="0" cellspacing="0" class="calendar" data-month="'.strtotime($month_name.' '.$year).'">';
			
			// table headings
			$headings = array('Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa');
			$calendar .= '<tr class="calenar-row"><th class="blank-placeholder"></th><th class="calendar-month-head" colspan="7">';
			if(!empty($prev_month)){
				$calendar .= '<a href="javascript:void(0)" class="prev-month" title="'.date('F Y', $prev_month).'" data-month="'.strtotime('-1 month', date('U', strtotime($month_name.' '.$year))).'">&lt;</a>';
			}
			$calendar .= '<a href="javascript:void(0)" class="view-month" data-month="'.date('U', strtotime($month_name.' '.$year)).'">'.$month_name.' '.$year.'</a>';
			if(!empty($next_month)){
				$calendar .= '<a href="javascript:void(0)" class="next-month" title="'.date('F Y', $next_month).'" data-month="'.strtotime('+1 month', date('U', strtotime($month_name.' '.$year))).'">&gt;</a>';
			}
			$calendar .= '</th></tr>';
			$calendar .= '<tr class="calendar-row"><td class="blank-placeholder"></td><td class="calendar-day-head">'.implode('</td><td class="calendar-day-head">', $headings).'</td></tr>';
			
			// day and week variables
			$running_day = date('w', mktime(0,0,0,$month,1,$year));
			$days_in_month = date('t', mktime(0,0,0,$month,1,$year));
			$days_in_this_week = 1;
			$day_counter = 0;
			$dates_array = array();
			
			//row for week one
			$calendar .= '<tr class="calendar-row">';
			$calendar .= '<td class="week-selector"><div class="week-bullet"><a href="javascript:void(0)"><span>&bull;</span></a></div></td>';
			
			// print blank days until first of current week
			for($i = 0; $i < $running_day; $i++){
				$calendar .= '<td class="calendar-day empty-day"> </td>';
				$days_in_this_week++;
			}
			
			// add more days
			for($list_day = 1; $list_day <= $days_in_month; $list_day++){
						
				$list_day_timestamp = date('U', mktime(0,0,0,$month,$list_day,$year));
				$list_day_output = '<span>'.$list_day.'</span>';
				
				// check if day off (holiday)
				if(!empty($this->master_exceptions)){
					if(in_array($list_day_timestamp, $this->master_exceptions)){
						$day_off = true;
					} else {
					 	$day_off = false;
					}
				} else {
					$day_off = false;
				}
				
				if(!$day_off){
					foreach($events as $event){
						if($list_day_timestamp == $event['start_date']){
							$list_day_output = '<a href="javascript:void(0)" data-dates="'.$event['start_date'].'"><span>'.$list_day.'</span></a>';
							$event_output[] = $this->event_output_builder($event);
						} 
						elseif(!empty($event['recurrences'])){
							foreach($event['recurrences'] as $recurrence){
								if($list_day_timestamp == $recurrence){
									$list_day_output = '<a href="javascript:void(0)" data-dates="'.$recurrence.'"><span>'.$list_day.'</span></a>';
									$event_output[] = $this->event_output_builder($event, $recurrence);
								}
							}
						}
						elseif(!empty($event['end_date'])){
							if($event['end_date'] >= $list_day_timestamp && $event['start_date'] <= $list_day_timestamp){
								$list_day_output = '<a href="javascript:void(0)" data-dates="'.$list_day_timestamp.'"><span>'.$list_day.'</span></a>';
							}
						}
					}
				}
				
				$calendar .= '<td class="calendar-day" data-dates="'.$list_day_timestamp.'">';
				$calendar .= '<div class="day-number">'.$list_day_output.'</div>';
				$calendar .= '</td>';
				
				if($running_day == 6){
					// end of week
					$calendar .= '</tr>';
					
					if(($day_counter+1) != $days_in_month){
						$calendar .= '<tr class="calendar-row">';
						$calendar .= '<td class="week-selector"><div class="week-bullet"><a href="javascript:void(0)"><span>&bull;</span></a></div></td>';
					}
					
					$running_day = -1;
					$days_in_this_week = 0;
					
				}
				
				$days_in_this_week++; 
				$running_day++; 
				$day_counter++;
			}
			
			// finish rest of days in last row
			if($days_in_this_week < 8 && $days_in_this_week != 1){
				for($i = 1; $i <= (8 - $days_in_this_week); $i++){
					$calendar .= '<td class="calendar-day empty-day"> </td>';
				}
			}
			
			// close final row and end table
			$calendar .= '</tr></table>';
			$calendars[] = $calendar;
		}
		
		$this->calendars = $calendars;
		$this->event_output = $event_output;
			
	}
	
	
	private function event_output_builder($event, $start_day = NULL){
	
		// is part of recurring, therefore can only be one day long
		if(!empty($start_day)){
			$dates = array($start_day);
		} else {
			// may contain multiple days, check.
			if(!empty($event['end_date']) && empty($event['recurrences'])){
				$dates = array($event['start_date']);
				$current_date = ($event['start_date'] + 86400);
				while($current_date <= $event['end_date']){
					$dates[] = $current_date;
					$current_date = ($current_date + 86400);
				}	
			} else {
				// otherwise is single day, not part of recurring
				$dates = array($event['start_date']);
			}
			$start_day = $event['start_date'];
		}
		
		if(!empty($this->taxonomies)){
			$taxonomies = $this->taxonomies;
			$taxo_terms = array();
			foreach($taxonomies as $taxonomy){
				$terms = get_the_terms($event['post_id'], $taxonomy);
				if(!empty($terms)){
					foreach($terms as $term) {
						$taxo_terms[] = $term->slug;
					}
				}
			}
		} else {
			$taxo_terms = array();
		}
		
		// fill in remaining info by associated post ID
		$event['url'] 		= get_permalink($event['post_id']);
		$event['title'] 	= get_the_title($event['post_id']);
		$post_object 		= get_post($event['post_id']);
		$event['content']	= $post_object->post_content;
		$event['hide_date'] = get_post_meta($event['post_id'], '_cmb_date_hider', true);
		
		$result  = '<div class="event" data-dates="['.implode(', ', $dates).']" '.(is_array($taxo_terms) ? 'data-terms="['.implode(', ', $taxo_terms).']"' : '').'>';
		$result .= '<h2><a href="'.$event['url'].'" class="fancybox fancybox.ajax">'.$event['title'].'</a></h2>';
		
		if($event['hide_date'] !== 'on'){
		
			$result .= '<div class="event-date">'.date('l, F j, Y', $start_day);
	
			// multi day
			if(count($dates) > 1){
				$result .= ' - ' . date('l, F j, Y', end($dates));
			}
			$result .= '</div>';
		
			if(!empty($event['start_time']) || $event['start_time'] === 0){ 
				$result .= '<div class="event-time">';
				$result .= date('g:i a', $event['start_time']);
				if(!empty($event['end_time'])){
					$result .= ' - ' . date('g:i a', $event['end_time']);
				}
				$result .= '</div>';
			}
		}
		$result .= '<div class="event-description">';
		$result .= $this->utf8_truncate( $event['content'], 200, $append = "\xC2\xA0…" ) . ' <a href="'.$event['url'].'" class="fancybox fancybox.ajax">read more</a>';
		$result .= '</div>';
		
		$result .= '</div>';
		
		return $result;
	}
	
	private function draw_form(){
		$form = '<h2 class="calendar-result-announcement">
			<span class="time-result-announcement"></span>
			<span class="text-result-announcement"></span>
			<span class="drop-result-announcement"></span>
			<!-- jquery result text goes here --></h2>
				<div class="pagination"><!-- jquery pagination goes here --></div>	
				<form id="event_search" name="event_search" method="POST" action="">';
				if(!empty($this->taxonomies)){
					$taxonomies = $this->taxonomies;
					foreach($taxonomies as $taxonomy){
						$taxonomy_name = get_taxonomy($taxonomy)->label;
						$terms = get_terms($taxonomy);
						if(!empty($terms)){
							$form .= '<div class="event-taxonomy-box">';
							$form .= '<label for="'.$taxonomy.'">'.$taxonomy_name.'</label>';
							$form .= '<select name="'.$taxonomy.'" id="'.$taxonomy.'">';
							$form .= '<option value="*">All</option>';
							foreach($terms as $term) {
								$form .= '<option value="'.$term->slug.'">'.$term->name.'</option>';
							}
							$form .= '</select>';
							$form .= '</div>';
						}
					}
				} 
		$form .=	'<div class="reset-holder cal-reset">
                    	<input type="text" id="text_search" placeholder="filter by text">
                    	<input type="button" id="reset" value="Reset" />
                    </div>';
		
		$form .= '</form>';
		$this->form = $form;         
	}
	
	public function calendar_script(){
		wp_register_script('bootpag', plugin_dir_url(__FILE__).'jquery.bootpag.min.js', array( 'jquery' ));
		wp_register_script('mstar_calendar_js', plugin_dir_url(__FILE__).'mstar_calendar.min.js', array( 'jquery' ));
		wp_enqueue_script('bootpag');
		wp_enqueue_script('mstar_calendar_js');	
	}
}

// Add CPT Settings Page for events
add_action('admin_menu', 'mstar_enable_cpt_pages');
function mstar_enable_cpt_pages() {
	add_submenu_page('edit.php?post_type=event', 'Event Options', 'Event Options', 'manage_options', 'event_options', 'mstar_event_options');
}

function mstar_event_options() {
	if(!current_user_can('manage_options')){
		wp_die('You do not have sufficient permission to access this page.');
	}
	$event_master_exceptions = get_option('event_master_exceptions');
	?>
	<div class="wrap">  
        <?php screen_icon('options-general'); ?> <h2>Event Options</h2>  
        <?php
		if(isset($_POST['event_update_settings']) && $_POST['event_update_settings'] == 'Y' ){
			$event_master_exceptions = $_POST['event_master_exceptions'];
			update_option('event_master_exceptions', $event_master_exceptions);
			echo '<div id="message" class="updated">Settings saved</div>';
		}
		
		if(isset($_POST['event_clear_cache']) && $_POST['event_clear_cache'] == 'Y' ){
			delete_transient('mstar_event_cache');
			echo '<div id="message" class="updated">Cache emptied! Visit Events page to rebuild!</div>';
		}
		
		$path = plugin_dir_url(__FILE__).'/cmb/';
		// lean on meta-tate stuff to do our bidding
		wp_register_script( 'cmb-timepicker', $path . 'js/jquery.timePicker.min.js' );
		wp_register_script( 'cmb-scripts', $path . 'js/cmb.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'media-upload', 'thickbox', 'farbtastic' ) );
		wp_enqueue_script( 'cmb-timepicker' );
		wp_enqueue_script( 'cmb-scripts' );
		wp_register_style( 'cmb-styles', $path . 'style.css', array( 'thickbox', 'farbtastic' ) );
		wp_enqueue_style( 'cmb-styles' );
		?>
  
        <form method="POST" action="">  
            <table class="form-table">
            	<tr valign="top">
                	<td colspan="2">
                    	<p>Enter dates when <strong>no events</strong> should run (i.e. holidays). This overrides all recurring events, sitewide.</p>
                        
						<p>Format should be <em>MM/DD/YYYY</em>, comma separated.<br />
                        <strong>Example</strong>: 07/04/2013, 12/25/2013, 01/20/2014
                        </p>
                        <p>Use the Date Picker below to add dates.<br />
                        <em>Note: the order does not matter</em>
                        </p>
                       
                    </td>
                </tr>
                <tr valign="top">  
                    <td>  
                        <label for="event_master_exceptions">  
                            Master Exception Dates
                        </label>   
                    </td>  
                    <td>  
                        <textarea name="event_master_exceptions" id="event_master_exceptions" rows="5" cols="50"><?php echo $event_master_exceptions; ?></textarea>
                    </td>  
                </tr>  
                <tr valign="top">
                	<td><label for="event_exception_picker">Date Picker</label>
                    <td><input class="cmb_text_small cmb_datepicker" type="text" name="event_exception_picker" id="event_exception_picker" value="<?php date('m/d/Y');?>" /><br />
                    	<input type="button" value="Add Date" class="button action" id="add_date" /></td>
                </tr>
            </table> 
            <script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('#add_date').click(function() {
						var new_value = jQuery('#event_exception_picker').val();
						var old_value = jQuery('#event_master_exceptions').val();
						jQuery('#event_master_exceptions').val(old_value + ', ' + new_value);
					});
				});
			</script> 
            <p>  
            	<input type="hidden" name="event_update_settings" value="Y" />
                <input type="submit" value="<?php esc_attr_e('Save Changes'); ?>" class="button-primary"/>  
            </p>
		</form>  
		
		<hr />
		
		<h3 class="title">Delete Cache</h3>
		<form method="POST" action=""> 
            <p>
            	Delete events cache. <br />
            	Useful if new events created, modified, or destroyed. <br />
            	Otherwise, cache is rebuilt periodically.
            </p>
            <p>
            	<input type="hidden" name="event_clear_cache" value="Y" />
            	<input type="submit" value="<?php esc_attr_e('Delete Cache');?>" class="button-primary" />
            </p>
        </form>  
        
    </div>
    <?php
}

/***** 
 *
 * tinymce button and shortcode
 *
 ****/
add_action('init', 'mstar_calendar_button');

function mstar_calendar_button() {
	if((current_user_can('edit_posts') || current_user_can('edit_pages')) && get_user_option('rich_editing')){
		add_filter('mce_external_plugins', 'mstar_calendar_button_plugin');
		add_filter('mce_buttons', 'mstar_calendar_register_button');
	}
}

function mstar_calendar_register_button($buttons) {
	array_push($buttons, "|", "mstar_calendar");
	return $buttons;
}

function mstar_calendar_button_plugin($plugin_array) {
	$plugin_array['mstar_calendar'] = plugin_dir_url(__FILE__) . 'mstar_calendar_button.js';
	return $plugin_array;
}

// video link shortcode [mstar_calendar history="" future=""]
add_shortcode('mstar_calendar', 'mstar_calendar_generator');

function mstar_calendar_generator($atts) {
	extract( shortcode_atts( array(
		'history' => '12',
		'future'  => '12',
		'cache_hours' => '24'
		),
		$atts 
		)
	);
	$events = new mstar_calendar($history, $future, true, $cache_hours);
	$return = $events->output_calendar();
	return $return;
}

// add method for displaying singles
add_filter('template_include', 'mstar_calendar_template_include');
function mstar_calendar_template_include($template){
	if(is_single() && 'event' === get_query_var('post_type')) {
		if ( file_exists( get_stylesheet_directory() . '/single-event.php' ) ) {
			return get_stylesheet_directory() . '/single-event.php';
		} elseif ( file_exists( get_template_directory() . '/single-event.php' ) ) {
			return get_stylesheet_directory() . '/single-event.php';
		} else {
			add_filter('the_content', 'mstar_calendar_content_filter', 10);
			return $template;
		}
	}
	return $template;
}

function mstar_calendar_content_filter( $content ) {
	if(get_post_type() == 'event'){
		global $post;
	
		$post->start_date 		= get_post_meta($post->ID, '_cmb_start_date', true);
	    $post->frequency		= get_post_meta($post->ID, '_cmb_frequency', true);
	    $post->custom_weekly	= get_post_meta($post->ID, '_cmb_custom_weekly', false);
		$post->start_time 		= get_post_meta($post->ID, '_cmb_start_time', true);
		$post->end_date			= get_post_meta($post->ID, '_cmb_end_date', true);
		$post->end_time			= get_post_meta($post->ID, '_cmb_end_time', true);
		
		switch ($post->frequency){
			case 'custom':
			$daysOfTheWeek = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
			foreach($post->custom_weekly as &$week_day){
				$week_day = $daysOfTheWeek[$week_day];
			}
			$day = implode(', ', $post->custom_weekly);
			$post->temporal = 'Every '.$day;
			break;
			
			case 'weekly_1':
			$day = date('l', $post->start_date);
			$post->temporal = 'Every '.$day;
			break;
				
			case 'weekly_2':
			$day = date('l', $post->start_date);
			$post->temporal = 'Every other '.$day;
			break;
				
			case 'weekly_3':
			$day = date('l', $post->start_date);
			$post->temporal = 'Every third '.$day;
			break;
				
			case 'weekly_4':
			$day = date('l', $post->start_date);
			$post->temporal = 'Every fourth '.$day;
			break;
			
			case 'monthly_1':
			$day = date('jS', $post->start_date);
			$post->temporal = 'Every month on the '.$day;
			break;
			
			case 'monthly_2':
			$day = date('jS', $post->start_date);
			$post->temporal = 'Every two months on the '.$day;
			break;
			
			case 'monthly_3':
			$day = date('jS', $post->start_date);
			$post->temporal = 'Every three months on the '.$day;
			break;
			
			case 'monthly_first':
			$day = date('l', $post->start_date);
			$post->temporal = 'The first '.$day.' of every month';
			break;
			
			case 'monthly_second':
			$day = date('l', $post->start_date);
			$post->temporal = 'The second '.$day.' of every month';
			break;
			
			case 'monthly_third':
			$day = date('l', $post->start_date);
			$post->temporal = 'The third '.$day.' of every month';
			break;
			
			case 'monthly_fourth':
			$day = date('l', $post->start_date);
			$post->temporal = 'The fourth '.$day.' of every month';
			break;
				
			case 'monthly_last':
			$day = date('l', $post->start_date);
			$post->temporal = 'The last '.$day.' of every month';
			break;
			
			default:
			$post->temporal = NULL;
			break;
		}
	    
	    $html  = '<div class="event-single">';
		$html .= '<div class="event-date">';
		$html .= '<p>';
		
		// frequency
		if(!empty($post->temporal)){
			$html .= $post->temporal . '<br />';
		}
	
		$html .= date('l, F j, Y', $post->start_date);
		
		if($post->start_date !== $post->end_date){
			$html .= ' - ' . date('l, F j, Y', $post->end_date);
		}
					
		$html .= '</p>';
		$html .= '</div>';
		
		if(!empty($post->start_time)){ 
			$html .= '<div class="event-time">';
			$html .= ltrim($post->start_time, '0');
			if(!empty($post->end_time)){
				$html .= ' - ' . ltrim($post->end_time, '0');
			}
			$html .= '</div>';
		}
	
		$content = $html.$content;
	}
	
	return $content;
}
?>
