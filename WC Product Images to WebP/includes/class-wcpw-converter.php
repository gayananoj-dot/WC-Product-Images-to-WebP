<?php
namespace WCPW;

if (!defined('ABSPATH')) exit;

class Converter {
  const META_ORIGINAL = '_wcpw_original_attached_file';

  public static function init(): void {
    // Convert on product save (featured + gallery).
    add_action('save_post_product', [__CLASS__, 'on_product_save'], 30, 3);
  }

  public static function on_product_save(int $post_id, \WP_Post $post, bool $update): void {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $ids = self::get_product_image_ids($post_id);
    foreach ($ids as $attachment_id) {
      self::convert_attachment_to_webp($attachment_id, [
        'quality' => 82,
        'keep_original' => true,
      ]);
    }
  }

  public static function get_product_image_ids(int $product_id): array {
    $ids = [];

    $thumb_id = (int) get_post_thumbnail_id($product_id);
    if ($thumb_id) $ids[] = $thumb_id;

    $gallery = (string) get_post_meta($product_id, '_product_image_gallery', true);
    if ($gallery) {
      foreach (explode(',', $gallery) as $id) {
        $id = (int) trim($id);
        if ($id) $ids[] = $id;
      }
    }

    // Unique + only valid attachment IDs
    $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
    return $ids;
  }

  public static function is_convertible_attachment(int $attachment_id): bool {
    $mime = (string) get_post_mime_type($attachment_id);
    return in_array($mime, ['image/jpeg', 'image/png'], true);
  }

  public static function convert_attachment_to_webp(int $attachment_id, array $opts = []): array {
    $defaults = [
      'quality' => 82,
      'keep_original' => true,
      'overwrite' => false,
    ];
    $opts = array_merge($defaults, $opts);

    if (!self::is_convertible_attachment($attachment_id)) {
      return ['ok' => false, 'skipped' => true, 'reason' => 'Not jpeg/png'];
    }

    $attached_rel = get_post_meta($attachment_id, '_wp_attached_file', true);
    if (!$attached_rel) return ['ok' => false, 'skipped' => true, 'reason' => 'No attached file meta'];

    $uploads = wp_get_upload_dir();
    $src_abs = trailingslashit($uploads['basedir']) . ltrim($attached_rel, '/');
    if (!file_exists($src_abs)) return ['ok' => false, 'skipped' => true, 'reason' => 'File missing'];

    $ext = strtolower(pathinfo($src_abs, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
      return ['ok' => false, 'skipped' => true, 'reason' => 'Unsupported extension'];
    }

    // If already converted (attachment now points to webp), skip.
    if ($ext === 'webp') {
      return ['ok' => true, 'skipped' => true, 'reason' => 'Already webp'];
    }

    $dst_abs = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src_abs);
    $dst_rel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $attached_rel);

    if (file_exists($dst_abs) && !$opts['overwrite']) {
      // If webp exists but attachment still points to old file, we can switch.
      return self::switch_attachment_to_webp($attachment_id, $attached_rel, $dst_rel, $dst_abs, $opts);
    }

    $made = self::make_webp($src_abs, $dst_abs, (int)$opts['quality']);
    if (!$made) return ['ok' => false, 'skipped' => false, 'reason' => 'Conversion failed'];

    return self::switch_attachment_to_webp($attachment_id, $attached_rel, $dst_rel, $dst_abs, $opts);
  }

  private static function switch_attachment_to_webp(
    int $attachment_id,
    string $src_rel,
    string $dst_rel,
    string $dst_abs,
    array $opts
  ): array {
    // Backup original path once.
    $original = get_post_meta($attachment_id, self::META_ORIGINAL, true);
    if (!$original) {
      update_post_meta($attachment_id, self::META_ORIGINAL, $src_rel);
    }

    // Update attached file + mime type
    update_post_meta($attachment_id, '_wp_attached_file', $dst_rel);

    wp_update_post([
      'ID' => $attachment_id,
      'post_mime_type' => 'image/webp',
    ]);

    // Regenerate metadata/sizes based on new webp original
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attachment_id, $dst_abs);
    if (is_array($meta)) {
      wp_update_attachment_metadata($attachment_id, $meta);
    }

    // Optionally delete original file (we keep by default)
    if (empty($opts['keep_original'])) {
      $uploads = wp_get_upload_dir();
      $src_abs = trailingslashit($uploads['basedir']) . ltrim($src_rel, '/');
      if (file_exists($src_abs)) @unlink($src_abs);
    }

    return [
      'ok' => true,
      'skipped' => false,
      'attachment_id' => $attachment_id,
      'new_file' => $dst_rel,
    ];
  }

  private static function make_webp(string $src_abs, string $dst_abs, int $quality): bool {
    // Prefer Imagick
    if (class_exists('\Imagick')) {
      try {
        $img = new \Imagick($src_abs);
        $img->setImageFormat('webp');
        $img->setImageCompressionQuality(max(1, min(100, $quality)));
        // Some PNGs need alpha preserved; Imagick typically handles this.
        $ok = $img->writeImage($dst_abs);
        $img->clear();
        $img->destroy();
        return (bool) $ok && file_exists($dst_abs);
      } catch (\Throwable $e) {
        // fall through to GD
      }
    }

    // GD fallback
    if (!function_exists('imagewebp')) return false;

    $info = @getimagesize($src_abs);
    if (!$info || empty($info['mime'])) return false;

    switch ($info['mime']) {
      case 'image/jpeg':
        $im = @imagecreatefromjpeg($src_abs);
        break;
      case 'image/png':
        $im = @imagecreatefrompng($src_abs);
        if ($im) {
          // Preserve alpha
          imagepalettetotruecolor($im);
          imagealphablending($im, true);
          imagesavealpha($im, true);
        }
        break;
      default:
        return false;
    }

    if (!$im) return false;

    // Ensure destination directory exists
    $dir = dirname($dst_abs);
    if (!is_dir($dir)) @wp_mkdir_p($dir);

    $ok = @imagewebp($im, $dst_abs, max(1, min(100, $quality)));
    imagedestroy($im);

    return (bool) $ok && file_exists($dst_abs);
  }
}
