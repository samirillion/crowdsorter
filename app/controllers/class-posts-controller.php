<?php
/**
 * Undocumented class
 *
 * @package Ifm
 */
require_once( IFM_APP . 'views/class-posts-container.php' );
require_once( IFM_APP . 'views/class-edit-post.php' );
require_once( IFM_APP . 'views/partials/class-post-template.php' );
require_once( IFM_APP . 'views/class-new-post.php' );

require_once( IFM_APP . 'models/post.php' );
require_once( IFM_APP . 'models/sorter-factory.php' );
require_once( IFM_APP . 'models/news-aggregator.php' );


class IfmPostsController {

	/**
	 * Define Posts Per Page for Pagination. Eventually set in WordPress Admin.
	 *
	 * @var integer
	 */
	private $posts_per_page = 30;

	/**
	 * Registration function.
	 */
	public static function register() {
		$plugin = new self();

		add_shortcode( 'crowdsortcontainer', array( $plugin, 'create_main' ) );
		add_shortcode( 'ifm-post', array( $plugin, 'create_new_post_template' ) );
		add_shortcode( 'edit-aggpost', array( $plugin, 'render_edit_post_container' ) );

		add_action( 'init', array( $plugin, 'generate_sorter' ) );
		add_action( 'wp_ajax_add_entry_karma', array( $plugin, 'my_user_vote' ) );
		add_action( 'wp_ajax_nopriv_add_entry_karma', array( $plugin, 'redirect_to_login_ajax' ) );
		add_filter( 'query_vars', array( $plugin, 'add_query_vars' ) );
		add_action( 'post_ranking_cron', array( $plugin, 'update_post_rank' ) );
		add_action( 'admin_post_submit_post', array( $plugin, 'submit_post' ) );
		add_action( 'admin_post_nopriv_submit_post', array( $plugin, 'redirect_to_login' ) );
		add_action( 'admin_post_edit_post', array( $plugin, 'edit_post' ) );

		add_filter( 'rest_pre_echo_response', array( $plugin, 'lets_see' ) );

		// Limit media library access
		// add_action( 'wp_ajax_nopriv_more_aggregator_posts', array( $plugin, 'load_more_posts' ) );
		// add_action( 'wp_ajax_more_aggregator_posts', array( $plugin, 'load_more_posts' ) );
		// add_action( 'wp_ajax_addComment', array( $plugin, 'add_comment' ) );
		// add_action( 'wp_ajax_nopriv_addComment', array( $plugin, 'redirect_to_login_ajax' ) );
		// add_action( 'wp_ajax_vote_on_comment', array( $plugin, 'vote_on_comment' ) );
		// add_action( 'wp_ajax_nopriv_vote_on_comment', array( $plugin, 'redirect_to_login_ajax' ) );
		// add_filter( 'ajax_query_attachments_args', array( $plugin, 'crowd_limit_media_upload_to_user' ) );
	}

	public function lets_see( $result ) {
		xdebug_break();
		return $result;
	}

	/**
	 * Undocumented function
	 *
	 * @param array $search_results
	 * @return void
	 */
	public function create_main( $search_results = [] ) {
		if ( ! isset( $_GET['agg_query'] ) ) {
			$query     = IfmPost::sort_posts();
			$pageposts = $query[0];
		} else {
			$pageposts = $this->agg_search_posts();
		}

		// load_template( dirname( __FILE__ ) . '/templates/some-template.php' );

		// $route_template = get_query_template( '404' );
		// add_action( 'template_include', $route_template );
		// ob_start();
		return IfmPostsContainer::render( $pageposts );
		// $html = ob_get_clean();
		// return $html;
		// return trim( IfmPostsContainer::render( $pageposts ) );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function edit_post() {

		if ( get_post_field( 'post_author', $_POST['post-id'] ) !== get_current_user_id() ) {
			wp_safe_redirect( esc_url( add_query_arg( 'agg_post_id', $_POST['post-id'], home_url( 'edit' ) ) ) );
		}

		$the_post = array(
			'ID'         => $_POST['post-id'],
			'post_title' => $_POST['post-title'],
		);

		if ( '' !== $_POST['post-text-content'] ) {
			$the_post['post_content'] = $_POST['post-text-content'];
		} else {
			update_post_meta( $_POST['post-id'], 'aggregator_entry_url', $_POST['post-url'] );
		}
		wp_set_object_terms( $_POST['post-id'], $_POST['post-type'], 'aggpost-type', false );
		wp_update_post( $the_post );

		wp_safe_redirect( home_url( 'fin-forum' ) );
	}

	/**
	 * Limit media upload options on the frontend visual editor to the user's personal media.
	 */
	function crowd_limit_media_upload_to_user( $query ) {
		$user_id = get_current_user_id();
		if ( $user_id && ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'edit_others_posts' ) ) {
			$query['author'] = $user_id;
		}
		return $query;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function render_edit_post_container() {
		IfmEditPost::render();
	}

	/**
	 * Undocumented function
	 */
	public function add_query_vars( $vars ) {
		$vars[] .= 'agg_post_id';
		$vars[] .= 'status';
		$vars[] .= 'user_id';
		$vars[] .= 'aggpost_tax';
		return $vars;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function update_post_rank() {
		newsAggregator::update_temporal_karma();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function agg_search_posts() {
		$query->query_vars['s']              = sanitize_text_field( $_GET['agg_query'] );
		$query->query_vars['posts_per_page'] = $this->posts_per_page;
		$posts                               = [];
		foreach ( relevanssi_do_query( $query ) as $post ) {
				if ( 'aggregator-posts' === $post->post_type ) {
				$posts[] = $post;
					}
		}
		return $posts;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function load_more_posts() {
		$query     = IfmPost::sort_posts();
		$pageposts = $query[0];

		$content = IfmPostTemplate::render( $pageposts );
		return $content;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function generate_sorter() {
		$sorter_factory = new sorterFactory;
		$aggregator     = $sorter_factory->get_sorter( 'News-Aggregator' );

		// add post definition details
		$aggregator->define_post_type();
		$aggregator->define_post_meta();

		// add metadata on post creation
		// eventually add functionality to allow more vars in plugin
		add_action( 'load-post.php', array( $aggregator, 'define_post_meta_on_load' ) );
		add_action( 'load-post-new.php', array( $aggregator, 'define_post_meta_on_load' ) );
		add_action( 'publish_aggregator-posts', array( $aggregator, 'define_meta_on_publish' ) );

	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function redirect_to_login() {
		wp_redirect( home_url( 'member-login' ) );
		die();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function redirect_to_login_ajax() {
		$redirect_url         = home_url( 'member-login' );
		$response[ redirect ] = $redirect_url;
		$response             = json_encode( $response );
		echo $response;
		die();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function my_user_vote() {
		$karma_tracker = new IfmPost;
		$karma_tracker->update_post_karma();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function create_new_post_template() {
		$crowd_post_template = new IfmNewPost;
		$crowd_post_template->render();
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function submit_post() {
		$crowd_posts = new IfmPost;
		$crowd_posts->submit_post();
	}
}

IfmPostsController::register();