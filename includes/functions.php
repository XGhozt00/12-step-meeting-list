<?php

//function: takes 18:30 and returns 6:30 p.m.
//used:		meetings_get and theme
function meetings_format_time($string) {
	if (!strstr($string, ':')) return 'n/a';
	if ($string == '12:00') return 'Noon';
	if ($string == '23:59') return 'Midnight';
	list($hours, $minutes) = explode(':', $string);
	$hours -= 0;
	$ampm = ($hours > 11) ? 'p.m.' : 'a.m.';
	$hours = ($hours > 12) ? $hours - 12 : $hours;
	return $hours . ':' . $minutes . ' ' . $ampm;
}

//function: deletes all the locations in the database
//used:		importer
function meetings_delete_all_locations() {
	//delete locations
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		wp_delete_post($location->ID, true);
	}
	//delete associations with meetings
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		meetings_remove_location($meeting->ID);
	}
}

//function: deletes all the meetings in the database
//used:		importer
function meetings_delete_all_meetings() {
	//delete locations
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		wp_delete_post($meeting->ID, true);
	}
}

//function: remove all regions from database
//used: importer
function meetings_delete_all_regions() {
	$terms = get_terms('region', 'hide_empty=0');
	foreach ($terms as $term) {
		wp_delete_term($term->term_id, 'region');
	}
}

//function: load the regions array
//used: init & importer
function meetings_get_regions() {
	$regions = array();
	$region_terms = get_terms('region', 'hide_empty=0');
	foreach ($region_terms as $region) $regions[$region->term_id] = $region->name;
	return $regions;
}

//function: deletes all orphaned locations (has no meetings associated)
//used:		save_post filter and ad-hoc
function meetings_delete_orphaned_locations() {

	//get all active location_ids
	$active = array();
	$meetings = get_posts('post_type=meetings&numberposts=-1');
	foreach ($meetings as $meeting) {
		$active[] = $meeting->post_parent;
	}

	//get all location ids
	$all_locations = array();
	$locations = get_posts('post_type=locations&numberposts=-1');
	foreach ($locations as $location) {
		$all_locations[] = $location->ID;
	}

	//foreach location id not active, delete it
	$inactive = array_diff($all_locations, $active);
	foreach($inactive as $location_id) {
		wp_delete_post($location_id, true);
	}
}

//get meetings based on post information
//used by meetings_api and theme 
function meetings_get($arguments=array()) {
	global $regions;

	if (empty($arguments) && !empty($_POST)) $arguments = $_POST;

	$meta_query = array(
		'relation'	=> 'AND',
	);


	if (!empty($arguments['day'])) {
		$meta_query[] = array(
			'key'	=> 'day',
			'value'	=> $arguments['day'],
		);
	}

	if (!empty($arguments['region'])) {
		$meta_query[] = array(
			'key'	=> 'region',
			'value'	=> $arguments['region'],
		);
	}

	if (!empty($arguments['types'])) {
		foreach ($arguments['types'] as $type) {
			$meta_query[] = array(
				'key'	=> 'types',
				'value'	=> '"' . $type . '"',
				'compare'=>'LIKE',
			);
		}
	}
	
	$meetings = $locations = array();

	$posts = get_posts(array(
	    'post_type'		=> 'locations',
	    'numberposts'	=> -1,
	));

	foreach ($posts as $post) {
		$custom = get_post_meta($post->ID);
		$locations[$post->ID] = array(
			'address'			=>$custom['address'][0],
			'latitude'			=>$custom['latitude'][0],
			'longitude'			=>$custom['longitude'][0],
			'region_id'			=>$custom['region'][0],
			'region'			=>$regions[$custom['region'][0]],
			'location'			=>$post->post_title,
			'location_url'		=>$post->guid,
			'location_slug'		=>$post->post_name,
			'location_updated'	=>$post->post_modified_gmt,
		);
	}

	$posts = get_posts(array(
	    'post_type'		=> 'meetings',
	    'numberposts'	=> -1,
		'meta_key'		=> 'time',
		'orderby'		=> 'meta_value',
		'order'			=> 'asc',
		'meta_query'	=> $meta_query,
	));

	foreach ($posts as $post) {
		$custom = get_post_meta($post->ID);
		if (empty($locations[$post->post_parent])) {
			echo $post->post_title;
			continue;
		}
		$meetings[] = array_merge(array(
			'id'			=>$post->ID,
			'name'			=>$post->post_title,
			'slug'			=>$post->post_name,
			'notes'			=>$post->post_content,
			'updated'		=>$post->post_modified_gmt,
			'location_id'	=>$post->post_parent,
			'url'			=>$post->guid,
			'time'			=>$custom['time'][0],
			'day'			=>$custom['day'][0],
			'types'			=>unserialize($custom['types'][0]),
		), $locations[$post->post_parent]);
	}

	return $meetings;
}

//api ajax function
//used by theme and app
add_action('wp_ajax_meetings', 'meetings_api');
add_action('wp_ajax_nopriv_meetings', 'meetings_api');

function api() {
	wp_send_json(meetings_get());
};