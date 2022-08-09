<?php

namespace PressWind\inc\assets;

if (!defined('WP_ENV')) {
  define('WP_ENV', 'development');
}

/**
 * get manifest file generated by vite
 */
function getManifest()
{
  $strJsonFileContents = file_get_contents(dirname(__FILE__) . "/../dist/manifest.json");
  return json_decode(str_replace(
    '\u0000',
    '',
    $strJsonFileContents
  ));
}


function strpos_arr($haystack, $needle)
{
  if (!is_array($needle)) $needle = array($needle);
  foreach ($needle as $what) {
    if (($pos = strpos($haystack, $what)) !== false) return $pos;
  }
  return false;
}


/**
 * preload files
 */
function addPreload()
{
  $preloadAssets = array(
    'main.'
  );
  $config = namespace\getManifest();
  $files = get_object_vars($config);
  $save = [];
  foreach ($files as $key => $value) {
    if (property_exists($config->{$key}, 'assets') || property_exists($config->{$key}, 'css') || property_exists($config->{$key}, 'file')) {
      $assets = $config->{$key}->assets ?? [];
      $css = $config->{$key}->css ?? [];
      $file = $config->{$key}->file ? array($config->{$key}->file) : [];
      $assets = array_merge($assets, $css, $file);
      $path = get_template_directory_uri();
      foreach ($assets as $asset) {
        $path_parts = pathinfo($asset);
        if (!in_array($asset, $save) && strpos_arr($asset, $preloadAssets) !== false) {
          $as = 'image';
          if ($path_parts['extension'] === 'woff2' || $path_parts['extension'] === 'woff') {
            $as = 'font';
          }
          if ($path_parts['extension'] === 'css') {
            $as = 'style';
          }
          if ($path_parts['extension'] === 'js') {
            $as = 'script';
          }
          add_action(
            'wp_head',
            function () use ($path, $asset, $as) {
              echo '<link rel="preload" href="' . $path . '/dist/' . $asset . '" as="' . $as . '" crossorigin="anonymous" />';
            },
            2
          );
          array_push($save, $asset);
        }
      }
    }
  }
}


/**
 * Enqueue scripts.
 *
 */
function addScript()
{
  $path = get_template_directory_uri();

  if (WP_ENV !== 'development') {
    // preload assets
    // addPreload();
    // get files name list from manifest
    $config = namespace\getManifest();
    // search legacy file in manifest for load in first
    // for vite 3 change with vite/legacy-polyfills-legacy
    $legacy = $config->{"-legacy"}->file;
    $k = explode('.', $legacy);
    $token = $k[1];
    wp_enqueue_script('press-wind-theme-' . $token, $path . '/dist/' . $legacy, array(), $token, true);
    // delete key legacy-polyfills
    // for vite 3 change with vite/legacy-polyfills-legacy
    unset($config->{"-legacy"});

    if (!$config) return;
    // load others files
    $files = get_object_vars($config);
    foreach ($files as $key => $value) {
      $file = $config->{$key}->file;
      // get token file
      $k = explode('.', $file);
      $token = $k[1];
      wp_enqueue_script('press-wind-theme-' . $token, $path . '/dist/' . $file, array(), $token, true);
    }
  } else {
    // development
    wp_enqueue_script('press-wind-theme', 'http://localhost:3000/main.js', []);
  }
}


/**
 * Register the JavaScript for the public-facing side of the site.
 */
function enqueue_scripts()
{
  add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (strpos($handle, 'press-wind-theme') === false) {
      return $tag;
    }
    // change the script tag by adding type="module" and return it.
    $tag = '<script type="module" crossorigin src="' . esc_url($src) . '"></script>';
    return $tag;
  }, 10, 3);

  add_action('wp_enqueue_scripts', __NAMESPACE__ . '\addScript');
}


/**
 * Register the CSS
 */
function enqueue_styles()
{
  add_action(
    'wp_enqueue_scripts',
    function () {
      $path = get_template_directory_uri();
      if (WP_ENV !== 'development') {
        // get file name from manifest
        $config = namespace\getManifest();
        if (!$config) return;
        $files = get_object_vars($config);
        // search css key
        foreach ($files as $key => $value) {
          if (property_exists($config->{$key}, 'css')) {
            $css = $config->{$key}->css;
            // $css is array
            foreach ($css as $file) {
              $k = explode('.', $file);
              $token = $k[1];
              wp_enqueue_style(
                'press-wind-theme-' . $token,
                $path . '/dist/' . $file,
                array(),
                $token,
                'all'
              );
            }
          }
        }
      }
    }
  );
}



/**
 * Completely Remove jQuery From WordPress if not admin and is not connected
 */
function removeJquery()
{
  if ($GLOBALS['pagenow'] !== 'wp-login.php' && !is_admin() && !is_user_logged_in()) {
    wp_deregister_script('jquery');
    wp_register_script('jquery', false);
  }
}


// add_action('init', __NAMESPACE__ . '\removeJquery');
add_action('init', __NAMESPACE__ . '\enqueue_scripts');
add_action('init', __NAMESPACE__ . '\enqueue_styles');
