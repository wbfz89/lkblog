<?php
add_filter('wpjam_basic_sub_pages', function($subs){
	$subs['baidu-zz']	=[
		'menu_title'	=>'百度站长',
		'function'		=>'tab',
		'page_file'		=>WPJAM_BASIC_PLUGIN_DIR.'admin/pages/baidu-zz.php',
		'summary'		=>'<p>百度推送扩展由 WordPress 果酱和 <a href="https://www.baidufree.com" target="_blank">纵横SEO</a> 联合推出， 实现提交链接到百度站长，让你的博客的文章能够更快被百度收录，详细介绍请点击：<a href="https://blog.wpjam.com/m/301-redirects/" target="_blank">百度站长扩展</a>。</p>'
	];

	return $subs;
});

add_action('wpjam_post_page_file', function($post_type){
	if($post_type != 'attachment' && is_post_type_viewable($post_type)){
		include WPJAM_BASIC_PLUGIN_DIR.'admin/hooks/post-baidu-zz.php';
	}
});

add_action('wpjam_post_list_page_file', function($post_type){
	if($post_type != 'attachment' && is_post_type_viewable($post_type)){
		include WPJAM_BASIC_PLUGIN_DIR.'admin/hooks/post-baidu-zz.php';
	}
});