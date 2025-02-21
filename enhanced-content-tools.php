<?php
/*
Plugin Name: Enhanced Content Tools
Plugin URI: https://www.github.com/omsekhar08
Description: Custom widgets and AJAX search functionality
Version: 1.0
Author: Om Sekhar
Text Domain: ect
*/



defined('ABSPATH') || exit;

class Enhanced_Content_Tools {
    public function __construct() {
        add_action('widgets_init', [$this, 'register_widgets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_ect_search', [$this, 'ajax_search']);
        add_action('wp_ajax_nopriv_ect_search', [$this, 'ajax_search']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function register_widgets() {
        register_widget('ECT_Recent_Articles_Widget');
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'ect-styles',
            plugins_url('css/ect-styles.css', __FILE__)
        );

        wp_enqueue_script(
            'ect-scripts',
            plugins_url('js/ect-scripts.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('ect-scripts', 'ect_vars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ect-search-nonce')
        ]);
    }

    public function add_admin_menu() {
        add_options_page(
            'Search Settings',
            'Search Settings',
            'manage_options',
            'ect-search-settings',
            [$this, 'settings_page']
        );
    }

    public function settings_init() {
        register_setting('ect_search_settings', 'ect_search_options');

        add_settings_section(
            'ect_search_main',
            'Search Default Settings',
            null,
            'ect-search-settings'
        );

        add_settings_field(
            'default_category',
            'Default Category',
            [$this, 'category_field_callback'],
            'ect-search-settings',
            'ect_search_main'
        );

        add_settings_field(
            'default_number',
            'Number of Posts',
            [$this, 'number_field_callback'],
            'ect-search-settings',
            'ect_search_main'
        );
    }

    public function category_field_callback() {
        $options = get_option('ect_search_options');
        wp_dropdown_categories([
            'show_option_all' => __('All Categories', 'ect'),
            'name' => 'ect_search_options[default_category]',
            'selected' => $options['default_category'] ?? 0
        ]);
    }

    public function number_field_callback() {
        $options = get_option('ect_search_options');
        echo '<input type="number" name="ect_search_options[default_number]" 
              value="' . esc_attr($options['default_number'] ?? 5) . '" min="1" step="1">';
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>Search Settings</h2>
            <form action="options.php" method="post">
                <?php
                settings_fields('ect_search_settings');
                do_settings_sections('ect-search-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function ajax_search() {
        check_ajax_referer('ect-search-nonce', 'nonce');

        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $options = get_option('ect_search_options');

        // Get recent posts if search is empty
        if(empty($search_term)) {
            $transient_key = 'ect_recent_' . md5(serialize($options));
            $results = get_transient($transient_key);

            if(false === $results) {
                $args = [
                    'post_type' => 'post',
                    'posts_per_page' => $options['default_number'] ?? 5,
                    'post_status' => 'publish'
                ];

                if(!empty($options['default_category'])) {
                    $args['cat'] = $options['default_category'];
                }

                $query = new WP_Query($args);
                $results = [];
                
                if($query->have_posts()) {
                    while($query->have_posts()) {
                        $query->the_post();
                        $results[] = [
                            'title' => get_the_title(),
                            'url' => get_permalink()
                        ];
                    }
                }
                
                set_transient($transient_key, $results, HOUR_IN_SECONDS);
            }

            wp_send_json_success($results);
        }

        // Regular search
        $transient_key = 'ect_search_' . md5($search_term);
        $results = get_transient($transient_key);

        if(false === $results) {
            $query = new WP_Query([
                'post_type' => 'post',
                'post_status' => 'publish',
                's' => $search_term,
                'posts_per_page' => 5
            ]);

            $results = [];
            if($query->have_posts()) {
                while($query->have_posts()) {
                    $query->the_post();
                    $results[] = [
                        'title' => get_the_title(),
                        'url' => get_permalink()
                    ];
                }
            }
            
            set_transient($transient_key, $results, HOUR_IN_SECONDS);
        }

        wp_send_json_success($results);
    }
}

class ECT_Recent_Articles_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'ect_recent_articles',
            __('Recent Articles by Category', 'ect'),
            ['description' => __('Display recent articles from selected category', 'ect')]
        );
    }

    public function widget($args, $instance) {
        $query = new WP_Query([
            'category__in' => $instance['category'],
            'posts_per_page' => $instance['number']
        ]);

        echo $args['before_widget'];
        if(!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        if($query->have_posts()) {
            echo '<ul class="ect-articles-list">';
            while($query->have_posts()) {
                $query->the_post();
                echo '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No articles found', 'ect') . '</p>';
        }

        echo $args['after_widget'];
        wp_reset_postdata();
    }

    public function form($instance) {
        $defaults = [
            'title' => __('Recent Articles', 'ect'),
            'category' => 0,
            'number' => 5
        ];
        $instance = wp_parse_args((array) $instance, $defaults);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'ect'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text" value="<?php echo esc_attr($instance['title']); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('category')); ?>"><?php _e('Category:', 'ect'); ?></label>
            <?php wp_dropdown_categories([
                'show_option_all' => __('All Categories', 'ect'),
                'hide_empty' => 0,
                'name' => $this->get_field_name('category'),
                'id' => $this->get_field_id('category'),
                'selected' => $instance['category']
            ]); ?>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('number')); ?>"><?php _e('Number of articles:', 'ect'); ?></label>
            <input class="tiny-text" id="<?php echo esc_attr($this->get_field_id('number')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('number')); ?>"
                   type="number" step="1" min="1" 
                   value="<?php echo absint($instance['number']); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['category'] = absint($new_instance['category']);
        $instance['number'] = absint($new_instance['number']);
        return $instance;
    }
}

function ect_search_form_shortcode() {
    ob_start(); ?>
    <div class="ect-search-container">
        <form role="search" method="get" class="ect-search-form">
            <input type="search" class="ect-search-field" 
                   placeholder="<?php esc_attr_e('Search...', 'ect'); ?>" 
                   autocomplete="off">
            <div class="ect-search-results"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ect_search', 'ect_search_form_shortcode');

new Enhanced_Content_Tools();