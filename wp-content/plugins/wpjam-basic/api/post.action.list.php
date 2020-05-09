<?php
$comment_type	= str_replace('.list', '', $module_action);

$post_id	= wpjam_get_parameter('post_id');

if(!is_null($post_id)){
	$output	= $output ?: $comment_type.'s';

	$response[$output]	= WPJAM_Comment::get_comments(['post_id'=>$post_id,	'type'=>$comment_type]);
}else{
	$output	= $output ?: ($post_type ? $post_type.'s' : 'posts');

	$comment_args	= [
		'type'			=> $comment_type,
		'number'		=> $args['number'] ?? 20,
		'no_found_rows'	=> false,
		'hierarchical'	=> true,
		'update_comment_meta_cache'	=> true,
		'update_comment_post_cache'	=> true
	];

	$post_type	= $post_type ?: ($_GET['post_type'] ?? null);

	if(!is_null($post_type)){
		$comment_args['post_type']	= $post_type;
	}

	if(is_user_logged_in()){
		$comment_args['user_id']	= get_current_user_id();
	}else{
		$comment_author_email	= WPJAM_Comment::get_comment_author_email();

		if(is_wp_error($comment_author_email)){
			wpjam_send_json($comment_author_email);
		}

		$comment_args['author_email']	= $comment_author_email;
	}

	$cursor	= wpjam_get_parameter('cursor', ['method'=>'GET', 'type'=>'int']);

	if($cursor){
		$comment_args['date_query']	= [
			['before'=>get_date_from_gmt(date('Y-m-d H:i:s',$cursor))]
		];
	}

	$next_cursor	= 0;
	$posts_json		= [];

	$wp_coment_query	= new WP_Comment_Query($comment_args);

	if($user_comments = $wp_coment_query->comments){
		foreach ($user_comments as $user_comment){
			$post_json	= wpjam_get_post($user_comment->comment_post_ID, $args);

			if($post_json){
				$post_json[$comment_type]	= WPJAM_Comment::parse_for_json($user_comment);
				$posts_json[]				= $post_json;
			}
		}

		if($wp_coment_query->max_num_pages > 1){
			$next_cursor	= end($posts_json)[$comment_type]['timestamp'];
		}
	}

	$response['next_cursor']	= $next_cursor;
	$response[$output]			= $posts_json;
}

