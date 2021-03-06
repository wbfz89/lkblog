<?php 
/**
 Template Name: Statics
 */

get_header();
?>


	<?php while(have_posts()) : the_post(); ?>
	<?php if(akina_option('patternimg') || !get_post_thumbnail_id(get_the_ID())) { ?>
	<span class="linkss-title"><?php the_title();?></span>
	<?php } ?>
	<article <?php post_class("post-item"); ?>>
			<?php the_content(); ?>
	</article>
	<div id="primary" class="content-area">
		<div class="statistics">
		
		
		<div class="post_statistics"><div><?php echo simple_stats();?><?php counter_user_online()?></div></div>
		
		</div>
		
		
		

	</div><!-- #primary -->
      <?php the_reward(); ?>
	<?php endwhile; ?> 

<?php
get_footer();