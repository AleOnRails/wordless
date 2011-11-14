<?php
/*
Plugin Name: Wordless
Plugin URI: https://github.com/welaika/wordless
Description: A theme framework.
Version: 0.1
Author: weLaika
Author URI: http://welaika.com/
License: GPL2
*/

require_once "wordless/preprocessors.php";

/**
 * Wordless holds all the plugin setup and initialization.
 */
class Wordless {

  private static $preprocessors = array();
  private static $preferences = array();

  public static function initialize() {
    self::load_i18n();
    self::require_helpers();
    self::require_theme_initializers();
    self::register_preprocessors("CoffeePreprocessor", "CompassPreprocessor");
    self::register_preprocessor_actions();
  }

  public static function register_preprocessors() {
    foreach (func_get_args() as $preprocessor_class) {
      self::$preprocessors[] = new $preprocessor_class();
    }
  }

  /**
   * Register all the actions we need to setup custom rewrite rules
   */
  public static function register_preprocessor_actions() {
    add_action('init', array('Wordless', 'assets_rewrite_rules'));
    add_action('query_vars', array('Wordless', 'query_vars'));
    add_action('parse_request', array('Wordless', 'parse_request'));
  }

  /**
   * Register some custom query vars we need to handle file multiplexing of file preprocessors
   */
  public static function query_vars($query_vars) {
    foreach (self::$preprocessors as $preprocessor) {
      /* this query_var will be set to true when the requested URL needs this preprocessor */
      array_push($query_vars, $preprocessor->query_var_name());
      /* this query_var will be set to the url of the file preprocess */
      array_push($query_vars, $preprocessor->query_var_name('original_url'));
    }
    return $query_vars;
  }

  /**
   * For each preprocessor, it creates a new rewrite rule.
   */
  public static function assets_rewrite_rules() {
    global $wp_rewrite;

    foreach (self::$preprocessors as $preprocessor) {
      add_rewrite_rule('^(.*\.'.$preprocessor->to_extension().')$', 'index.php?'.$preprocessor->query_var_name().'=true&'.$preprocessor->query_var_name('original_url').'=$matches[1]', 'top');
    }
  }

  /**
   * If we get back our custom query vars, then redirect the request to the preprocessor.
   */
  public static function parse_request(&$wp) {
    foreach (self::$preprocessors as $preprocessor) {
      if (array_key_exists($preprocessor->query_var_name(), $wp->query_vars)) {
        $original_url = $wp->query_vars[$preprocessor->query_var_name('original_url')];
        $relative_path = str_replace(preg_replace("/^\//", "", self::theme_url()), '', $original_url);
        $processed_file_path = Wordless::join_paths(get_template_directory(), $relative_path);
        $relative_path = preg_replace("/^.*\/assets\//", "", $relative_path);
        $to_process_file_path = Wordless::join_paths(self::theme_assets_path(), $relative_path);
        $to_process_file_path = preg_replace("/\." . $preprocessor->to_extension() . "$/", "", $to_process_file_path);
        $preprocessor->process_file_with_caching($to_process_file_path, $processed_file_path, Wordless::theme_temp_path());
        exit();
      }
    }
  }

  /**
   * Set a Wordless preference
   */
  public static function set_preference($name, $value) {
    self::$preferences[$name] = $value;
  }

  /**
   * Get a Wordless preference
   */
  public static function preference($name, $default = '') {
    return isset(self::$preferences[$name]) ? self::$preferences[$name] : $default;
  }

  public static function load_i18n() {
    $locales_path = self::theme_locales_path();
    if (file_exists($locales_path) && is_dir($locales_path)) {
      load_theme_textdomain('we', $locales_path);
    }
  }

  public static function require_helpers() {
    require_once 'wordless/helpers.php';
    $helpers_path = self::theme_helpers_path();
    foreach (glob("$helpers_path/*.php") as $filename) {
      require_once $filename;
    }
  }

  public static function require_theme_initializers() {
    $initializers_path = self::theme_initializers_path();
    foreach (glob("$initializers_path/*.php") as $filename) {
      require_once $filename;
    }
  }

  public static function theme_helpers_path() {
    return self::join_paths(get_template_directory(), 'config/helpers');
  }

  public static function theme_initializers_path() {
    return self::join_paths(get_template_directory(), 'config/initializers');
  }

  public static function theme_locales_path() {
    return self::join_paths(get_template_directory(), 'config/locales');
  }

  public static function theme_views_path() {
    return self::join_paths(get_template_directory(), 'theme/views');
  }

  public static function theme_assets_path() {
    return self::join_paths(get_template_directory(), 'theme/assets');
  }

  public static function theme_stylesheets_path() {
    return self::join_paths(get_template_directory(), 'theme/assets/stylesheets');
  }

  public static function theme_javascripts_path() {
    return self::join_paths(get_template_directory(), 'theme/assets/javascripts');
  }

  public static function theme_temp_path() {
    return self::join_paths(get_template_directory(), 'tmp');
  }

  public static function theme_url() {
    return str_replace(get_bloginfo('siteurl'), '', get_bloginfo('template_url'));
  }

  public static function join_paths() {
    $args = func_get_args();
    $paths = array();

    foreach($args as $arg) {
      $paths = array_merge($paths, (array)$arg);
    }

    foreach($paths as &$path) {
      $path = trim($path, '/');
    }

    if (substr($args[0], 0, 1) == '/') {
      $paths[0] = '/' . $paths[0];
    }

    return join('/', $paths);
  }

}

Wordless::initialize();
