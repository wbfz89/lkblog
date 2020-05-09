<?php
$post_id		= wpjam_get_parameter('post_id', ['method'=>'POST', 'type'=>'int', 'required'=>true]);

$comment_data	= ['post_id'=>$post_id];

if($module_action == 'comment'){
	$text		= wpjam_get_parameter('text',	['method'=>'POST']);

	if(!empty($text)){
		$comment_data['comment']	= $text;
	}else{
		$comment_data['comment']	= wpjam_get_parameter('comment',	['method'=>'POST', 'required'=>true]);
	}

	$reply_to	= wpjam_get_parameter('reply_to',	['method'=>'POST']);

	if(!empty($reply_to)){
		$comment_data['parent']		= intval($reply_to);
	}else{
		$comment_data['parent']		= wpjam_get_parameter('parent',		['method'=>'POST', 'default'=>0]);
	}

	$comment_meta	= [];

	if($images = wpjam_get_parameter('images', ['method'=>'POST'])){
		if(!is_array($images)){
			$images	= wpjam_json_decode(wp_unslash($images));
		}

		$comment_meta['images']	= $images;
	}

	if($rating = wpjam_get_parameter('rating', ['method'=>'POST'])){
		$comment_meta['rating']	= $rating;
	}

	if($comment_meta){
		$comment_data['meta']	= $comment_meta;
	}

	$comment_id	= WPJAM_Comment::insert($comment_data);
}else{
	$comment_id	= WPJAM_Comment::action($comment_data, $module_action);
}

if(is_wp_error($comment_id)){
	wpjam_send_json($comment_id);
}

