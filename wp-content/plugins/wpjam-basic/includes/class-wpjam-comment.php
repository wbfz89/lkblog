<?php
remove_action('check_comment_flood', 'check_comment_flood_db', 10, 4);

add_filter('pre_wp_update_comment_count_now',	['WPJAM_Comment', 'filter_pre_wp_update_comment_count_now'], 10, 3);
add_filter('wp_is_comment_flood', 				['WPJAM_Comment', 'filter_is_comment_flood'], 10, 4);
add_filter('wpjam_post_json',					['WPJAM_Comment', 'filter_post_json'], 10, 2);

class WPJAM_Comment{
	public static function filter_pre_wp_update_comment_count_now($count, $old, $post_id){
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_type = ''", $post_id));
	}

	public static function filter_is_comment_flood($is_flood, $ip, $email, $date){
		global $wpdb;
		
		if(current_user_can('manage_options') || current_user_can('moderate_comments')){
			return false;
		}
		
		$lasttime	= gmdate('Y-m-d H:i:s', time() - 15);

		if(is_user_logged_in()){
			$user			= get_current_user_id();
			$check_column	= '`user_id`';
		}else{
			$user			= $ip;
			$check_column	= '`comment_author_IP`';
		}

		$sql	= $wpdb->prepare("SELECT `comment_date_gmt` FROM `$wpdb->comments` WHERE `comment_type` = '' AND `comment_date_gmt` >= %s AND ( $check_column = %s OR `comment_author_email` = %s ) ORDER BY `comment_date_gmt` DESC LIMIT 1", $lasttime, $user, $email);

		if($wpdb->get_var($sql)) {
			return true;
		}

		return false;
	}

	public static function filter_post_json($post_json, $post_id){
		$post		= get_post($post_id);
		$post_type	= $post->post_type;

		if(post_type_supports($post_type, 'comments')){
			$post_json['comment_count']		= intval($post->comment_count);
			$post_json['comment_status']	= $post->comment_status;
		}

		$actions	= ['like', 'fav'];

		if(post_type_supports($post_type, 'likes')){
			$post_json['like_count']	= intval(get_post_meta($post_id, 'likes', true));
		}

		if(post_type_supports($post_type, 'favs')){
			$post_json['fav_count']	= intval(get_post_meta($post_id, 'favs', true));
		}

		if(is_singular($post_type)){
			if(post_type_supports($post_type, 'comments')){
				$post_json['comments']	= self::get_comments(['post_id'=>$post_id]);
			}

			if(post_type_supports($post_type, 'likes')){
				$is_liked	= self::did_action($post_id, 'like');
				$post_json['is_liked']	= ($is_liked && !is_wp_error($is_liked)) ? 1 : 0;
				$post_json['likes']		= self::get_comments(['post_id'=>$post_id,	'type'=>'like']);
			}

			if(post_type_supports($post_type, 'favs')){
				$is_faved	= self::did_action($post_id, 'fav');
				$post_json['is_faved']	= ($is_faved && !is_wp_error($is_faved)) ? 1 : 0;
			}
		}

		return $post_json;
	}

	public static function get($comment_id){
		return get_comment($comment_id, ARRAY_A);
	}

	public static function insert($comment_data){
		$comment_post_ID	= absint($comment_data['post_id']);
		$comment_type		= $comment_data['type'] ?? '';

		if($comment_type == 'comment' || $comment_type == ''){
			if(empty($comment_post_ID)){
				return new WP_Error('empty_post_id', 'post_id不能为空');
			}

			$post	= get_post($comment_post_ID);
			if(empty($post)){
				return new WP_Error('invalid_post_id', '非法 post_id');
			}

			if($post->comment_status == 'closed'){
				return new WP_Error('comment_closed', '已关闭留言');
			}

			if('publish' != $post->post_status){
				return new WP_Error( 'invalid_post_status', '文章未发布，不能评论。' );
			}

			if(!post_type_supports($post->post_type, 'comments')){
				return new WP_Error('action_not_support', '操作不支持');
			}
		}

		if(is_user_logged_in()){
			$user_id	= get_current_user_id();
			$user		= get_userdata($user_id);

			$comment_author_email	= $user->user_email;
			$comment_author			= $user->display_name ?: $user->user_login;
			$comment_author_url		= $user->user_url;
		}else{
			$comment_author_email	= self::get_comment_author_email();

			if(is_wp_error($comment_author_email)){
				return $comment_author_email;
			}

			$comment_author			= self::get_comment_author();
			$comment_author_url		= '';
		}

		$comment_content	= $comment_data['comment'] ?? '';
		if($comment_type == 'comment' || $comment_type == ''){
			$comment_content	= trim(wp_strip_all_tags($comment_content));

			if(empty($comment_content)){
				return new WP_Error('require_valid_comment', '评论内容不能为空。');
			}
		}

		if(isset($comment_data['parent'])){
			$comment_parent = absint($comment_data['parent']);
		}else{
			$comment_parent		= 0;
		}

		$comment_author_IP	= $comment_data['ip'] ?? preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR']);

		$comment_agent		= $comment_data['agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
		$comment_agent		= substr($comment_agent, 0, 254);

		$comment_date		= $comment_data['date'] ?? current_time('mysql');
		$comment_date_gmt	= $comment_data['date_gmt'] ?? current_time('mysql', 1);

		$comment_meta		= $comment_data['meta'] ?? [];
		
		$comment_data = compact(
			'comment_post_ID',
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_content',
			'comment_type',
			'comment_parent',
			'comment_author_IP',
			'comment_agent',
			'comment_date',
			'comment_date_gmt',
			'user_id',
			'comment_meta'
		);

		$comment_data	= apply_filters('preprocess_comment', $comment_data);

		$comment_data	= wp_slash($comment_data);
		$comment_data	= wp_filter_comment($comment_data);

		$comment_approved	= 1;
		if($comment_type == 'comment' || $comment_type == ''){
			$comment_approved = wp_allow_comment($comment_data, $avoid_die=true);
			if(is_wp_error($comment_approved)) {
				return $comment_approved;
			}
		}

		$comment_data['comment_approved']	= $comment_approved;

		$comment_id	= wp_insert_comment($comment_data);
		
		if(!$comment_id) {
			global $wpdb;

			$fields = ['comment_author', 'comment_author_email', 'comment_author_url', 'comment_content'];

			foreach($fields as $field){
				$comment_data[$field]	= $wpdb->strip_invalid_text_for_column($wpdb->comments, $field, $comment_data[$field]);
			}

			$comment_id	= wp_insert_comment($comment_data);
		}

		if(!$comment_id){
			return new WP_Error( 'comment_save_error', '评论保存失败，请稍后重试！', 500 );
		}

		do_action('comment_post', $comment_id, $comment_data['comment_approved'], $comment_data);

		return $comment_id;
	}

	public static function update($comment_id, $data){
		$comment_data	= [];

		$comment_data	= self::get($comment_id);

		if(isset($data['comment'])){
			$comment_data['comment_content']		= $data['comment'];
		}

		if(isset($data['approved'])){
			$comment_data['comment_approved']	= $data['approved'];
		}

		$result	= wp_update_comment($comment_data);

		if(!$result){
			return new WP_Error('comment_update_failed', '评论更新失败！');
		}

		return $result;
	}

	public static function get_comment_author_email(){
		if(get_option('comment_registration')){
			return new WP_Error('bad_authentication', '必须要登录之后才能操作');	
		}

		$wpjam_user	= wpjam_get_current_user();

		if(is_wp_error($wpjam_user)){
			return $wpjam_user;
		}

		if(empty($wpjam_user) || empty($wpjam_user['user_email'])){
			return new WP_Error('bad_authentication', '无权限');
		}

		return $wpjam_user['user_email'];
	}

	public static function get_comment_author(){
		$wpjam_user	= wpjam_get_current_user();

		return $wpjam_user['nickname'] ?? '';
	}

	public static function delete($comment_id, $force_delete=false){
		$comment	= get_comment($comment_id);

		if(empty($comment)){
			return new WP_Error('comment_not_exists', '评论不存在');
		}

		if(is_user_logged_in()){
			if($comment->user_id != get_current_user_id() && !current_user_can('manage_options')){
				return new WP_Error('bad_authentication', '你不能删除别人的评论');
			}
		}else{
			$comment_author_email	= self::get_comment_author_email();

			if(is_wp_error($comment_author_email)){
				return $comment_author_email;
			}

			if($comment->comment_author_email != $comment_author_email){
				return new WP_Error('bad_authentication', '你不能删除别人的评论');
			}
		}

		return wp_delete_comment($comment_id, $force_delete);
	}

	public static function action($comment_data, $action='like'){
		if(in_array($action, ['unlike', 'unfav'])){
			$type	= str_replace('un', '', $action);
			$status	= -1;
		}else{
			$type	= $action;
			$status	= 1;
		}

		// $types	= ['like'=>'喜欢','fav'=>'收藏'];
		// $label	= $types[$type] ?? '';

		// if(empty($label)){
		// 	return new WP_Error('invalid_action_type', '非法的动作类型'); 
		// }

		$post_id	= absint($comment_data['post_id']);

		if(empty($post_id)){
			return new WP_Error('empty_post_id', 'post_id不能为空');
		}

		$post	= get_post($post_id);
		if(empty($post)){
			return new WP_Error('invalid_post_id', '非法 post_id');
		}

		if(!post_type_supports($post->post_type, $type) && !post_type_supports($post->post_type, $type.'s')){
			return new WP_Error('action_not_support', '操作不支持');
		}

		$did	= self::did_action($post_id, $type);

		if(is_wp_error($did)){
			return $did;
		}

		if($did){
			if($status == 1){
				return true;
				// $result	= new WP_Error('duplicate_'.$type, '不能重复'.$label);
			}

			$result	= wp_delete_comment($did, $force_delete=true);
		}else{
			if($status != 1){
				return true;
				// $result	= new WP_Error('empty_'.$type, '你都没有'.$label.'过。');
			}

			$comment_data['type']	= $type;
			$result	= self::insert($comment_data);
		}

		if(!is_wp_error($result)){
			self::update_count($post_id, $type);
		}

		return $result;
	}

	public static function did_action($post_id, $type='like'){
		$actions	= get_comments(['post_id'=>$post_id, 'type'=>$type, 'order'=>'ASC']);

		if(empty($actions)){
			return 0;
		}

		if(is_user_logged_in()){
			$actions	= wp_list_pluck($actions, 'comment_ID', 'user_id');
			$user_id	= get_current_user_id();
			if(isset($actions[$user_id])){
				return $actions[$user_id];
			}
		}else{
			$comment_author_email	= self::get_comment_author_email();

			if(is_wp_error($comment_author_email)){
				return $comment_author_email;
			}
			
			$actions	= wp_list_pluck($actions, 'comment_ID', 'comment_author_email');
			if(isset($actions[$comment_author_email])){
				return $actions[$comment_author_email];
			}
		}
		
		return 0;
	}

	public static function update_count($post_id, $type='like', $meta_key=''){
		$comments	= get_comments(compact('post_id', 'type'));
		$meta_key	= $meta_key ?: $type.'s';

		update_post_meta($post_id, $meta_key, count($comments));
	}

	public static function get_comments($args=[]){
		$args	= wp_parse_args($args, [
			'post_id'		=> 0,
			'order'			=> 'ASC',
			'type'			=> 'comment',
			'status'		=> 'approve',
			'hierarchical'	=> get_option('thread_comments') ? 'threaded' : false
		]);

		$comments	= get_comments($args);

		if(empty($comments)){
			return [];
		}

		$comments_json	= [];

		if($args['type'] == 'comment' || $args['type'] == ''){

			if($args['hierarchical'] == 'threaded'){
				$comment_hierarchy	= [];

				foreach($comments as $comment){
					$comment_children = $comment->get_children([
						'format'  => 'flat',
						'status'  => $args['status']
					]);

					$comments_json[]	= self::parse_for_json($comment, $comment_children);
				}

				return $comments_json;
			}
		}
		
		foreach($comments as $comment){
			$comments_json[]	= self::parse_for_json($comment);
		}
		
		return $comments_json;
	}

	public static function parse_for_json($comment, $comment_children=null){
		$comment	= get_comment($comment);

		if(empty($comment)){
			return [];
		}

		$timestamp		= strtotime($comment->comment_date_gmt);
		$comment_id		= $comment->comment_ID;
		$comment_type	= $comment->comment_type;
		$post_id		= $comment->comment_post_ID;
		$post_type		= get_post($post_id)->post_type;

		$author			= self::get_author($comment);

		$comment_json	= [
			'id'		=> intval($comment_id),
			'post_id'	=> intval($post_id),
			'timestamp'	=> $timestamp,
			'type'		=> $comment_type ?: 'comment'
		];

		if($comment_type == 'like' || $comment_type == 'fav'){
			$comment_json	= array_merge($comment_json, $author);
		}else{
			$comment_json['time']		= wpjam_human_time_diff($timestamp);
			$comment_json['approved']	= intval($comment->comment_approved);
			$comment_json['content']	= wp_strip_all_tags($comment->comment_content);

			$images	= get_comment_meta($comment_id, 'images', true) ?: [];

			if($images){
				if(count($images) > 1){
					array_walk($images, function(&$image){
						$image = [
							'thumb'		=> wpjam_get_thumbnail($image, '200x200'),
							'original'	=> wpjam_get_thumbnail($image, ['width'=>1080])
						];
					});
				}else{
					array_walk($images, function(&$image){
						$image = [
							'thumb'		=> wpjam_get_thumbnail($image, ['width'=>300]),
							'original'	=> wpjam_get_thumbnail($image, ['width'=>1080])
						];
					});
				}
			}

			$comment_json['images']		= $images;

			if(post_type_supports($post_type, 'rating')){
				$comment_json['rating']		= intval(get_comment_meta($comment_id, 'rating', true));
			}

			$comment_json['parent']		= intval($comment->comment_parent);

			if($comment_type == 'comment' || $comment_type == ''){
				if($parent = $comment_json['parent']){
					$parent_comment	= get_comment($parent);
					$comment_json['reply_to']	= $parent_comment ? $parent_comment->comment_author : '';
				}elseif($comment_children){
					$comment_json['children']	= [];
					foreach($comment_children as $comment_child){
						$comment_json['children'][]	= self::parse_for_json($comment_child);
					}
				}
			}

			$comment_json['author']		= $author;
			$comment_json['user_id']	= $author['user_id'];
		}

		return apply_filters('wpjam_comment_json', $comment_json, $comment_id, $comment);
	}

	public static function get_author($comment){
		$comment	= get_comment($comment);

		$email		= $comment ? $comment->comment_author_email : '';
		$author		= $comment ? $comment->comment_author : '';
		$user_id	= $comment ? intval($comment->user_id) : 0;
		$avatar		= get_avatar_url($comment, 200);

		$userdata	= $user_id ? get_userdata($user_id) : null;
		$nickname	= $userdata ? $userdata->display_name : $author;

		return compact('email', 'author', 'nickname', 'user_id',  'avatar');
	}
}