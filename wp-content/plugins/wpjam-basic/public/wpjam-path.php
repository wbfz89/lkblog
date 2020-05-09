<?php
function wpjam_register_path($page_key, $args=[]){
	if(wp_is_numeric_array($args)){
		foreach($args as $i=> $item){
			WPJAM_Path::create($page_key, $item);
		}

		return true;
	}else{
		return WPJAM_Path::create($page_key, $args);
	}	
}

function wpjam_get_paths($path_type){
	$wpjam_paths	= WPJAM_Path::get_all();

	if($wpjam_paths && $path_type){
		$wpjam_paths = array_filter($wpjam_paths, function($wpjam_path) use($path_type){
			return $wpjam_path->has($path_type);
		});
	}

	return $wpjam_paths;
}

function wpjam_has_path($path_type, $page_key){
	$path_obj	= WPJAM_Path::get_instance($page_key);

	return is_null($path_obj) ? false : $path_obj->has($path_type);
}

function wpjam_get_path($path_type, $page_key,  $args=[]){
	$path_obj	= WPJAM_Path::get_instance($page_key);

	if(is_null($path_obj)){
		return '';
	}

	$path	= $path_obj->get_path($path_type, $args);

	if(is_wp_error($path) && wpjam_get_json()){
		return '';
	}
	
	return $path;
}

function wpjam_generate_path($data){
	$page_key	= $data['page_key'] ?? '';
	$path_type	= $data['path_type'] ?? 'weapp'; // 历史遗留问题，默认都是 weapp， 非常 ugly 
	return wpjam_get_path($path_type, $page_key, $data);
}

function wpjam_get_path_fields($path_type, $for=''){
	$fields	= [];

	$fields['path_type']	= ['title'=>'',		'type'=>'hidden',	'value'=>$path_type];
	$fields['page_key']		= ['title'=>'页面',	'type'=>'select',	'options'=>[]];

	if($path_objs	= wpjam_get_paths($path_type)){
		foreach ($path_objs as $page_key => $path_obj){
			$fields['page_key']['options'][$page_key]	= $path_obj->get_title();

			$path_fields	= $path_obj->get_fields($path_type);

			if($path_fields){
				array_walk($path_fields, function(&$path_fields) use($page_key){
					$path_fields['data-page_key']	= $page_key;
				});

				$fields	= $fields + $path_fields;
			}
		}
	}

	$fields['page_key']['options']['none']		= '只展示不跳转';
	$fields['page_key']['options']['external']	= '外部链接';

	$fields['url']		= ['title'=>'链接地址',	'type'=>'url',	'data-page_key'=>'external'];

	return apply_filters('wpjam_path_fields', $fields, $path_type, $for);
}