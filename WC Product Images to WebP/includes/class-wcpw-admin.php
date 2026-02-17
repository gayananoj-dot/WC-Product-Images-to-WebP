<?php
namespace WCPW;

if (!defined('ABSPATH')) exit;

class Admin {
  public static function init(): void {
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    add_action('wp_ajax_wcpw_bulk_convert', [__CLASS__, 'ajax_bulk_convert']);
  }

  public static function menu(): void {
    add_management_page(
      'WC Product WebP',
      'WC Product WebP',
      'manage_woocommerce',
      'wcpw',
      [__CLASS__, 'page']
    );
  }

  public static function assets(string $hook): void {
    if ($hook !== 'tools_page_wcpw') return;

    wp_enqueue_script(
      'wcpw-admin',
      plugins_url('../assets/wcpw-admin.js', __FILE__),
      ['jquery'],
      WCPW_VERSION,
      true
    );

    wp_localize_script('wcpw-admin', 'WCPW', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('wcpw_bulk'),
      'batchSize' => 25,
    ]);
  }

  public static function page(): void {
    if (!current_user_can('manage_woocommerce')) return;

    echo '<div class="wrap">';
    echo '<h1>WC Product Images → WebP</h1>';
    echo '<p>This converts WooCommerce product featured and gallery images (JPEG/PNG) to WebP and updates attachments.</p>';
    echo '<button class="button button-primary" id="wcpw-start">Bulk convert product images</button>';
    echo '<pre id="wcpw-log" style="margin-top:12px; padding:12px; background:#111; color:#0f0; max-height:420px; overflow:auto;"></pre>';
    echo '</div>';
  }

  public static function ajax_bulk_convert(): void {
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'forbidden'], 403);
    check_ajax_referer('wcpw_bulk', 'nonce');

    $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
    $limit  = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 25;

    $product_ids = get_posts([
      'post_type' => 'product',
      'post_status' => ['publish', 'private', 'draft', 'pending'],
      'fields' => 'ids',
      'posts_per_page' => $limit,
      'offset' => $offset,
      'orderby' => 'ID',
      'order' => 'ASC',
    ]);

    $converted = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($product_ids as $pid) {
      $img_ids = Converter::get_product_image_ids((int)$pid);
      foreach ($img_ids as $aid) {
        $res = Converter::convert_attachment_to_webp((int)$aid, [
          'quality' => 82,
          'keep_original' => true,
        ]);
        if (!empty($res['ok']) && empty($res['skipped'])) $converted++;
        elseif (!empty($res['skipped'])) $skipped++;
        else $errors++;
      }
    }

    wp_send_json_success([
      'nextOffset' => $offset + count($product_ids),
      'done' => count($product_ids) < $limit,
      'counts' => compact('converted', 'skipped', 'errors'),
      'processedProducts' => count($product_ids),
    ]);
  }
}
