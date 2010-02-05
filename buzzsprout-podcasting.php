<?php
/*
Plugin Name: Buzzsprout Podcasting
Plugin URI: http://www.buzzsprout.com/wordpress
Description: This plugin fetches content from a Buzzsprout feed URL, from which user can pick an episode and add it into the post
Version: 1.0
Author: Buzzsprout
Author URI: http://www.buzzsprout.com
*/

global $wpdb, $buzzp_plugin_slug, $buzzp_plugin_name, $buzzp_plugin_dir, $buzzp_text_domain;

$buzzp_plugin_slug = 'buzzsprout-podcasting';
$buzzp_plugin_name = 'Buzzsprout Podcasting';
$buzzp_plugin_dir = get_settings('siteurl') . "/wp-content/plugins/$buzzp_plugin_slug/";
$buzzp_text_domain = "$buzzp_plugin_slug-domain";

define('BUZZSPROUT_SHORTTAG', 'buzzsprout');
define('BUZZP_ALL_EPISODES', 99999);

load_plugin_textdomain($buzzp_text_domain);

buzzp_hook();

/**
* @desc     Detects the edit and add-new page to add necessary scripts
* @return   void
*/
function buzzp_hook()
{
    global $pagenow, $buzzp_plugin_dir;
    if (($pagenow == 'post.php' && $_GET['action'] == 'edit') || $pagenow == 'post-new.php') 
    {
        wp_enqueue_script('buzzp-admin-js', "$buzzp_plugin_dir/js/box-init.js", array('jquery'), '1.0', true);
    }
}

/**
* @desc     Hooks into init to handle the options saving
* @return   void
*/
function buzzp_request_handler()
{
    global $buzzp_plugin_slug, $buzzp_text_domain;
    
    // if $_POST['buzzp_action'] is not set, this request doesn't belong to us!
    // let it go
    if (!isset($_REQUEST['buzzp_action'])) return false;
    
    switch($_REQUEST['buzzp_action'])
    {
        case 'options':
            buzzp_save_options();
            break;
        case 'box':
            buzzp_load_box();
            break;
        default:
            return false;
    }
    exit();
}

add_action('init', 'buzzp_request_handler', 5);

/**
* @desc     This function creates the HTML for thickbox's iframe
*           which lets user pick an item.
*           Upon picking, the item is transferred to parent's document to handle.
*/
function buzzp_load_box()
{
    global $buzzp_text_domain, $buzzp_plugin_slug, $buzzp_plugin_dir, $buzzp_plugin_name;    
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php _e("$buzzp_plugin_name - Pick an episode", $buzzp_text_domain) ?></title>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo "$buzzp_plugin_dir/css/admin.css"?>" />
</head>
<body class="box">
<?php
    if (!$buzzp_options = get_option($buzzp_plugin_slug))
    {
        echo '<p class="major-info error">', __("You have not specified a valid Buzzsprout feed URL yet. 
        Please use the form under <a href=\"options-general.php?page=$buzzp_plugin_slug.php\" target=\"_blank\">Settings &raquo; $buzzp_plugin_name", $buzzp_text_domain), '</a> to do so.</p>';
    }
    else if(!buzzp_is_valid_buzz_feed_uri($buzzp_options['feed-uri']))
    {
        echo '<p class="major-error error">', __("A valid Buzzsprout feed URL cannot be found. 
        Please use the form under <a href=\"options-general.php?page=$buzzp_plugin_slug.php\" target=\"_blank\">Settings &raquo; $buzzp_plugin_name", $buzzp_text_domain), '</a> to update your settings.</p>';
    }
    else
    {
        include_once(ABSPATH . WPINC . '/feed.php');
        $rss = fetch_feed($buzzp_options['feed-uri']);
        $maxitems = $rss->get_item_quantity($buzzp_options['number-episodes']); 
        $rss_items = $rss->get_items(0, $maxitems); 
        
        echo '<h2>', __('Pick an item', $buzzp_text_domain), '</h2>';
?>
    <ul>
<?php 
        if ($maxitems == 0)
        { 
            echo '<li class="error">', __('No feed items can be retrieved.', $buzzp_text_domain), '</li>';
        }
        else
        {
            // Loop through each feed item and display each item as a hyperlink.
            
            foreach ($rss_items as $item)
            {
?>
        <li>
            <a class="buzzp-item" short-tag='<?php echo buzzp_create_short_tag($item->get_permalink(), $buzzp_options['include-flash'])?>'
                href="#"
                title="<?php _e('Click to add this episode into the post', $buzzp_text_domain)?>">
                <?php echo $item->get_title(); ?></a>
        </li>
<?php 
            }
        }
?>
    </ul>

<?php
    }
?>
<script type="text/javascript" src="../wp-includes/js/jquery/jquery.js"></script>
<script type="text/javascript" src="<?php echo "$buzzp_plugin_dir/js/admin-onload.js"?> "></script>
</body>
</html>
<?php
}

/**
* Determnines if a URL is a valid Buzzsprout one
* 
* @param mixed $uri
* @return int
*/
function buzzp_is_valid_buzz_feed_uri($uri)
{
    if (!trim($uri)) return false;
    return preg_match('|^http(s)?://(www\.)?buzzsprout\.com/[0-9]+\.rss$|i', $uri);
}

/**
* @desc     Saves the plugin settings
* @return   void
*/
function buzzp_save_options()
{
    global $buzzp_text_domain, $buzzp_plugin_slug;
    
    $errors = array();

    $feed_uri = trim($_POST['feed-uri']);
    
    // a quick RegEx to determine if the RSS feed is a valid Buzzsprout one
    if (!buzzp_is_valid_buzz_feed_uri($feed_uri))
    {
        $errors[] = __('Invalid Buzzsprout feed address provided.', $buzzp_text_domain);
    } 
       
    if (count($errors))
    {
        die ('<ul><li>' . implode('</li><li>', $errors) . '</li></ul>');
    }
    
    $buzzp_otions = array(
        'feed-uri'          => $feed_uri,
        'include-flash'     => isset($_POST['include-flash']) ? 1 : 0,
        'number-episodes'   => intval($_POST['number-episodes']),
    );
    
    // now save
    update_option($buzzp_plugin_slug, $buzzp_otions);
    
    die('<p>' . __('Settings saved.', $buzzp_text_domain) . '</p>');
}

/**
* @desc     Displays the options form for the plugin
* @return   void
*/
function buzzp_options_form()
{
    global $wpdb, $buzzp_plugin_slug, $buzzp_plugin_dir, $buzzp_plugin_name, $buzzp_text_domain;
    if (!$buzzp_otions = get_option($buzzp_plugin_slug))
    {
        $buzzp_otions = array(
            'feed-uri'          => false,
            'include-flash'     => true,
            'number-episodes'   => 10,
        );
    }
?>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo "$buzzp_plugin_dir/css/admin.css"?>" />
<script type="text/javascript" src="<?php echo $buzzp_plugin_dir?>js/admin-onload.js"></script>

<div class="wrap buzzp">
    <div id="icon-options-buzzp" class="icon32"><br /></div>
    <h2><?php _e("$buzzp_plugin_name", $buzzp_text_domain)?></h2>
    
    <p>
        <?php _e("Buzzsprout is the only solution you need for publishing, hosting, promoting and tracking your podcast on the web. It eliminates the hassles and technical know-how required with self-managed podcast publishing. Whether you're just starting out or have been podcasting for years, Buzzsprout is the easiest way to get your podcast online.", $buzzp_text_domain)?>
    </p>
    <p>
        <?php _e('You can learn more about Buzzsprout and create your own FREE account at <a href="http://www.buzzsprout.com" target="_blank">http://www.buzzsprout.com</a>.', $buzzp_text_domain)?>
    </p>
    
    <h3><?php _e('Buzzsprout Settings', $buzzp_text_domain)?></h3>
    
    <form action="index.php" method="post" class="ajax" autocomplete="off">
        <div class="updated fade" id="result" style="display:none"></div>
        <p>
            <label>
                <span class="field-name">
                    <?php _e('Buzzsprout feed address (URL)', $buzzp_text_domain)?>
                </span>
                <input style="width: 300px" type="text" name="feed-uri" value="<?php echo $buzzp_otions['feed-uri'] ?>" />
                <span class="guide">
                    <?php _e('<a href="http://www.buzzsprout.com/login" target="_blank">Login to your account</a> then click on "Promotion" section.', $buzzp_text_domain)?>
                </span>
                <br class="clear" />
            </label>
            <label>
                <span class="field-name">Include a Flash player?</span>
                <input type="checkbox" name="include-flash"<?php echo $buzzp_otions['include-flash'] ? ' checked="checked"' : '' ?> /> Yes
                <br class="clear" />
            </label>
            <label>
                <span class="field-name">
                    <?php _e('Number of Episodes to return', $buzzp_text_domain)?>
                </span>
                <select name="number-episodes">
<?php
for ($i = 5; $i < 21; $i += 5)
{
    printf('<option value="%s"%s>%s</option>%s', 
            $i, 
            $buzzp_otions['number-episodes'] == $i ? ' selected="selected"' : '',
            $i,
            PHP_EOL);
}

printf('<option value="%s"%s>%s</option>%s', BUZZP_ALL_EPISODES, $buzzp_otions['number-episodes'] == BUZZP_ALL_EPISODES, __('All', $buzzp_text_domain), PHP_EOL);
?>
                </select>
                <br class="clear" />
            </label>
            <input type="hidden" value="<?php echo wp_create_nonce($buzzp_plugin_slug)?>" name="_nonce" />
            <input type="hidden" name="buzzp_action" value="options" />
        </p>
        <p class="submit">
        <input class="button-primary" name="submit" type="submit" value="<?php _e('Save Changes', $buzzp_text_domain)?>" />
        </p>
        <div id="loading" style="display:none"><img src="<?php echo $buzzp_plugin_dir?>images/loading.gif" alt="<?php _e('Loading...', $buzzp_text_domain)?>" /></div>
    </form>
    
    <h3><?php _e('How it works', $buzzp_text_domain)?></h3>
    
    <p class="how-it-works">
        <?php _e('The Buzzsrpout Podcasting plugin drops a new icon onto your "Upload/Insert" toolbar. Click this icon to select the episode you would like to include within your post.', $buzzp_text_domain)?>
        <img src="<?php echo $buzzp_plugin_dir?>images/help-toolbar.png" alt="<?php _e('Toolbar', $buzzp_text_domain)?>" />
    </p>
    <p class="how-it-works">
        <?php _e('Once you select the episode you would like to include, a shortcode will be added to your post. You can feel free to move this around, to wherever you would like the episode to appear within your post.', $buzzp_text_domain)?>
        <img src="<?php echo $buzzp_plugin_dir?>images/help-shortcode.png" alt="<?php _e('Shortcode', $buzzp_text_domain)?>"
    </p>
    
</div>
<?php
}

/**
 * @desc    Adds the Options menu item
 * @return  void
 */
function buzzp_menu_items()
{
    global $buzzp_plugin_name;
    add_options_page($buzzp_plugin_name, $buzzp_plugin_name, 8, basename(__FILE__), 'buzzp_options_form');
}

add_action('admin_menu', 'buzzp_menu_items');

/**
* Handles the [buzzsprout] shortcode
* 
* @param mixed $atts
* @return The partsed HTML
*/
function buzzp_shortcode_handler($atts)
{
    extract(shortcode_atts(array(
        'episode'   => 0,
        'player'    => 'true',
    ), $atts));
    
    // as player=true is preferred, we only disable player if the value is exclusively 'false'
    $parsed_html = sprintf(
        '<script src="http://www.buzzsprout.com/%s/%s.js?%s" type="text/javascript" charset="utf-8"></script>',
        buzz_get_subscription_id(), $episode, $player != 'false' ? 'player=small' : ''
    );
    
    return $parsed_html;
}

add_shortcode(BUZZSPROUT_SHORTTAG, 'buzzp_shortcode_handler');

/**
* Gets the subcription ID (from the RSS URL)
* 
* @param mixed The feed URL
*/
function buzz_get_subscription_id($feed_uri = false)
{
    global $buzzp_plugin_slug;
    
    // if a feed URI is not provided
    // try getting it from DB
    if (!$feed_uri)
    {
        if (!$buzzp_otions = get_option($buzzp_plugin_slug)) return false;
        $feed_uri = $buzzp_otions['feed-uri'];
    }
    
    if (!preg_match_all('|^https?://(www\.)?buzzsprout\.com/([0-9]+)\.rss$|i', $feed_uri, &$matches)) return false;
    return isset($matches[2][0]) ? $matches[2][0] : false;
}

/**
* Create a short tag to add into the post
* 
* @param string Link of the buzz media file
* @param mixed Whether player should be enabled
*/
function buzzp_create_short_tag($buzz_item_link, $player)
{
    // http://www.buzzsprout.com/96/1917-ep-9-rams-vs-titans.mp3
    if (!preg_match_all('|^https?://(www\.)?buzzsprout\.com/[0-9]+/([0-9]+).*|i', $buzz_item_link, &$matches)) return false;
    
    if (!isset($matches[2][0])) return false;
    
    $tag = sprintf('[%s episode="%s" player="%s"]', BUZZSPROUT_SHORTTAG, $matches[2][0], $player ? 'true' : 'false');
    return $tag;
}


/**
* @desc     A small helper to log important data
* The log file can be found in the plugin directory under the name, erm, "log"
* @param    mixed   The data to log
* @return   void
*/
function buzzp_write_log($val)
{
    if (is_array($val))
    {
        $val = print_r($val, 1);
    }
    
    if (is_object($val))
    {
        ob_start();
        var_dump($val);
        $val = ob_get_clean();
    }
    
    $handle = fopen(dirname(__FILE__) . '/log', 'a');
    fwrite($handle, $val . PHP_EOL);
    fclose($handle);
}