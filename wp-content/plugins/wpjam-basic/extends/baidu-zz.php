<?php
/*
Plugin Name: 百度站长
Plugin URI: http://blog.wpjam.com/project/wpjam-basic/
Description: 支持主动，被动，自动以及批量方式提交链接到百度站长。
Version: 1.0
*/

add_action('wp_enqueue_scripts', function(){
	if(wpjam_get_setting('baidu-zz', 'no_js')){
		return;
	}

	if(is_404() || is_preview()){
		return;
	}elseif(is_singular() && get_post_status() != 'publish'){
		return;
	}

	if(is_ssl()){
		wp_enqueue_script('baidu_zz_push', 'https://zz.bdstatic.com/linksubmit/push.js', '', '', true);
	}else{
		wp_enqueue_script('baidu_zz_push', 'http://push.zhanzhang.baidu.com/push.js', '', '', true);
	}
});

function wpjam_notify_baidu_zz($urls, $args=false){
	$site	= wpjam_get_setting('baidu-zz', 'site');
	$token	= wpjam_get_setting('baidu-zz', 'token');
	$mip	= wpjam_get_setting('baidu-zz', 'mip');

	if($site && $token){
		if(is_array($args)){
			$update	= $args['update'] ?? false; 
		}else{
			$update	= $args;
		}

		if($update){
			$baidu_zz_api_url	= 'http://data.zz.baidu.com/update?site='.$site.'&token='.$token;
		}else{
			$baidu_zz_api_url	= 'http://data.zz.baidu.com/urls?site='.$site.'&token='.$token;
		}

		if($mip){
			$baidu_zz_api_url	.= '&type=mip';
		}

		$response	= wp_remote_post($baidu_zz_api_url, array(
			'headers'	=> ['Accept-Encoding'=>'','Content-Type'=>'text/plain'],
			'sslverify'	=> false,
			'blocking'	=> false,
			'body'		=> $urls
		));
	}
}

function wpjam_notify_xzh($urls, $args=false){
	$appid	= wpjam_get_setting('xzh', 'appid');
	$token	= wpjam_get_setting('xzh', 'token');

	if($appid && $token){
		if(is_array($args)){
			$update		= $args['update'] ?? false;
			// $original	= $args['original'] ?? false;
		}else{
			$update		= $args;
			// $original	= false;
		}

		if($update){
			$xzh_api_url = 'http://data.zz.baidu.com/urls?appid='.$appid.'&token='.$token.'&type=batch';
		}else{
			$xzh_api_url = 'http://data.zz.baidu.com/urls?appid='.$appid.'&token='.$token.'&type=realtime';
		}

		$response	= wp_remote_post($xzh_api_url, array(
			'headers'	=> ['Accept-Encoding'=>'','Content-Type'=>'text/plain'],
			'sslverify'	=> false,
			'blocking'	=> false,
			'body'		=> $urls
		));

		// if($original){
		// 	$xzh_api_url = 'http://data.zz.baidu.com/urls?appid='.$appid.'&token='.$token.'&type=original';

		// 	$response	= wp_remote_post($xzh_api_url, array(
		// 		'headers'	=> ['Accept-Encoding'=>'','Content-Type'=>'text/plain'],
		// 		'sslverify'	=> false,
		// 		'blocking'	=> false,
		// 		'body'		=> $urls
		// 	));
		// }
	}
}


add_action('publish_future_post', function($post_id){
	$urls	= apply_filters('baiduz_zz_post_link', get_permalink($post_id))."\n";	

	wp_cache_set($post_id, true, 'wpjam_baidu_zz_notified', HOUR_IN_SECONDS);
	wpjam_notify_xzh($urls);
	wpjam_notify_baidu_zz($urls);
},11);

