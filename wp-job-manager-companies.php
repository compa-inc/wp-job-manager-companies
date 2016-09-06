<?php
/**
 * Plugin Name: WP Job Manager - Company Profiles
 * Plugin URI:  https://github.com/astoundify/wp-job-manager-companies
 * Description: Output a list of all companies that have posted a job, with a link to a company profile.
 * Author:      Astoundify
 * Author URI:  http://astoundify.com
 * Version:     1.3
 * Text Domain: wp-job-manager-companies
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Astoundify_Job_Manager_Companies {

	/**
	 * @var $instance
	 */
	private static $instance;

	/**
	 * @var slug
	 */
	private $slug;

	/**
	 * Make sure only one instance is only running.
	 */
	public static function instance() {
		if ( ! isset ( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Start things up.
	 * @since 1.0
	 */
	public function __construct() {
		if ( ! class_exists( 'WP_Job_Manager' ) ){
			return;
		}
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since 1.0
	 * @return void
	 */
	private function setup_globals() {

		/* Plugin Path */
		$this->plugin_dir = plugin_dir_path( __FILE__ );

		/* Plugin URI */
		$this->plugin_url = plugin_dir_url ( __FILE__ );

		/* The slug for creating permalinks */
		$this->slug = apply_filters( 'wp_job_manager_companies_company_slug', 'company' );
	}

	/**
	 * Setup the default hooks and actions
	 * @since 1.0
	 * @return void
	 */
	private function setup_actions(){

		/* i18n */
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		/* Add Query Var */
		add_filter( 'query_vars', array( $this, 'query_vars' ) );

		/* Add Rewrite Rules */
		add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite_rule' ) );

		/* Set Query */
		add_action( 'pre_get_posts', array( $this, 'posts_filter' ) );

		/* Create Custom Template */
		add_action( 'template_redirect', array( $this, 'template_loader' ) );

		/* Filter Head Title */
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 20 );

		/* Filter Archive Title */
		add_filter( 'get_the_archive_title', array( $this, 'archive_title' ) );

		/* Shortcode [job_manager_companies] */
		add_shortcode( 'job_manager_companies', array( $this, 'job_manager_companies_shortcode' ) );
	}

	/**
	 * Localisation
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wp-job-manager-companies', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Define "company" as a valid query variable.
	 * @since 1.0
	 * @param array $vars The array of existing query variables.
	 * @return array $vars The modified array of query variables.
	 */
	public function query_vars( $vars ) {
		$vars[] = $this->slug;
		return $vars;
	}

	/**
	 * Create the custom rewrite tag, then add it as a custom structure.
	 * @since 1.0
	 * @return obj $wp_rewrite->rules
	 */
	public function add_rewrite_rule( $wp_rewrite ) {

		$wp_rewrite->add_rewrite_tag( '%company%', '(.+?)', $this->slug . '=' );

		$rewrite_keywords_structure = $wp_rewrite->root . $this->slug ."/%company%/";

		$new_rule = $wp_rewrite->generate_rewrite_rules( $rewrite_keywords_structure );

		$wp_rewrite->rules = $new_rule + $wp_rewrite->rules;

		return $wp_rewrite->rules;
	}


	/**
	 * Potentialy filter the query. If we detect the "company" query variable
	 * then filter the results to show job listsing for that company.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param object $query
	 * @return void
	 */
	public function posts_filter( $query ) {
		if ( ! ( get_query_var( $this->slug ) && $query->is_main_query() && ! is_admin() ) )
			return;

		$meta_query = array(
			array(
				'key'   => '_company_name',
				'value' => urldecode( get_query_var( $this->slug ) )
			)
		);

		if ( get_option( 'job_manager_hide_filled_positions' ) == 1 ) {
			$meta_query[] = array(
				'key'     => '_filled',
				'value'   => '1',
				'compare' => '!='
			);
		}

		$query->set( 'post_type', 'job_listing' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'meta_query', $meta_query );
	}


	/**
	 * If we detect the "company" query variable, load our custom template
	 * file. This will check a child theme so it can be overwritten as well.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @return void
	 */
	public function template_loader() {
		global $wp_query;

		if ( ! get_query_var( $this->slug ) )
			return;

		if ( 0 == $wp_query->found_posts )
			locate_template( apply_filters( 'wp_job_manager_companies_404', array( '404.php', 'index.php' ) ), true );
		else
			locate_template( apply_filters( 'wp_job_manager_companies_templates', array( 'single-company.php', 'taxonomy-job_listing_category.php', 'index.php' ) ), true );

		exit();
	}


	/**
	 * Set a page title when viewing an individual company.
	 *
	 * @since WP Job Manager - Company Profiles 1.2
	 *
	 * @param string $title Default title text for current view.
	 * @param string $sep Optional separator.
	 * @return string Filtered title.
	 */
	public function document_title($title) {
		global $paged, $page;
		$sep = apply_filters( 'document_title_separator', '-' );
		if ( ! get_query_var( $this->slug ) )
			return $title;

		$company = urldecode( get_query_var( $this->slug ) );

		$title = get_bloginfo( 'name' );

		$site_description = get_bloginfo( 'description', 'display' );

		if ( $site_description && ( is_home() || is_front_page() ) )
			$title = "$title $sep $site_description";

		$title = sprintf( __( 'Jobs at %s', 'wp-job-manager-companies' ), $company ) . " $sep $title";

		return $title;
	}

	/**
	 * Set Archive Title
	 * this filter is introduce in WP 4.1
	 */
	public function archive_title( $title ) {
		if ( ! get_query_var( $this->slug ) ){
			return $title;
		}

		$company = urldecode( get_query_var( $this->slug ) );
		$title = sprintf( __( 'Jobs at %s', 'wp-job-manager-companies' ), $company );

		return $title;
	}

	/**
	 * Register the `[job_manager_companies]` shortcode.
	 * @since 1.0
	 * @param array $atts
	 * @return string The shortcode HTML output
	 */
	public function job_manager_companies_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'show_letters' => true
		), $atts );

		ob_start();
		wp_enqueue_script( 'jquery-masonry' );
	?>
		<script type="text/javascript">
		jQuery(function($) {
			$('.companies-overview').masonry({
				itemSelector : '.company-group',
				isFitWidth   : true
			});
		});
		</script>
	<?php
		echo $this->build_company_archive( $atts );
		return ob_get_clean();
	}

	/**
	 * Build the shortcode.
	 *
	 * Not very flexible at the moment. Only can deal with english letters.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param array $atts
	 * @return string The shortcode HTML output
	 */
	public function build_company_archive( $atts ) {
		global $wpdb;

		$output = '';
		$companies_raw = $wpdb->get_col(
			"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_company_name'
			 AND p.post_status = 'publish'
			 AND p.post_type = 'job_listing'
			 GROUP BY pm.meta_value
			 ORDER BY pm.meta_value"
		);

		/* Add Company Count */
		$companies = array();
		foreach( $companies_raw as $company ){
			$args = array(
				'post_type'      => 'job_listing',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => '_company_name',
						'value'   => $company,
						'compare' => '=',
					),
					array(
						'key'     => '_filled',
						'value'   => '1',
						'compare' => '!=',
					),
				),
			);
			$result = new WP_Query( $args );
			if( $result->found_posts ){
				$companies[] = array(
					'name'  => $company,
					'label' => "{$company} ({$result->found_posts})",
				);
			}
		}

		/* Group */
		$group = range( 'A', 'Z' );
		$group[] = '0-9';

		$_companies = array();
		foreach ( $companies as $k => $company ) {
			if( in_array( $company['name'][0], range( 'A', 'Z' ) ) ){
				$_companies[ strtoupper( $company['name'][0] ) ][] = $company;
			}
			else{
				$_companies['0-9'][] = $company;
			}
		}


		if ( $atts[ 'show_letters' ] ) {
			$output .= '<div class="company-letters">';

			foreach ( $group as $letter ) {
				$output .= '<a href="#' . $letter . '">' . $letter . '</a>';
			}

			$output .= '</div>';
		}

		$output .= '<ul class="companies-overview">';

		foreach ( $group as $letter ) {
			if ( ! isset( $_companies[ $letter ] ) )
				continue;

			$output .= '<li class="company-group"><div id="' . $letter . '" class="company-letter">' . $letter . '</div>';
			$output .= '<ul>';

			foreach ( $_companies[ $letter ] as $k => $company ) {

				$output .= '<li class="company-name"><a href="' . $this->company_url( $company['name'] ) . '">' . esc_attr( $company['label'] ) . '</a></li>';
			}

			$output .= '</ul>';
			$output .= '</li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Company profile URL. Depending on our permalink structure we might
	 * not output a pretty URL.
	 *
	 * @since WP Job Manager - Company Profiles 1.0
	 *
	 * @param string $company_name
	 * @return string $url The company profile URL.
	 */
	public function company_url( $company_name ) {
		global $wp_rewrite;

		$company_name = rawurlencode( $company_name );

		if ( $wp_rewrite->permalink_structure == '' ) {
			$url = add_query_arg( $this->slug, $company_name, home_url( 'index.php' ) );
		} else {
			$url = user_trailingslashit( home_url( "/{$this->slug}/{$company_name}" ) );
		}

		return esc_url( $url );
	}

}

add_action( 'plugins_loaded', array( 'Astoundify_Job_Manager_Companies', 'instance' ) );
