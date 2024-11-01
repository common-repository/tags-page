<?php
/*
Plugin Name: Tags Page
Plugin URI: http://wordpress.org/plugins/tags-page/
Description: Adds a table listing all tags registered on your website.
Version: 1.3.1
Author: Honza Skypala
Author URI: http://www.honza.info
License: WTFPL license applies
*/

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

class TagsPage {
  const version = "1.3.1";
  private static $init_table = false;

  public function __construct() {
    add_action('init', create_function('', 'load_plugin_textdomain("tags-page", false, basename(dirname(__FILE__)) . "/lang/");'));
    add_action('admin_init', array($this, 'version_upgrade'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    add_shortcode('get_tags', array($this, 'shortcode'));
    add_action('in_widget_form', array(__CLASS__,'widget_config'), 10, 3 );
    add_filter('widget_display_callback', array($this, 'widget_display'), 10, 3);
    add_filter('widget_update_callback', array($this, 'widget_update'), 10, 4);
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this , 'filter_plugin_actions'));
    add_action('parse_request', array($this, 'parse_request'));
  }

  public static function widget_config($widget, $return, $instance) {
    if ($widget->id_base == "tag_cloud") {
      if (!isset($instance['tags_page_caption']))
        $instance['tags_page_caption'] = __('(all tags)', 'tags-page');
      if (!isset($instance['tags_page_url'])) {
        $instance['tags_page_url'] = "/" . __('all-tags', 'tags-page') . "/";
        if (($home = get_option('home')) != FALSE && preg_match("#^https?://[a-z0-9_\.:]+/.+#", $home))
          $instance['tags_page_url'] = $home . $instance['tags_page_url'];  // home is located in subfolder of the domain
      }
      printf('<div id="%1$s"%2$s>', $widget->get_field_id('tags_page_config'), (isset($instance['taxonomy']) && $instance['taxonomy'] != 'post_tag') ? ' style="display:none"' : '');
      printf('<p><label for="%1$s">%4$s</label><span class="tags-page-config"><input type="url" class="widefat" id="%1$s" name="%2$s" value="%3$s" /><span><input type="button" class="button button-small" value="%5$s" for="%1$s" /></span></span></p>', $widget->get_field_id('tags_page_url'), $widget->get_field_name('tags_page_url'), isset($instance['tags_page_url']) ? $instance['tags_page_url'] : '', __('Tags page URL:', 'tags-page'), __('link', 'tags-page'));
      printf('<p><label for="%1$s">%4$s</label><input type="text" class="widefat" id="%1$s" name="%2$s" value="%3$s" /></p>', $widget->get_field_id('tags_page_caption'), $widget->get_field_name('tags_page_caption'), $instance['tags_page_caption'], __('Tags page caption:', 'tags-page'));
      printf('</div>');
    }
  }

  public static function widget_update($instance, $new_instance, $old_instance, $widget) {
    if ($widget->id_base == "tag_cloud" && $instance['taxonomy'] == 'post_tag') {
      $instance['tags_page_url']     = $new_instance['tags_page_url'];
      $instance['tags_page_caption'] = $new_instance['tags_page_caption'];
    }
    return $instance;
  }

  public function enqueue_scripts() {
   	$suffix = SCRIPT_DEBUG ? '' : '.min';

    wp_register_style('tags-page', plugins_url("/css/tags-page$suffix.css", __FILE__));
    wp_enqueue_style('tags-page');

    wp_register_script('webtoolkit.sortabletable', plugins_url("/js/webtoolkit.sortabletable$suffix.js", __FILE__), array(), false, true);
    wp_enqueue_script('jquery');
  }

  public function admin_enqueue_scripts($hook) {
    if($hook != 'widgets.php')
      return;
   	$suffix = SCRIPT_DEBUG ? '' : '.min';
    wp_enqueue_style('tags-page-admin', plugins_url("/css/admin$suffix.css", __FILE__));
    wp_enqueue_script('tags-page-admin', plugins_url("/js/admin$suffix.js", __FILE__), array('jquery', 'wplink'), false, true);
    wp_enqueue_style('editor-buttons');
    require_once(ABSPATH . WPINC . '/class-wp-editor.php');
    add_action('admin_footer', create_function('', '_WP_Editors::wp_link_dialog();'));
  }

  public function widget_display($instance, $widget, $args) {
    if ($widget->id_base != "tag_cloud" || $instance['taxonomy'] != 'post_tag' || $instance['tags_page_url'] == "" || $instance['tags_page_caption'] == "")
      return $instance;
    $args['after_widget'] = '<a href="' . $instance['tags_page_url'] . '" class="all-tags-link">' . $instance['tags_page_caption'] . '</a>'
                            . $args['after_widget'];
    $widget->widget($args, $instance);
    return false;
  }

  public function shortcode($atts) {
    extract( shortcode_atts(array(
  		'pagesize' => false
	  ), $atts, 'get_tags'));
	  
  	$tag_caption = __('Tag', 'tags-page');
  	$count_caption = __('Count', 'tags-page');

  	$ret = sprintf('<table class="tags_table">
  	          <thead>
  	            <tr>
  	              <th class="tags_table_column_tag_caption">%1$s</th>
  			          <th class="tags_table_column_count_caption">%2$s</th>
  		          </tr>
  	          </thead>
  	          <tbody>', $tag_caption, $count_caption);
    $tags = get_tags();
    if ($tags) {
    	$view_link = __("View all posts in %s", 'tags-page');
    	if (!isset($atts['pagesize']) || !$atts['pagesize'] || is_nan($atts['pagesize']) || $atts['pagesize'] < 1) {
      	$start = 0;
      	$end = count($tags);
      } else {
      	$start = 0;
      	if (preg_match("#^(.+)/page/(\d+)#i", $_SERVER["REQUEST_URI"], $match)) {
      	  $start = ($match[2] - 1) * $atts['pagesize'];
      	  if ($start > count($tags))
      	    $start = floor(count($tags) / $atts['pagesize']) * $atts['pagesize'];
      	}
      	$end = $start + $atts['pagesize'];
      	if ($end > count($tags)) 
      	  $end = count($tags);
      }
      for ($i = $start; $i < $end; $i++) {
        $tag = $tags[$i];
      	$ret .= sprintf('<tr>
      	           <td class="tags_table_column_tag"><a href="%3$s" title="%4$s">%1$s</a></td>
      	           <td class="tags_table_column_count">%2$d</td>
      	         </tr>', $tag->name, $tag->count, get_tag_link($tag->term_id), sprintf($view_link, $tag->name));
      }
    }

  	$ret .= sprintf('  </tbody>
               <tfoot>
  		           <tr>
  	              <th class="tags_table_column_tag_caption">%1$s</th>
  			          <th class="tags_table_column_count_caption">%2$s</th>
  		           </tr>
  	           </tfoot>
  	         </table>', $tag_caption, $count_caption);
  	
  	if ($start > 0 || $end < count($tags)) { // pagination bottom
  	  $page = ceil($end / $atts['pagesize']);
  	  $page_count = ceil(count($tags) / $atts['pagesize']);
  	  $ret .= '<div class="tags-page-pagination">';
  	  $label = sprintf(__('Page %1$s of %2$s', 'tags-page'), $page, $page_count);
  	  $ret .= "<span>$label</span>";
  	  $pstart = $page <= 3 ? 1 : $page - 2;
  	  $pend   = $page + 3 >= $page_count ? $page_count : $page + 2;
  	  if (empty($match)) {
  	    $base_uri = $_SERVER["REQUEST_URI"];
  	    if (substr($base_uri, -1) == "/")
  	      $base_uri = substr($base_uri, 0, strlen($base_uri) - 1);
  	    $match = array(1 => $base_uri);
  	  }
  	  if ($pstart > 1)
  	    $ret .= sprintf('<a href="%1$s" class="first-page">1</a> … ', $match[1]);
  	  for ($i = $pstart; $i <= $pend; $i++)
  	    if ($page == $i)
  	      $ret .= sprintf('<span class="current-page">%1$d</span>', $i);
  	    else
  	      $ret .= sprintf('<a href="%1$s/page/%2$d">%2$d</a>', $match[1], $i);
  	  if ($pend < $page_count)
  	    $ret .= sprintf(' … <a href="%1$s/page/%2$d" class="last-page">%2$d</a>', $match[1], $page_count);
  	  $ret .= '</div>';
  	}

    wp_enqueue_script('webtoolkit.sortabletable');
    if (!TagsPage::$init_table) { // init table sorting in page footer, but only once
      add_action('wp_footer', array($this, 'footer'));
      TagsPage::$init_table = true;
    }

  	return $ret;
  }
  
  public function footer() {
  	echo '<script type="text/javascript">
  	           /* <![CDATA[ */
  				   	 jQuery(document).ready(function($) {
    				   	 $(".tags_table").each(function() {
    				   	   SortableTable(this);
    				   	 });
  				   	 });
  				   	 /* ]]> */
  					</script>';
  }

  public function version_upgrade() {
    $registered_version = get_option('tags_page_version', '0');
    if (version_compare($registered_version, self::version, '<')) {
      if (get_option('tags_page_options') !== false)  // version 1.0 detected
        update_option('tags_page_display_options_moved', true);
      if (version_compare($registered_version, '1.1', '<')) {
        // copy tags_page_url from plugin v1.0 and fill-in default tags_page_caption
        $widget_tag_cloud_option = get_option('widget_tag_cloud', '');
        if (is_array($widget_tag_cloud_option)) {
          foreach ($widget_tag_cloud_option as &$tag_cloud) {
            if (is_array($tag_cloud) && isset($tag_cloud['taxonomy']) && $tag_cloud['taxonomy'] == 'post_tag') {
              if (!isset($tag_cloud['tags_page_caption']))
                $tag_cloud['tags_page_caption'] = __('(all tags)', 'tags-page');
              $tags_page_options = get_option('tags_page_options', '');
              if (is_array($tags_page_options) && isset($tags_page_options['tags_page_url']) && !isset($tag_cloud['tags_page_url']))
                $tag_cloud['tags_page_url'] = $tags_page_options['tags_page_url'];
            }
          }
        }
        update_option('widget_tag_cloud', $widget_tag_cloud_option);
        delete_option('tags_page_options');
      }
      update_option('tags_page_version', self::version);
    }
    add_action('admin_enqueue_scripts', array($this, 'admin_pointers_header'));
  }

  public function filter_plugin_actions($links) {
		array_unshift($links, sprintf('<a href="%1$s">%2$s</a>', admin_url('widgets.php'), __('Settings')));
		return $links;
	}

  public function admin_pointers_header() {
     if ($this->admin_pointers_check()) {
        add_action('admin_print_footer_scripts', array($this, 'admin_pointers_footer'));

        wp_enqueue_script('wp-pointer');
        wp_enqueue_style('wp-pointer');
     }
  }

  private function admin_pointers_check() {
     $admin_pointers = $this->admin_pointers();
     foreach ($admin_pointers as $pointer => $array) {
        if ($array['active'])
           return true;
     }
  }

  public function admin_pointers_footer() {
    $admin_pointers = $this->admin_pointers();
    ?>
    <script type="text/javascript">
    /* <![CDATA[ */
    (function($) {
       <?php
       foreach ($admin_pointers as $pointer => $array) {
          if ($array['active']) {
             ?>
             $( '<?php echo $array['anchor_id']; ?>' ).pointer( {
                content: '<?php echo $array['content']; ?>',
                position: {
                edge: '<?php echo $array['edge']; ?>',
                align: '<?php echo $array['align']; ?>'
             },
                close: function() {
                   $.post( ajaxurl, {
                      pointer: '<?php echo $pointer; ?>',
                      action: 'dismiss-wp-pointer'
                   } );
                }
             } ).pointer('open');
             <?php
          }
       }
       ?>
    } )(jQuery);
    /* ]]> */
    </script>
    <?php
  }

  private function admin_pointers() {
    $result = array();
    $dismissed = explode( ',', (string) get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));
    $version = '1_0'; // replace all periods in 1.0 with an underscore
    $prefix = 'tags_page_admin_pointers' . $version . '_';

    if (get_option('tags_page_display_options_moved')) {
      $new_pointer_content = '<h3>' . __('Tags Page settings moved', 'tags-page') . '</h3>';
      $new_pointer_content .= '<p>' . __('Tags Page settings are moved directly into Tag&nbsp;Cloud widget options, please check Appearance&nbsp;&rarr;&nbsp;Widgets. There is also new option for link caption.', 'tags-page') . '</p>';

      $result[$prefix . 'options_moved'] = array(
           'content' => $new_pointer_content,
           'anchor_id' => '#menu-appearance',
           'edge' => 'left',
           'align' => 'left',
           'active' => ( ! in_array( $prefix . 'options_moved', $dismissed ) )
        );
    }

    return $result;
  }

  /* Generate virtual All-Tags page, if it is requested and there is none in the system */

  public function parse_request(&$wp) {
    if (!empty($wp->query_vars['pagename']))
      $page = $wp->query_vars['pagename'];
    else if (!empty($wp->query_vars['category_name']))
      $page = $wp->query_vars['category_name'];

    if (!$page)
      return; // page isn't permalink

    if (($home = get_option('home')) == FALSE)
      return;  // home not set???

    $tags_page_url = '';

    $url_check = false;
    $widget_tag_cloud_option = get_option('widget_tag_cloud', '');
    if (is_array($widget_tag_cloud_option)) {
      foreach ($widget_tag_cloud_option as &$tag_cloud) {
        if (is_array($tag_cloud) && isset($tag_cloud['taxonomy']) && $tag_cloud['taxonomy'] == 'post_tag' && isset($tag_cloud['tags_page_url'])) {
          $tags_page_url = $tag_cloud['tags_page_url'];
          if (substr($tags_page_url, 0, strlen($home)) == $home)
            $tags_page_url = substr($tags_page_url, strlen($home));
          if (substr($tags_page_url, 0, 1) == "/")
            $tags_page_url = substr($tags_page_url, 1);
          if (substr($tags_page_url, -1) == "/")
            $tags_page_url = substr($tags_page_url, 0, strlen($tags_page_url) - 1);
          if (preg_match("#^$tags_page_url$#", $page)) {
            $url_check = true;
            break;
          }
        }
      }
    }

    if (!$url_check)
       return;

    global $wpdb;
    if ($wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_name='$tags_page_url' AND post_status='publish'" ) > 0)
      return; // there is a real (non-virtual) page with this slug, so we quite (the real page displays)

    // setup hooks and filters to generate virtual movie page
    add_action('template_redirect', array($this, 'virtual_page_template'));
    add_filter('the_posts', array($this, 'virtual_page_content'));

    // now that we know it's my page,
    // prevent shortcode content from having spurious <p> and <br> added
    remove_filter('the_content', 'wpautop');
  }

  public function virtual_page_content($posts) {
    // have to create a dummy post as otherwise many templates
    // don't call the_content filter
    global $wp, $wp_query;

    //create a fake post intance
    $p = new stdClass;
    // fill $p with everything a page in the database would have
    $p->ID = -1;
    $p->post_author = 1;
    $p->post_date = current_time('mysql');
    $p->post_date_gmt =  current_time('mysql', $gmt = 1);
    $p->post_content = "[get_tags]";
    $p->post_title = __('All Tags', 'tags-page');
    $p->post_excerpt = '';
    $p->post_status = 'publish';
    $p->ping_status = 'closed';
    $p->post_password = '';
    $p->post_name = __('all-tags', 'tags-page'); // slug
    $p->to_ping = '';
    $p->pinged = '';
    $p->modified = $p->post_date;
    $p->modified_gmt = $p->post_date_gmt;
    $p->post_content_filtered = '';
    $p->post_parent = 0;
    $p->guid = get_home_url('/' . $p->post_name); // use url instead?
    $p->menu_order = 0;
    $p->post_type = 'page';
    $p->post_mime_type = '';
    $p->comment_status = 'closed';
    $p->comment_count = 0;
    $p->filter = 'raw';
    $p->ancestors = array(); // 3.6

    // reset wp_query properties to simulate a found page
    $wp_query->is_page = TRUE;
    $wp_query->is_singular = TRUE;
    $wp_query->is_home = FALSE;
    $wp_query->is_archive = FALSE;
    $wp_query->is_category = FALSE;
    unset($wp_query->query['error']);
    $wp->query = array();
    $wp_query->query_vars['error'] = '';
    $wp_query->is_404 = FALSE;

    $wp_query->current_post = $p->ID;
    $wp_query->found_posts = 1;
    $wp_query->post_count = 1;
    $wp_query->comment_count = 0;
    // -1 for current_comment displays comment if not logged in!
    $wp_query->current_comment = null;
    $wp_query->is_singular = 1;

    $wp_query->post = $p;
    $wp_query->posts = array($p);
    $wp_query->queried_object = $p;
    $wp_query->queried_object_id = $p->ID;
    $wp_query->current_post = $p->ID;
    $wp_query->post_count = 1;

    return array($p);
  }

  public function virtual_page_template() {
    get_template_part('page', 'tags-page');
    exit;
  }
}

$wp_TagsPage = new TagsPage();
?>