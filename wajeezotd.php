<?php
/*
Plugin Name: Wajeez OTD
Plugin URI: http://wordpress.org/plugins/wajeez-otd/
Description: A flexible plugin showing posts made on this date in past years.
Version: 1.1.4
Author: Raouf Shabayek, R. Wiles Development, Bashir Shallah
Author URI: http://wajeez.com/
License: GPLv2 or later
*/

/*
Copyright 2013  Wajeez  (email: shabayek@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
function WajeezOTD_lang() {

    $plugin_basename = plugin_basename( __FILE__ );

    $plugin_dirname = dirname($plugin_basename ) . '/languages/';

    load_plugin_textdomain( 'WajeezOTD', false , $plugin_dirname );

}

add_action('plugins_loaded', 'WajeezOTD_lang');

class WajeezOTD extends WP_Widget {

    function WajeezOTD() {
        parent::WP_Widget(false, $name='Wajeez OTD',
            array( 'classname' => 'wajeez-otd', 'description' => __("Show posts on this day in past years.",'WajeezOTD')));

        // Add settings page
        function add_wajeez_settings () {
            add_submenu_page('plugin.php', 'Wajeez OTD Settings','Wajeez OTD Settings', 8, 'wajeez-settings','wajeez_settings');
            add_menu_page( 'Wajeez OTD Settings', 'Wajeez OTD', 8, 'wajeez-settings', 'wajeez_settings');
        }
        add_action ('admin_menu', 'add_wajeez_settings');

        // Add a link to that settings page
        function add_wajeez_link($links) {
            $link = '<a href="admin.php?page=wajeez-settings">'.__('Settings','WajeezOTD').'</a>';
            $links[] = $link;
            return $links;
        }
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin", 'add_wajeez_link');

        // Register settings, new settings must be added here
        function register_wajeez_settings() {
            register_setting('wajeez-settings', 'number-of-posts');
            register_setting('wajeez-settings', 'show-protected-posts');
            register_setting('wajeez-settings', 'number-of-years');
            register_setting('wajeez-settings', 'show-thumbnails');
            register_setting('wajeez-settings', 'adjacent-fallback');
            register_setting('wajeez-settings', 'character-limit');
            register_setting('wajeez-settings', 'show-plugin-link');
        }
        add_action('admin_init', 'register_wajeez_settings');

        // Add CSS
        wp_register_style('wajeez-otd', plugins_url('wajeez.css',__FILE__));
        wp_enqueue_style('wajeez-otd');

        wp_enqueue_script('jquery');

        if(is_admin() and is_rtl())
        {
            wp_register_style('wajeez-otd-ar', plugins_url('admin-rtl.css',__FILE__));
            wp_enqueue_style('wajeez-otd-ar');
        }
        function wajeez_warning() {
            if (get_option('wajeez-message') != 'on') {
                update_option('wajeez-message', 'on');
                echo "<div id='wajeez-warning' class='updated fade'><p><strong>".__('Wajeez OTD was installed successfully.','WajeezOTD')."</strong> ".__('Please go to the Appearance tab to add the Wajeez OTD widget to your blog.','WajeezOTD')."</p></div>";
            }
        }
        add_action('admin_notices', 'wajeez_warning');

    }

    function widget($args, $instance) {
        extract($args);

        // Begin output
        echo $before_widget;

        if ($instance['title'] != '')
            echo $before_title.$instance["title"].$after_title;
        ?>
        <ul class="wajeez-otd">
            <?php
            // Set up loop variables
            $offset = 0;
            $count = 0;

            // Limit the loop through near matches to one week (+/- 3)
            while (abs($offset) < 4) {

                // Get posts
                $now = current_time('timestamp') + $offset*60*60*24;
                $query = new WP_Query;
                $query->init();
                $query->parse_query('monthnum='.date('n',$now).'&day='.date('j',$now));
                $posts = $query->get_posts();

                foreach ($posts as $otd_post) {
                    // Skip posts from the current year.
                    if (mb_substr($otd_post->post_date,0,4,"utf-8") == date('Y',$now)) { continue; }

                    // Skip posts that are older than the year limit
                    if (intval(get_option('number-of-years')) !== 0 &&
                        intval(mb_substr($otd_post->post_date,0,4,"utf-8")) < (intval(date('Y',$now)) - intval(get_option('number-of-years')))) { continue; }

                    // Skip password protected posts, unless the option is enabled.
                    if (!empty($otd_post->post_password) && get_option('show-protected-posts') != 'on') { continue; }

                    // Exit if we hit the post limit
                    if ($count >= intval(get_option('number-of-posts'))) { break; }

                    // Otherwise this post is good, add one to the post count and output the post
                    $count++;
                    ?>
                    <li<?php if ($offset != 0) echo ' class="near-match"'?>>
                        <?php if (get_option('show-thumbnails') == 'on' || get_option('show-plugin-link') === FALSE) { ?>
                        <div class="wajeez-thumb"><?php echo get_the_post_thumbnail( $otd_post->ID, array(48,48)); ?></div>
                        <?php }
                        // Truncate the post title if necessary
                        $titlelength = intval(get_option('character-limit'));
                        $title = $otd_post->post_title;;
                        if ($titlelength != 0 && strlen($title) > $titlelength) {
                            $title = mb_substr($title, 0, $titlelength,"utf-8").'...';
                        } ?>
                        <a href="<?php echo get_permalink($otd_post->ID); ?>"><?php echo $title; ?></a>
                        <div class="wajeez-meta"><?php echo date_i18n(get_option('date_format'),strtotime($otd_post->post_date)); ?></div>
                    </li>
                    <?php
                }
                // Repeat for adjacent days if necessary
                if ($count < intval(get_option('number-of-posts')) && get_option('adjacent-fallback') == 'on') {
                    if ($offset <= 0)
                        $offset--;
                    $offset *= -1;
                } else { break; }
            }


            if ($count == 0) {
                echo __('Sorry, no posts were found on today\'s date','WajeezOTD');
            }

            // Display the link to the plugin, if necessary
            if (get_option('show-plugin-link') == 'on') {
                echo '<li style="text-align: center;"><small >'.__('Add this ','WajeezOTD').' <a target="_blank" href="http://wordpress.org/plugins/wajeez-otd/">'.__('Plugin ','WajeezOTD').'</a> '.__('to your blog. ','WajeezOTD').'</smalle></li>';
            }
            ?>
        </ul>
        <?php

        // End output
        echo $after_widget;
    }

    function form($instance) {
        // Per widget options go here
        ?>
        <p>
            <label for="<?php echo $this->get_field_id("title"); ?>"><?php _e('Title'); ?>:</label>
            <input class="widefat" id="<?php echo $this->get_field_id("title"); ?>" name="<?php echo $this->get_field_name("title"); ?>" type="text" value="<?php echo esc_attr($instance["title"]); ?>" />
        </p>
        <?php
    }

    function update($new_instance, $old_instance) {
        return $new_instance;
    }
}
add_action('widgets_init', create_function('', 'return register_widget("WajeezOTD");'));

// Create settings page, global options go here
function wajeez_settings() {
?>
<div class="wrap">
<div class="wajeezotd-settings" >
<h2><?php _e('Wajeez OTD Settings','WajeezOTD')?></h2>

    <form method="post" action="options.php">
        <?php settings_fields('wajeez-settings'); ?>
        <h3><?php _e('Post Settings','WajeezOTD')?></h3>
        <p>
            <?php
                $numposts = intval(get_option('number-of-posts'));
                if ($numposts === 0) $numposts = 5;
            ?>
            <label for="number-of-posts"><?php _e('Number of posts to display:','WajeezOTD')?></label><br/>
            <input type="text" id="number-of-posts" name="number-of-posts" value="<?php echo $numposts; ?>" />
        </p>
        <p>
            <input type="checkbox" id="adjacent-fallback" name="adjacent-fallback"<?php if (get_option('adjacent-fallback') == 'on') { echo ' checked'; } ?>/>
            <label for="adjacent-fallback"><?php _e('Fill in missing posts with near matches','WajeezOTD')?></label>
        </p>
        <p>
            <input type="checkbox" id="show-protected-posts" name="show-protected-posts"<?php if (get_option('show-protected-posts') == 'on') { echo ' checked'; } ?>/>
            <label for="show-protected-posts"><?php _e('Show password protected posts','WajeezOTD')?></label>
        </p>
        <p>
            <?php
                $years = intval(get_option('number-of-years'));
                if ($years === 0) $years = '';
            ?>
            <label for="number-of-years"><?php _e('Number of years to search: (leave blank for unlimited)','WajeezOTD')?></label><br/>
            <input type="text" id="number-of-years" name="number-of-years" value="<?php echo $years; ?>" />
        </p>
        <h3><?php _e('Display Settings','WajeezOTD')?></h3>
        <p>
            <input type="checkbox" id="show-thumbnails" name="show-thumbnails"<?php if (get_option('show-thumbnails') == 'on' || get_option('show-plugin-link') === FALSE) { echo ' checked'; } ?>/>
            <label for="show-thumbnails"><?php _e('Show post thumbnails','WajeezOTD')?></label>
        </p>
        <p>
            <?php
                $titlelength = intval(get_option('character-limit'));
                if ($titlelength === 0) $titlelength = '';
            ?>
            <label for="character-limit"><?php _e('Character limit for post titles: (leave blank for unlimited)','WajeezOTD')?></label><br/>
            <input type="text" id="character-limit" name="character-limit" value="<?php echo $titlelength; ?>" />
        </p>
        <p>
            <input type="checkbox" id="show-plugin-link" name="show-plugin-link"<?php if (get_option('show-plugin-link') == 'on') { echo ' checked'; } ?>/>
            <label for="show-plugin-link"><?php _e('Show plugin link','WajeezOTD')?></label>
        </p>

        <?php submit_button(); ?>
    </form><br/>
    </div>
    <?php
    $local = get_locale();
    if($local=='ar')
    {
        $feed_url = 'http://www.shabayek.com/blog/feed/';
        $twitter = 'Shabayek';
        $facebook = 'shabayek.blog';
        $blog_name = 'شبايك';
    }
    else
    {
        $feed_url = 'http://wajeez.com/feed/';
        $twitter = 'wajeez';
        $facebook = 'WajeezArticles';
        $blog_name = 'Wajeez';
    }
    ?>
    <div id="wajeez_support_box" >
        <div id="wajeez_donate_box">
            <div class="wajeez_support_title" ><?php _e('Support the development','WajeezOTD')?></div>
            <div class="wajeez_box_content" >
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                    <input type="hidden" name="cmd" value="_donations">
                    <input type="hidden" name="business" value="shabayek@gmail.com">
                    <input type="hidden" name="lc" value="US">
                    <input type="hidden" name="item_name" value="Wajeez OTD">
                    <input type="hidden" name="no_note" value="0">
                    <input type="hidden" name="currency_code" value="USD">
                    <label style="" for="amount"><?php _e('Enter amount in USD:','WajeezOTD')?></label>
                    <br />
                    <input name="amount" id="amount" type="text" />
                    <br />
                    <input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest">
                    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                    <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                </form>
            </div>
        </div>
        <div id="wajeez_follow_box">
            <div class="wajeez_support_title" ><?php _e('Follow us','WajeezOTD')?></div>
            <div class="wajeez_box_content" style="padding-bottom: 3px;" >
                <iframe id="wajeezotd-facebook" src="//www.facebook.com/plugins/likebox.php?href=https%3A%2F%2Fwww.facebook.com%2F<?=$facebook?>&amp;width&amp;height=62&amp;colorscheme=light&amp;show_faces=false&amp;header=false&amp;stream=false&amp;show_border=false" scrolling="no" frameborder="0" style="border:none; overflow:hidden; height:62px;" allowTransparency="true"></iframe>
                <a href="https://twitter.com/<?=$twitter?>" data-size="large" class="twitter-follow-button" data-show-count="false" data-lang="en">Follow @<?=$twitter?></a>
                <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
            </div>
        </div>
        <div id="wajeez_latest_posts_box" >
            <div class="wajeez_support_title" ><?php _e('Latest Posts by ','WajeezOTD')?><?=$blog_name?></div>
            <div class="wajeez_box_content">
                <div id="wajeez_latest_posts" ></div>
            </div>
        </div>
    </div>
    <script>
    var url ='<?=$feed_url?>';
    var total_count = 3;
        jQuery.ajax({
          url      : document.location.protocol + '//ajax.googleapis.com/ajax/services/feed/load?v=1.0&num=10&callback=?&q=' + encodeURIComponent(url),
          dataType : 'json',
          success  : function (data) {
            var wajeez_l_posts_links='<ul>';
            var count = 0;
            if (data.responseData.feed && data.responseData.feed.entries) {
              jQuery.each(data.responseData.feed.entries, function (i, e) {
                wajeez_l_posts_links += '<li><a target="_blank" href="'+e.link+'">'+e.title+'</a></li>';
                count++;

                if(total_count==count)
                return false;

              });
            }
            jQuery('#wajeez_latest_posts').append(wajeez_l_posts_links+'<ul/>');

          }
        });
    </script>
</div>
<?php
}