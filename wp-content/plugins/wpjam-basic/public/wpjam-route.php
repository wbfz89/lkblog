<?php
add_filter('query_vars', function ($query_vars) {
	$query_vars[]	= 'module';
	$query_vars[]	= 'action';

	// 如果 $custom_taxonomy_key.'_id' 不用在 rewrite ，下面代码无效
	// if($custom_taxonomies = get_taxonomies(array('public' => true, '_builtin' => false))){
	// 	foreach ($custom_taxonomies as $custom_taxonomy_key => $custom_taxonomy) {
	// 		$query_vars[]	= $custom_taxonomy_key.'_id';
	// 	}
	// }

	return $query_vars;
});

add_filter('request', function($query_vars){
	$module = $query_vars['module'] ?? '';
	$action = $query_vars['action'] ?? '';

	if($module == 'json' && strpos($action, 'mag.') === 0){
		return $query_vars;
	}

	$tax_query	= [];

	if(!empty($_REQUEST['tag_id'])){
		if($_REQUEST['tag_id'] == -1){
			$tax_query['post_tag']	= [
				'taxonomy'	=> 'post_tag',
				'field'		=> 'id',
				'operator'	=> 'NOT EXISTS'
			];
		}else{
			$query_vars['tag_id'] = $_REQUEST['tag_id'];
		}
	}

	if(!empty($_REQUEST['cat']) && $_REQUEST['cat'] == -1){
		$tax_query['category']	= [
			'taxonomy'	=> 'category',
			'field'		=> 'id',
			'operator'	=> 'NOT EXISTS'
		];
	}

	if($custom_taxonomies = get_taxonomies(['_builtin' => false])){
		foreach ($custom_taxonomies as $custom_taxonomy) {

			$taxonomy_id		= $custom_taxonomy.'_id';
			$current_term_id	= $query_vars[$taxonomy_id] ?? ($_REQUEST[$taxonomy_id] ?? '');

			if(!$current_term_id){
				continue;
			}

			if($current_term_id == -1){
				$tax_query[$custom_taxonomy]	= [
					'taxonomy'	=> $custom_taxonomy,
					'field'		=> 'id',
					'operator'	=> 'NOT EXISTS'
				];
			}elseif($term = get_term($current_term_id, $custom_taxonomy)){	// wp 本身的 cache 有问题， WP_Term::get_instance
				$tax_query[$custom_taxonomy]	= [
					'taxonomy'	=> $custom_taxonomy,
					'terms'		=> array( $current_term_id ),
					'field'		=> 'id',
				];
			}else{
				wp_die('非法'.$taxonomy_id);
			}
		}
	}

	if($tax_query){
		$query_vars['tax_query']				= array_values($tax_query);
		$query_vars['tax_query']['relation']	= 'AND';
	}
	
	return $query_vars;
});

//设置 headers
add_action('send_headers', function ($wp){
	if(wpjam_basic_get_setting('x-frame-options')){
		header('X-Frame-Options: '.wpjam_basic_get_setting('x-frame-options'));
	}

	$module = $wp->query_vars['module'] ?? '';
	$action = $wp->query_vars['action'] ?? '';

	if($module){
		remove_action('template_redirect', 'redirect_canonical');

		if($module == 'json'){
			wpjam_send_origin_headers();
			wpjam_json_request($action);
		}

		do_action('wpjam_module', $module, $action);
	}
});

// 当前用户处理
add_filter('determine_current_user', function($user_id){
	if($user_id || !wpjam_is_json_request()){
		return $user_id;
	}

	$wpjam_user	= wpjam_get_current_user();

	if($wpjam_user && !is_wp_error($wpjam_user) && !empty($wpjam_user['user_id'])){
		return $wpjam_user['user_id'];
	}

	return $user_id;
});

add_filter('template_include', function ($template){
	$module	= get_query_var('module');
	$action	= get_query_var('action');

	if($module){
		$action = ($action == 'new' || $action == 'add')?'edit':$action;

		if($action){
			$wpjam_template = STYLESHEETPATH.'/template/'.$module.'/'.$action.'.php';
		}else{
			$wpjam_template = STYLESHEETPATH.'/template/'.$module.'/index.php';
		}

		$wpjam_template		= apply_filters('wpjam_template', $wpjam_template, $module, $action);

		if(is_file($wpjam_template)){
			return $wpjam_template;
		}else{
			wp_die('路由错误！');
		}
	}

	return $template;
});

add_action('wpjam_api_template_redirect', function($json){
	remove_filter('the_excerpt', 'convert_chars');
	remove_filter('the_excerpt', 'wpautop');
	remove_filter('the_excerpt', 'shortcode_unautop');

	remove_filter('the_title', 'convert_chars');

	// add_filter('the_password_form',	function($output){
	// 	if(get_queried_object_id() == get_the_ID()){
	// 		return '';
	// 	}else{
	// 		return $output;
	// 	}
	// });
});

function wpjam_is_json_request(){
	if(get_option('permalink_structure')){
		if(preg_match("/\/api\/(.*)\.json/", $_SERVER['REQUEST_URI'])){ 
			return true;
		}
	}else{
		if(isset($_GET['module']) && $_GET['module'] == 'json'){
			return true;
		}
	}
		
	return false;
}

function wpjam_json_request($action){
	if(strpos($action, 'mag.') !== 0){
		if(!isset($_GET['debug'])){ 
			if(isset($_GET['callback']) || isset($_GET['_jsonp'])){
				$content_type	= 'application/javascript';	
			}else{
				$content_type	= 'application/json';
			}

			@header('Content-Type: ' .  $content_type.'; charset=' . get_option('blog_charset'));
		}

		return;
	}

	global $wp, $wpjam_json;
			
	$wpjam_json	= str_replace(['mag.','/'], ['','.'], $action);

	do_action('wpjam_api_template_redirect', $wpjam_json);

	$api_setting	= wpjam_get_api_setting($wpjam_json);

	if(!$api_setting){
		wpjam_send_json([
			'errcode'	=> 'api_not_defined',
			'errmsg'	=> '接口未定义！',
		]);
	}
	
	$wpjam_user	= wpjam_get_current_user();

	if(is_wp_error($wpjam_user)){
		if(!empty($api_setting['auth'])){
			wpjam_send_json($wpjam_user);
		}else{
			$wpjam_user	= null;
		}
	}elseif(is_null($wpjam_user)){
		if(!empty($api_setting['auth'])){
			wpjam_send_json([
				'errcode'	=>'bad_authentication', 
				'errmsg'	=>'无权限'
			]);
		}
	}

	$response	= ['errcode'=>0];

	$response['current_user']	= $wpjam_user;
	$response['page_title']		= $api_setting['page_title'] ?? '';
	$response['share_title']	= $api_setting['share_title'] ?? '';
	$response['share_image']	= !empty($api_setting['share_image']) ? wpjam_get_thumbnail($api_setting['share_image'], '500x400') : '';

	foreach ($api_setting['modules'] as $module){
		if(!$module['type'] || !$module['args']){
			continue;
		}
		
		if(is_array($module['args'])){
			$args = $module['args'];
		}else{
			$args = wpjam_parse_shortcode_attr(stripslashes_deep($module['args']), 'module');
		}

		$module_type	= $module['type'];
		$module_action	= $args['action'] ?? '';
		$output			= $args['output'] ?? '';

		if(in_array($module_type, ['post_type', 'taxonomy', 'media', 'setting', 'other'])){
			$module_template	= WPJAM_BASIC_PLUGIN_DIR.'api/'.$module_type.'.php';
		}else{
			$module_template	= '';
		}

		$module_template	= apply_filters('wpjam_api_template_include', $module_template, $module_type, $module);

		if($module_template && is_file($module_template)){
			include $module_template;
		}
	}

	$response = apply_filters('wpjam_json', $response, $api_setting, $wpjam_json);

	wpjam_send_json($response);
}

function wpjam_is_module($module='', $action=''){
	$current_module	= get_query_var('module');
	$current_action	= get_query_var('action');

	// 没设置 module
	if(!$current_module){
		return false;
	}
	
	// 不用确定当前是什么 module
	if(!$module){
		return true;
	}
	
	if($module != $current_module){
		return false;
	}

	if(!$action){
		return true;
	}

	if($action != $current_action){
		return false;
	}
	
	return true;
}

function wpjam_send_origin_headers(){
	header('X-Content-Type-Options: nosniff');

	$origin = get_http_origin();

	if ( $origin ) {
		// Requests from file:// and data: URLs send "Origin: null"
		if ( 'null' !== $origin ) {
			$origin = esc_url_raw( $origin );
		}

		@header( 'Access-Control-Allow-Origin: ' . $origin );
		@header( 'Access-Control-Allow-Methods: GET, POST' );
		@header( 'Access-Control-Allow-Credentials: true' );
		@header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
		@header( 'Vary: Origin' );

		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			exit;
		}
	}
	
	if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
		status_header( 403 );
		exit;
	}
}

function wpjam_get_json(){
	global $wpjam_json;

	return $wpjam_json ?? '';
}

function wpjam_is_json($json=''){
	$wpjam_json = wpjam_get_json();

	if(empty($wpjam_json)){
		return false;
	}

	if($json){
		return $wpjam_json == $json;
	}else{
		return true;
	}
}

function is_module($module='', $action=''){
	return wpjam_is_module($module, $action);
}

function is_wpjam_json($json=''){
	return wpjam_is_json($json);
}