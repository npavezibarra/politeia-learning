<?php get_header(); ?>
<main class="pcg-archive">
  <h1>Programas Filosóficos</h1>
  <div class="program-list">
  <?php while ( have_posts() ) : the_post(); ?>
    <article>
      <a href="<?php the_permalink(); ?>">
        <?php if ( has_post_thumbnail() ) the_post_thumbnail('medium'); ?>
        <h2><?php the_title(); ?></h2>
      </a>
    </article>
  <?php endwhile; ?>
  </div>
</main>
<?php get_footer(); ?>
