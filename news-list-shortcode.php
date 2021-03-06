<?php
/**
 * Plugin Name:     News List Shortcode
 * Plugin URI:      https://github.com/tkc49/news-list-shorcode
 * Description:     News List ShortCode can display a list of news
 * Author:          Takashi Hosoya
 * Author URI:      https://takashihosoya.ninja/
 * Text Domain:     news-list-shortcode
 * Domain Path:     /languages
 * Version:         1.1.1
 *
 * @package         News_List_Shortcode
 */

define( 'NEWS_LIST_SHORTCODE_URL',  plugins_url( '', __FILE__ ) );
define( 'NEWS_LIST_SHORTCODE_PATH', dirname( __FILE__ ) );

$NewsListShortCode = new NewsListShortCode();
$NewsListShortCode->register();


class NewsListShortCode {

    private $version = '';
    private $langs   = '';
    private $nonce   = 'news_list_shortcode_';

    private $post_type = 'news';
    private $taxonomy = 'news_genre';

    function __construct()
    {
        $data = get_file_data(
            __FILE__,
            array( 'ver' => 'Version', 'langs' => 'Domain Path' )
        );
        $this->version = $data['ver'];
        $this->langs   = $data['langs'];

    }

    /**************************************
     *
     *  Plugin Active
     *
     ***************************************/
    public function register()
    {
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 1 );
    }

    /**************************************
     *
     *  Plugins Loaded
     *
     ***************************************/
    public function plugins_loaded()
    {
        load_plugin_textdomain(
            'news-list-shortcode-bk',
            false,
            dirname( plugin_basename( __FILE__ ) ).$this->langs
        );

        add_action('admin_menu', array( $this, 'news_list_shortcode_meta_box_output') );
        add_action('save_post', array( $this, 'new_list_shortcode_save_post'));


        add_action( 'init', array( $this, 'create_news_post_type') );
        add_shortcode( 'newslist', array( $this, 'newslist_func') );

    }

    public function news_list_shortcode_meta_box_output(){
        add_meta_box('news_list_shortcode_page', 'Display Date', array($this, 'news_list_shortcode_meta_box_inside'), $this->post_type, 'normal', 'low' );
    }

    public function news_list_shortcode_meta_box_inside( $post ){

        $news_list_shortcode_display_date = get_post_meta( $post->ID, 'news_list_shortcode_display_date',true );
        echo '<input type="date" id="news_list_shortcode_display_date" name="news_list_shortcode_display_date" value="'.$news_list_shortcode_display_date.'"  />';

    }

    public function new_list_shortcode_save_post( $post_id ){

        $post_type = get_post_type($post_id);

        if ( "news" != $post_type ) return;

        if ( isset( $_POST['news_list_shortcode_display_date'] ) ) {
            update_post_meta( $post_id, 'news_list_shortcode_display_date', sanitize_text_field( $_POST['news_list_shortcode_display_date'] ) );
        }else{
            delete_post_meta( $post_id, 'news_list_shortcode_display_date' );
        }

    }

    /**
     * ShortCode.
     *
     * @param  $atts
     * @return $html
     */
    public function newslist_func( $atts ){

        extract(shortcode_atts(array(
            'count' => -1,
        ), $atts));

        $args = array(
            'post_type' => $this->post_type,
            'posts_per_page' => $count,
        );

        $the_query = new WP_Query( $args );

        if ( $the_query->have_posts() ) {
            $html = "";

            $news_list_shortcode_list_before = apply_filters( 'news_list_shortcode_list_before', '<ul class="newsList">');
            $html .= $news_list_shortcode_list_before;

            while ( $the_query->have_posts() ) {
                $the_query->the_post();

                // datetime
                if( !empty( get_post_meta( $the_query->post->ID, 'news_list_shortcode_display_date', true ) ) ){
                    $date = get_post_meta( $the_query->post->ID, 'news_list_shortcode_display_date', true );

                }else{
                    $date = get_the_time('Y/m/d');
                }

                $datetime = date_i18n( 'Y/m/d', strtotime( $date ) );
                $datetime_disp = date_i18n( get_option( 'date_format' ), strtotime( $date ) );

                // term
                $term_name_join = "";
                $terms = get_the_terms( $the_query->post, $this->taxonomy );
                if ( $terms && ! is_wp_error( $terms ) ) {

                    $term_name = array();
                    foreach ( $terms as $term ) {
                        $term_name[] = esc_html( $term->name );
                    }

                    $news_list_shortcode_term_before = apply_filters( 'news_list_shortcode_term_before', '<span class="newsList_item_term">');
                    $news_list_shortcode_term_after = apply_filters( 'news_list_shortcode_term_after', '</span>');

                    $term_name_join = $news_list_shortcode_term_before . join( $news_list_shortcode_term_after.$news_list_shortcode_term_before , $term_name ).$news_list_shortcode_term_after;
                }


                // list
                $news_list_shortcode_list_item = apply_filters( 'news_list_shortcode_list_item', '<li class="newsList_item"><time class="newsList_item_date" datetime="%date_time%">%post_date%</time>%post_category%<a class="newsList_item_link" href="%link%" title="%link_title%">%post_title%</a></li>' );


                $news_list_shortcode_list_item = str_replace('%date_time%', esc_attr( $datetime ), $news_list_shortcode_list_item);
                $news_list_shortcode_list_item = str_replace('%post_date%', esc_html( $datetime_disp ), $news_list_shortcode_list_item);
                $news_list_shortcode_list_item = str_replace('%post_category%', $term_name_join, $news_list_shortcode_list_item);
                $news_list_shortcode_list_item = str_replace('%link%', esc_url( apply_filters( 'the_permalink', get_permalink() ) ), $news_list_shortcode_list_item);
                $news_list_shortcode_list_item = str_replace('%link_title%', esc_attr( get_the_title() ), $news_list_shortcode_list_item);
                $news_list_shortcode_list_item = str_replace('%post_title%', esc_html( apply_filters( 'the_title', get_the_title() ) ), $news_list_shortcode_list_item);

                $html .= $news_list_shortcode_list_item;

            }

            $news_list_shortcode_list_after = apply_filters( 'news_list_shortcode_list_after', '</ul>');
            $html .= $news_list_shortcode_list_after;

            // Archve List
            $news_list_shortcode_archive_link =  apply_filters( 'news_list_shortcode_archive_link', '<a href="%archive_link%" class="newsList_archiveLink">'.__( 'News List' ).'</a>' );
            $news_list_shortcode_archive_link = str_replace('%archive_link%', esc_url( get_post_type_archive_link( $this->post_type ) ), $news_list_shortcode_archive_link );
            $html .= $news_list_shortcode_archive_link;

            wp_reset_postdata();

        }else{

            // Not Fund.
            $html =	"<p>"._e( 'Sorry, There is no news.' )."</p>";
        }


        return $html;

    }


    /**
     * Register custom post type.
     *
     * @param  none
     * @return none
     */
    public function create_news_post_type()
    {

        // Create News Post Type
        $labels = array(
            'name'               => _x( 'News', 'post type general name', 'news-list-shortcode-bk' ),
            'singular_name'      => _x( 'News', 'post type singular name', 'news-list-shortcode-bk' ),
            'menu_name'          => _x( 'News', 'admin menu', 'news-list-shortcode-bk' ),
            'name_admin_bar'     => _x( 'News', 'add new on admin bar', 'news-list-shortcode-bk' ),
            'add_new'            => _x( 'Add New', 'News', 'news-list-shortcode-bk' ),
            'add_new_item'       => __( 'Add New News', 'news-list-shortcode-bk' ),
            'new_item'           => __( 'New News', 'news-list-shortcode-bk' ),
            'edit_item'          => __( 'Edit News', 'news-list-shortcode-bk' ),
            'view_item'          => __( 'View News', 'news-list-shortcode-bk' ),
            'all_items'          => __( 'All News', 'news-list-shortcode-bk' ),
            'search_items'       => __( 'Search News', 'news-list-shortcode-bk' ),
            'parent_item_colon'  => __( 'Parent News:', 'news-list-shortcode-bk' ),
            'not_found'          => __( 'No News found.', 'news-list-shortcode-bk' ),
            'not_found_in_trash' => __( 'No News found in Trash.', 'news-list-shortcode-bk' )
        );

        $args = array(
            'labels'             	=> $labels,
            'public'             	=> true,
            'publicly_queryable' 	=> true,
            'show_ui'            	=> true,
            'show_in_menu'       	=> true,
            'query_var'          	=> true,
            'rewrite'            	=> false,
            'capability_type'    	=> 'post',
            'has_archive'        	=> true,
            'hierarchical'       	=> false,
            'menu_position'      	=> null,
            'supports'				=> array( 'title', 'editor', 'author', 'revisions' ),
        );

        register_post_type(
            $this->post_type,
            $args
        );

        // Create News Category Taxonomy
        $labels = array(
            'name'				=> _x( 'Genres', 'taxonomy general name', 'news-list-shortcode-bk' ),
            'singular_name'		=> _x( 'Genre', 'taxonomy singular name', 'news-list-shortcode-bk' ),
            'search_items'		=> __( 'Search Genres', 'news-list-shortcode-bk' ),
            'all_items'			=> __( 'All Genres', 'news-list-shortcode-bk' ),
            'parent_item'		=> __( 'Parent Genre', 'news-list-shortcode-bk' ),
            'parent_item_colon' => __( 'Parent Genre:', 'news-list-shortcode-bk' ),
            'edit_item'			=> __( 'Edit Genre', 'news-list-shortcode-bk' ),
            'update_item'		=> __( 'Update Genre', 'news-list-shortcode-bk' ),
            'add_new_item'		=> __( 'Add New Genre', 'news-list-shortcode-bk' ),
            'new_item_name'		=> __( 'New Genre Name', 'news-list-shortcode-bk' ),
            'menu_name'         => __( 'Genre', 'news-list-shortcode-bk' ),
        );


        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui' => true,
            'query_var' => true,
            'rewrite' => false,
        );

        register_taxonomy(
            $this->taxonomy,
            array( $this->post_type ),
            $args
        );

    }

}
