<?php
$comment_id	= wpjam_get_parameter('id',	['method'=>'POST', 'type'=>'int', 'required'=>true]);

$result		= WPAJAM_Comment::delete($comment_id);
if(is_wp_error($result)){
	wpjam_send_json($result);
}

$response['errmsg']	= '删除成功';